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
use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Listener\LotteryListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class LotteryListenerTest extends TestCase
{
    // The identifier is the lottery's public url, and the route requires exactly thirteen characters: "XXX-9999-9999", the letters from an alphabet without the vowels that read alike
    public function testPrePersistNamesTheLotteryWithItsPublicIdentifier(): void
    {
        $lottery = new Lottery();

        $this->createListener()->prePersist($lottery, $this->createPrePersistArgs($lottery));

        $this->assertMatchesRegularExpression('/^[BCDFGHJKLMNPQRSTVWXYZ]{3}-[0-9A-F]{4}-[0-9A-F]{4}$/', $lottery->getIdentifier());
        $this->assertSame(13, \strlen($lottery->getIdentifier()), 'The lottery_display route requires an identifier of exactly thirteen characters.');
    }

    // Two lotteries never share a url
    public function testPrePersistDrawsADifferentIdentifierEachTime(): void
    {
        $listener = $this->createListener();

        $identifiers = [];
        for ($i = 0; $i < 20; ++$i) {
            $lottery = new Lottery();
            $listener->prePersist($lottery, $this->createPrePersistArgs($lottery));
            $identifiers[] = $lottery->getIdentifier();
        }

        $this->assertCount(20, array_unique($identifiers));
    }

    // A lottery is created running: it is the campaign that decides when the tickets stop being sold, and the admin who closes it
    public function testPrePersistOpensTheLottery(): void
    {
        $lottery = new Lottery();

        $this->createListener()->prePersist($lottery, $this->createPrePersistArgs($lottery));

        $this->assertTrue($lottery->isActive());
    }

    public function testPrePersistStampsTheCreationWithAMutableDate(): void
    {
        $lottery = new Lottery();

        $this->createListener()->prePersist($lottery, $this->createPrePersistArgs($lottery));

        $this->assertInstanceOf(\DateTime::class, $lottery->getCreation());
    }

    // A DATETIME_MUTABLE column: an immutable stamp is refused by Doctrine at flush
    public function testPreFlushStampsTheModificationWithAMutableDate(): void
    {
        $lottery = new Lottery();

        $this->createListener()->preFlush($lottery, $this->createPreFlushArgs());

        $this->assertInstanceOf(\DateTime::class, $lottery->getModification());
    }

    public function testPreFlushRecordsWhoEditedTheLottery(): void
    {
        $user = $this->createStub(UserInterface::class);
        $lottery = new Lottery();

        $this->createListener($user)->preFlush($lottery, $this->createPreFlushArgs());

        $this->assertSame($user, $lottery->getUser());
    }

    private function createPreFlushArgs(): PreFlushEventArgs
    {
        return new PreFlushEventArgs($this->createStub(EntityManagerInterface::class));
    }

    private function createPrePersistArgs(object $entity): PrePersistEventArgs
    {
        return new PrePersistEventArgs($entity, $this->createStub(ObjectManager::class));
    }

    private function createListener(?UserInterface $user = null): LotteryListener
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new LotteryListener($security, $this->createStub(EntityManagerInterface::class));
    }
}
