<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Listener;

use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\CrowdfundingBundle\Listener\CrowdfundingNewsListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\TestCase;

// The dates of a news, wherever it is written - the campaign page or the campaign's edit form
class CrowdfundingNewsListenerTest extends TestCase
{
    // Three DATETIME_MUTABLE columns: an immutable stamp on any of them is refused by Doctrine at the very flush that follows
    public function testPrePersistStampsTheDatesWithMutableOnes(): void
    {
        $news = new CrowdfundingNews();

        new CrowdfundingNewsListener()->prePersist($news, $this->createPrePersistArgs());

        $this->assertInstanceOf(\DateTime::class, $news->getCreation());
        $this->assertInstanceOf(\DateTime::class, $news->getPublishedDate());
    }

    // Published straight away: the campaign page's form has no draft state, and a news nobody reads has no reason to be written
    public function testPrePersistPublishesTheNewsAsItIsWritten(): void
    {
        $news = new CrowdfundingNews();

        new CrowdfundingNewsListener()->prePersist($news, $this->createPrePersistArgs());

        $this->assertEqualsWithDelta($news->getCreation()->getTimestamp(), $news->getPublishedDate()->getTimestamp(), 1);
    }

    // A news reaching here with a date already set - a fixture, an import - keeps it: the default fills a gap, it does not impose
    public function testPrePersistKeepsADateAlreadySet(): void
    {
        $typed = new \DateTime('2026-01-15');
        $news = new CrowdfundingNews();
        $news->setPublishedDate($typed);

        new CrowdfundingNewsListener()->prePersist($news, $this->createPrePersistArgs());

        $this->assertSame($typed, $news->getPublishedDate());
    }

    // "preUpdate" and not "preFlush": Doctrine hands the latter every news of the identity map, so one merely read by the campaign page was stamped again on any later flush
    public function testPreUpdateStampsTheModification(): void
    {
        $news = new CrowdfundingNews();

        new CrowdfundingNewsListener()->preUpdate($news, $this->createPreUpdateArgs($news));

        $this->assertInstanceOf(\DateTime::class, $news->getModification());
    }

    // The column is not nullable and "preUpdate" never runs on an insert, so the stamp has to be written here too
    public function testPrePersistStampsTheModification(): void
    {
        $news = new CrowdfundingNews();

        new CrowdfundingNewsListener()->prePersist($news, $this->createPrePersistArgs());

        $this->assertInstanceOf(\DateTime::class, $news->getModification());
    }

    private function createPrePersistArgs(): PrePersistEventArgs
    {
        return new PrePersistEventArgs(new CrowdfundingNews(), $this->createStub(EntityManagerInterface::class));
    }

    private function createPreUpdateArgs(CrowdfundingNews $news): PreUpdateEventArgs
    {
        $changeSet = [];

        return new PreUpdateEventArgs($news, $this->createStub(EntityManagerInterface::class), $changeSet);
    }
}
