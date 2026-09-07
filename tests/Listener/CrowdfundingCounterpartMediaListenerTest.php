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
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpartMedia;
use c975L\CrowdfundingBundle\Listener\CrowdfundingCounterpartMediaListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

// The counterpart's own picture, whose row is written empty by CrowdfundingCounterpartListener and filled by the upload afterwards
class CrowdfundingCounterpartMediaListenerTest extends TestCase
{
    public function testPreFlushRecordsWhoUploadedThePicture(): void
    {
        $user = $this->createStub(UserInterface::class);
        $media = new CrowdfundingCounterpartMedia();

        $this->createListener($user)->preFlush($media, $this->createPreFlushArgs());

        $this->assertSame($user, $media->getUser());
    }

    // A media carries no modification date - the trait asks method_exists() before reaching for one - so the uploader survives every later save of the counterpart
    public function testPreFlushKeepsTheUploaderOnAPictureSavedAgain(): void
    {
        $uploader = $this->createStub(UserInterface::class);
        $media = new CrowdfundingCounterpartMedia();
        $media->setUser($uploader);

        $this->createListener($this->createStub(UserInterface::class))->preFlush($media, $this->createPreFlushArgs());

        $this->assertSame($uploader, $media->getUser());
    }

    public function testPreFlushLeavesTheUserNullWhenNobodyIsLoggedIn(): void
    {
        $media = new CrowdfundingCounterpartMedia();

        $this->createListener()->preFlush($media, $this->createPreFlushArgs());

        $this->assertNull($media->getUser());
    }

    private function createPreFlushArgs(): PreFlushEventArgs
    {
        return new PreFlushEventArgs($this->createStub(EntityManagerInterface::class));
    }

    private function createListener(?UserInterface $user = null): CrowdfundingCounterpartMediaListener
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new CrowdfundingCounterpartMediaListener($security, $this->createStub(EntityManagerInterface::class));
    }
}
