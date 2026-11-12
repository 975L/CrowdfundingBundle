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
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingMedia;
use c975L\CrowdfundingBundle\Listener\CrowdfundingMediaListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class CrowdfundingMediaListenerTest extends TestCase
{
    // The campaign's own medias, not the whole table: a photo added to one campaign is placed after that campaign's last one
    public function testPrePersistPlacesANewMediaAfterTheCampaignLastOne(): void
    {
        $crowdfunding = new Crowdfunding();
        $crowdfunding->addMedia(new CrowdfundingMedia()->setPosition(10));
        $crowdfunding->addMedia(new CrowdfundingMedia()->setPosition(20));

        $media = new CrowdfundingMedia()->setCrowdfunding($crowdfunding);

        $this->createListener()->prePersist($media, new PrePersistEventArgs($media, $this->createStub(ObjectManager::class)));

        $this->assertSame(25, $media->getPosition());
    }

    // Five apart, as everywhere else, so a media can be slipped between two without renumbering
    public function testPrePersistStartsAtFiveOnACampaignWithoutAnyMedia(): void
    {
        $media = new CrowdfundingMedia()->setCrowdfunding(new Crowdfunding());

        $this->createListener()->prePersist($media, new PrePersistEventArgs($media, $this->createStub(ObjectManager::class)));

        $this->assertSame(5, $media->getPosition());
    }

    // The order an admin arranged by hand is theirs, and is never recomputed
    public function testPreUpdateLeavesAPositionAlreadySet(): void
    {
        $crowdfunding = new Crowdfunding();
        $crowdfunding->addMedia(new CrowdfundingMedia()->setPosition(50));

        $media = new CrowdfundingMedia()->setCrowdfunding($crowdfunding)->setPosition(1);

        $this->createListener()->preUpdate($media, $this->updateArgs($media));

        $this->assertSame(1, $media->getPosition());
    }

    public function testPrePersistRecordsWhoUploadedTheMedia(): void
    {
        $user = $this->createStub(UserInterface::class);
        $media = new CrowdfundingMedia()->setCrowdfunding(new Crowdfunding());

        $this->createListener($user)->prePersist($media, new PrePersistEventArgs($media, $this->createStub(ObjectManager::class)));

        $this->assertSame($user, $media->getUser());
    }

    // The uploader is kept rather than replaced by whoever saves the campaign next
    public function testPreUpdateKeepsTheUploaderOnAMediaSavedAgain(): void
    {
        $uploader = $this->createStub(UserInterface::class);
        $media = new CrowdfundingMedia()->setCrowdfunding(new Crowdfunding());
        $media->setUser($uploader);

        $this->createListener($this->createStub(UserInterface::class))->preUpdate($media, $this->updateArgs($media));

        $this->assertSame($uploader, $media->getUser());
    }

    private function createListener(?UserInterface $user = null): CrowdfundingMediaListener
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new CrowdfundingMediaListener($security);
    }

    // The change set is passed by reference, so it has to be a variable
    /** @param array<string, array{mixed, mixed}> $changeSet */
    private function updateArgs(object $entity, array $changeSet = []): PreUpdateEventArgs
    {
        return new PreUpdateEventArgs($entity, $this->createStub(EntityManagerInterface::class), $changeSet);
    }
}
