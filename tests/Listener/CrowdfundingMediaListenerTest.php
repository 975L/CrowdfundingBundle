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
use Doctrine\ORM\Event\PreFlushEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class CrowdfundingMediaListenerTest extends TestCase
{
    // The campaign's own medias, not the whole table: a photo added to one campaign is placed after that campaign's last one
    public function testPreFlushPlacesANewMediaAfterTheCampaignLastOne(): void
    {
        $crowdfunding = new Crowdfunding();
        $crowdfunding->addMedia(new CrowdfundingMedia()->setPosition(10));
        $crowdfunding->addMedia(new CrowdfundingMedia()->setPosition(20));

        $media = new CrowdfundingMedia()->setCrowdfunding($crowdfunding);

        $this->createListener()->preFlush($media, $this->createPreFlushArgs());

        $this->assertSame(25, $media->getPosition());
    }

    // Five apart, as everywhere else, so a media can be slipped between two without renumbering
    public function testPreFlushStartsAtFiveOnACampaignWithoutAnyMedia(): void
    {
        $media = new CrowdfundingMedia()->setCrowdfunding(new Crowdfunding());

        $this->createListener()->preFlush($media, $this->createPreFlushArgs());

        $this->assertSame(5, $media->getPosition());
    }

    // The order an admin arranged by hand is theirs, and is never recomputed
    public function testPreFlushLeavesAPositionAlreadySet(): void
    {
        $crowdfunding = new Crowdfunding();
        $crowdfunding->addMedia(new CrowdfundingMedia()->setPosition(50));

        $media = new CrowdfundingMedia()->setCrowdfunding($crowdfunding)->setPosition(1);

        $this->createListener()->preFlush($media, $this->createPreFlushArgs());

        $this->assertSame(1, $media->getPosition());
    }

    public function testPreFlushRecordsWhoUploadedTheMedia(): void
    {
        $user = $this->createStub(UserInterface::class);
        $media = new CrowdfundingMedia()->setCrowdfunding(new Crowdfunding());

        $this->createListener($user)->preFlush($media, $this->createPreFlushArgs());

        $this->assertSame($user, $media->getUser());
    }

    // A media carries no modification date - the trait asks method_exists() before reaching for one - so the uploader is kept rather than replaced by whoever saves the campaign next
    public function testPreFlushKeepsTheUploaderOnAMediaSavedAgain(): void
    {
        $uploader = $this->createStub(UserInterface::class);
        $media = new CrowdfundingMedia()->setCrowdfunding(new Crowdfunding());
        $media->setUser($uploader);

        $this->createListener($this->createStub(UserInterface::class))->preFlush($media, $this->createPreFlushArgs());

        $this->assertSame($uploader, $media->getUser());
    }

    private function createPreFlushArgs(): PreFlushEventArgs
    {
        return new PreFlushEventArgs($this->createStub(EntityManagerInterface::class));
    }

    private function createListener(?UserInterface $user = null): CrowdfundingMediaListener
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new CrowdfundingMediaListener($security, $this->createStub(EntityManagerInterface::class));
    }
}
