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
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

// A picture is signed by whoever adds it, and keeps that author whoever saves it next
class CrowdfundingCounterpartMediaListenerTest extends TestCase
{
    public function testPrePersistRecordsWhoAddedThePicture(): void
    {
        $user = $this->createStub(UserInterface::class);
        $entity = new CrowdfundingCounterpartMedia();

        $this->createListener($user)->prePersist($entity, new PrePersistEventArgs($entity, $this->createStub(ObjectManager::class)));

        $this->assertSame($user, $entity->getUser());
    }

    // The uploader survives every later save of the campaign
    public function testPreUpdateKeepsTheUploader(): void
    {
        $uploader = $this->createStub(UserInterface::class);
        $entity = new CrowdfundingCounterpartMedia()->setUser($uploader);

        $this->createListener($this->createStub(UserInterface::class))->preUpdate($entity, $this->updateArgs($entity));

        $this->assertSame($uploader, $entity->getUser());
    }

    // Nobody logged in: a row written from the CLI keeps no author rather than being handed something it cannot store
    public function testPrePersistLeavesTheUserNullWhenNobodyIsLoggedIn(): void
    {
        $entity = new CrowdfundingCounterpartMedia();

        $this->createListener(null)->prePersist($entity, new PrePersistEventArgs($entity, $this->createStub(ObjectManager::class)));

        $this->assertNull($entity->getUser());
    }

    private function createListener(?UserInterface $user): CrowdfundingCounterpartMediaListener
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new CrowdfundingCounterpartMediaListener($security);
    }

    // The change set is passed by reference, so it has to be a variable
    /** @param array<string, array{mixed, mixed}> $changeSet */
    private function updateArgs(object $entity, array $changeSet = []): PreUpdateEventArgs
    {
        return new PreUpdateEventArgs($entity, $this->createStub(EntityManagerInterface::class), $changeSet);
    }
}
