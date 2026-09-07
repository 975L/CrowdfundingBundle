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
use c975L\CrowdfundingBundle\Entity\CrowdfundingVideo;
use c975L\CrowdfundingBundle\Listener\CrowdfundingVideoListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

// The campaign's presentation video, uploaded or pointed at on YouTube
class CrowdfundingVideoListenerTest extends TestCase
{
    public function testPreFlushRecordsWhoAddedTheVideo(): void
    {
        $user = $this->createStub(UserInterface::class);
        $video = new CrowdfundingVideo();

        $this->createListener($user)->preFlush($video, $this->createPreFlushArgs());

        $this->assertSame($user, $video->getUser());
    }

    // A media carries no modification date - the trait asks method_exists() before reaching for one - so the uploader survives every later save of the campaign
    public function testPreFlushKeepsTheUploaderOnAVideoSavedAgain(): void
    {
        $uploader = $this->createStub(UserInterface::class);
        $video = new CrowdfundingVideo();
        $video->setUser($uploader);

        $this->createListener($this->createStub(UserInterface::class))->preFlush($video, $this->createPreFlushArgs());

        $this->assertSame($uploader, $video->getUser());
    }

    public function testPreFlushLeavesTheUserNullWhenNobodyIsLoggedIn(): void
    {
        $video = new CrowdfundingVideo();

        $this->createListener()->preFlush($video, $this->createPreFlushArgs());

        $this->assertNull($video->getUser());
    }

    private function createPreFlushArgs(): PreFlushEventArgs
    {
        return new PreFlushEventArgs($this->createStub(EntityManagerInterface::class));
    }

    private function createListener(?UserInterface $user = null): CrowdfundingVideoListener
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new CrowdfundingVideoListener($security, $this->createStub(EntityManagerInterface::class));
    }
}
