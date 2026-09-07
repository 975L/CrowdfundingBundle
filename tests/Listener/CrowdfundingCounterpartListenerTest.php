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
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\AsciiSlugger;

class CrowdfundingCounterpartListenerTest extends TestCase
{
    // The slug is rebuilt from the title on every save rather than typed: it is what the basket line and the counterpart's own anchor are keyed on
    public function testPreFlushSlugsTheTitleInLowercase(): void
    {
        $counterpart = new CrowdfundingCounterpart()->setTitle('Le Tote bag édition limitée');

        $this->createListener()->preFlush($counterpart, $this->createPreFlushArgs());

        $this->assertSame('le-tote-bag-edition-limitee', $counterpart->getSlug());
    }

    // A DATETIME_MUTABLE column: an immutable stamp is refused by Doctrine at flush
    public function testPreFlushStampsTheModificationWithAMutableDate(): void
    {
        $counterpart = new CrowdfundingCounterpart()->setTitle('Un lot');

        $this->createListener()->preFlush($counterpart, $this->createPreFlushArgs());

        $this->assertInstanceOf(\DateTime::class, $counterpart->getModification());
    }

    public function testPreFlushRecordsWhoEditedTheCounterpart(): void
    {
        $user = $this->createStub(UserInterface::class);
        $counterpart = new CrowdfundingCounterpart()->setTitle('Un lot');

        $this->createListener($user)->preFlush($counterpart, $this->createPreFlushArgs());

        $this->assertSame($user, $counterpart->getUser());
    }

    // The media row has to exist before its file can be attached, the owner not being persisted yet when the upload form is handled
    public function testPrePersistGivesTheCounterpartAnEmptyMediaToFill(): void
    {
        $counterpart = new CrowdfundingCounterpart();

        $this->createListener()->prePersist($counterpart, $this->createPrePersistArgs($counterpart));

        $this->assertInstanceOf(CrowdfundingCounterpartMedia::class, $counterpart->getMedia());
        $this->assertSame($counterpart, $counterpart->getMedia()->getCrowdfundingCounterpart());
    }

    // Vich's own column is the one immutable date of these entities, and the placeholder is written for it
    public function testThePlaceholderMediaIsStampedWithAnImmutableDate(): void
    {
        $counterpart = new CrowdfundingCounterpart();

        $this->createListener()->prePersist($counterpart, $this->createPrePersistArgs($counterpart));

        $this->assertInstanceOf(\DateTimeImmutable::class, $counterpart->getMedia()->getUpdatedAt());
    }

    // A counterpart created with its media already filled keeps it
    public function testPrePersistLeavesAMediaAlreadyThere(): void
    {
        $media = new CrowdfundingCounterpartMedia();
        $counterpart = new CrowdfundingCounterpart()->setMedia($media);

        $this->createListener()->prePersist($counterpart, $this->createPrePersistArgs($counterpart));

        $this->assertSame($media, $counterpart->getMedia());
    }

    public function testPrePersistStampsTheCreationWithAMutableDate(): void
    {
        $counterpart = new CrowdfundingCounterpart();

        $this->createListener()->prePersist($counterpart, $this->createPrePersistArgs($counterpart));

        $this->assertInstanceOf(\DateTime::class, $counterpart->getCreation());
    }

    private function createPreFlushArgs(): PreFlushEventArgs
    {
        return new PreFlushEventArgs($this->createStub(EntityManagerInterface::class));
    }

    private function createPrePersistArgs(object $entity): PrePersistEventArgs
    {
        return new PrePersistEventArgs($entity, $this->createStub(ObjectManager::class));
    }

    private function createListener(?UserInterface $user = null): CrowdfundingCounterpartListener
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new CrowdfundingCounterpartListener($security, $this->createStub(EntityManagerInterface::class), new AsciiSlugger());
    }
}
