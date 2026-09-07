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
use c975L\CrowdfundingBundle\Entity\LotteryPrize;
use c975L\CrowdfundingBundle\Listener\LotteryPrizeListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class LotteryPrizeListenerTest extends TestCase
{
    // A DATETIME_MUTABLE column: an immutable stamp is refused by Doctrine at flush, and a prize is saved with the lottery it hangs from
    public function testPreFlushStampsTheModificationWithAMutableDate(): void
    {
        $prize = new LotteryPrize();

        $this->createListener()->preFlush($prize, $this->createPreFlushArgs());

        $this->assertInstanceOf(\DateTime::class, $prize->getModification());
    }

    public function testPrePersistStampsTheCreationWithAMutableDate(): void
    {
        $prize = new LotteryPrize();

        $this->createListener()->prePersist($prize, new PrePersistEventArgs($prize, $this->createStub(ObjectManager::class)));

        $this->assertInstanceOf(\DateTime::class, $prize->getCreation());
    }

    public function testPreFlushRecordsWhoEditedThePrize(): void
    {
        $user = $this->createStub(UserInterface::class);
        $prize = new LotteryPrize();

        $this->createListener($user)->preFlush($prize, $this->createPreFlushArgs());

        $this->assertSame($user, $prize->getUser());
    }

    // Nobody logged in: a prize written from the CLI keeps no editor rather than being handed something it cannot store
    public function testPreFlushLeavesTheUserNullWhenNobodyIsLoggedIn(): void
    {
        $prize = new LotteryPrize();

        $this->createListener()->preFlush($prize, $this->createPreFlushArgs());

        $this->assertNull($prize->getUser());
    }

    private function createPreFlushArgs(): PreFlushEventArgs
    {
        return new PreFlushEventArgs($this->createStub(EntityManagerInterface::class));
    }

    private function createListener(?UserInterface $user = null): LotteryPrizeListener
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new LotteryPrizeListener($security, $this->createStub(EntityManagerInterface::class));
    }
}
