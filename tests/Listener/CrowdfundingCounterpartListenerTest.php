<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Listener;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpartMedia;
use c975L\CrowdfundingBundle\Listener\CrowdfundingCounterpartListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\AsciiSlugger;

// A counterpart is named, dated and signed on a write and on a real edit only: a contribution counting its run never hands it to the contributor
class CrowdfundingCounterpartListenerTest extends TestCase
{
    // The slug is built from the title rather than typed: it is what the basket line and the counterpart's own anchor are keyed on
    public function testPrePersistSlugsTheTitleInLowercase(): void
    {
        $counterpart = new CrowdfundingCounterpart()->setTitle('Le Tote bag édition limitée');

        $this->createListener()->prePersist($counterpart, $this->persistArgs($counterpart));

        $this->assertSame('le-tote-bag-edition-limitee', $counterpart->getSlug());
    }

    // A renamed counterpart is slugged again
    public function testAnEditSlugsDatesAndSignsTheCounterpart(): void
    {
        $user = $this->createStub(UserInterface::class);
        $counterpart = new CrowdfundingCounterpart()->setTitle('Item 2')->setSlug('item-1');

        $this->createListener($user)->preUpdate($counterpart, $this->updateArgs($counterpart, ['title' => ['Item 1', 'Item 2']]));

        $this->assertSame('item-2', $counterpart->getSlug());
        $this->assertInstanceOf(\DateTime::class, $counterpart->getModification());
        $this->assertSame($user, $counterpart->getUser());
    }

    // A contribution counting the run dates the counterpart, and changes neither its author nor anything else
    public function testAContributionChangesNeitherTheAuthorNorTheDate(): void
    {
        $author = $this->createStub(UserInterface::class);
        $counterpart = new CrowdfundingCounterpart()->setTitle('Item 1')->setSlug('item-1')->setUser($author);
        $counterpart->setModification(new \DateTime('2026-01-12'));

        $this->createListener($this->createStub(UserInterface::class))->preUpdate($counterpart, $this->updateArgs($counterpart, ['orderedQuantity' => [1, 2], 'modification' => [null, null]]));

        $this->assertSame($author, $counterpart->getUser());
        $this->assertSame('2026-01-12', $counterpart->getModification()->format('Y-m-d'));
    }

    public function testPrePersistRecordsWhoCreatedTheCounterpart(): void
    {
        $user = $this->createStub(UserInterface::class);
        $counterpart = new CrowdfundingCounterpart()->setTitle('Item 1');

        $this->createListener($user)->prePersist($counterpart, $this->persistArgs($counterpart));

        $this->assertSame($user, $counterpart->getUser());
    }

    // The media row has to exist before its file can be attached, the owner not being persisted yet when the upload form is handled
    public function testPrePersistGivesTheCounterpartAnEmptyMediaToFill(): void
    {
        $counterpart = new CrowdfundingCounterpart();

        $this->createListener()->prePersist($counterpart, $this->persistArgs($counterpart));

        $this->assertInstanceOf(CrowdfundingCounterpartMedia::class, $counterpart->getMedia());
        $this->assertSame($counterpart, $counterpart->getMedia()->getCrowdfundingCounterpart());
    }

    // Vich's own column is the one immutable date of these entities, and the placeholder is written for it
    public function testThePlaceholderMediaIsStampedWithAnImmutableDate(): void
    {
        $counterpart = new CrowdfundingCounterpart();

        $this->createListener()->prePersist($counterpart, $this->persistArgs($counterpart));

        $this->assertInstanceOf(\DateTimeImmutable::class, $counterpart->getMedia()->getUpdatedAt());
    }

    // A counterpart created with its media already filled keeps it
    public function testPrePersistLeavesAMediaAlreadyThere(): void
    {
        $media = new CrowdfundingCounterpartMedia();
        $counterpart = new CrowdfundingCounterpart()->setMedia($media);

        $this->createListener()->prePersist($counterpart, $this->persistArgs($counterpart));

        $this->assertSame($media, $counterpart->getMedia());
    }

    // DATETIME_MUTABLE columns, both set on an insert since preUpdate never runs on one
    public function testPrePersistStampsBothDatesWithMutableDates(): void
    {
        $counterpart = new CrowdfundingCounterpart();

        $this->createListener()->prePersist($counterpart, $this->persistArgs($counterpart));

        $this->assertInstanceOf(\DateTime::class, $counterpart->getCreation());
        $this->assertInstanceOf(\DateTime::class, $counterpart->getModification());
    }

    private function persistArgs(object $entity): PrePersistEventArgs
    {
        return new PrePersistEventArgs($entity, $this->createStub(ObjectManager::class));
    }

    /** @param array<string, array{mixed, mixed}> $changeSet */
    private function updateArgs(object $entity, array $changeSet): PreUpdateEventArgs
    {
        return new PreUpdateEventArgs($entity, $this->createStub(EntityManagerInterface::class), $changeSet);
    }

    private function createListener(?UserInterface $user = null): CrowdfundingCounterpartListener
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new CrowdfundingCounterpartListener($security, new AsciiSlugger());
    }
}
