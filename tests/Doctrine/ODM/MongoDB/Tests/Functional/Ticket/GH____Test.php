<?php

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket;

use Doctrine\ODM\MongoDB\Events;
use Doctrine\ODM\MongoDB\Event\OnFlushEventArgs;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Doctrine\ODM\MongoDB\Tests\QueryLogger;

class GH____Test extends \Doctrine\ODM\MongoDB\Tests\BaseTest
{
    /**
     * @var Doctrine\ODM\MongoDB\Tests\QueryLogger
     */
    private $ql;

    protected function getConfiguration()
    {
        if ( ! isset($this->ql)) {
            $this->ql = new QueryLogger();
        }

        $config = parent::getConfiguration();
        $config->setLoggerCallable($this->ql);

        return $config;
    }

    public function testUpdatingDocumentBeforeItsInsertionShouldNotEntailMultipleQueries()
    {
        $listener = new GH____Listener();
        $this->dm->getEventManager()->addEventListener(Events::onFlush, $listener);

        $doc = new GH____Document();
        $doc->name = 'foo';

        $this->dm->persist($doc);
        $this->dm->flush();

        $this->assertCount(1, $this->ql, 'Changing a document before its insertion requires one query');
        $this->assertEquals('foo-changed', $doc->name);
        $this->assertEquals(1, $listener->inserts);
        $this->assertEquals(0, $listener->updates);
    }
}

/** @ODM\Document */
class GH____Document
{
    const CLASSNAME = __CLASS__;

    /** @ODM\Id */
    public $id;

    /** @ODM\String */
    public $name;
}

class GH____Listener
{
    public $inserts = 0;
    public $updates = 0;

    public function onFlush(OnFlushEventArgs $args)
    {
        $dm = $args->getDocumentManager();
        $uow = $dm->getUnitOfWork();

        foreach ($uow->getScheduledDocumentInsertions() as $document) {
            $this->inserts++;
            if ($document instanceof GH____Document) {
                $document->name .= '-changed';
                $cm = $dm->getClassMetadata(GH____Document::CLASSNAME);
                $uow->recomputeSingleDocumentChangeSet($cm, $document);
            }
        }

        foreach ($uow->getScheduledDocumentUpdates() as $document) {
            $this->updates++;
        }
    }
}
