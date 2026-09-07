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
use c975L\CrowdfundingBundle\Listener\CrowdfundingListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class CrowdfundingListenerTest extends TestCase
{
    // The column is nullable and the index page orders on it: a campaign saved without one would sort before every other, whatever the admin had arranged
    public function testPreFlushPlacesANewCampaignAfterTheLastOne(): void
    {
        $crowdfunding = new Crowdfunding();

        $this->createListener([new Crowdfunding()->setPosition(10), new Crowdfunding()->setPosition(25)])->preFlush($crowdfunding, $this->createPreFlushArgs());

        $this->assertSame(30, $crowdfunding->getPosition());
    }

    // Five apart rather than one, so an admin can slip a campaign between two without renumbering the lot
    public function testPreFlushStartsAtFiveOnAnEmptyTable(): void
    {
        $crowdfunding = new Crowdfunding();

        $this->createListener()->preFlush($crowdfunding, $this->createPreFlushArgs());

        $this->assertSame(5, $crowdfunding->getPosition());
    }

    // A position an admin dragged into place is theirs, and is never recomputed on the next save
    public function testPreFlushLeavesAPositionAlreadySet(): void
    {
        $crowdfunding = new Crowdfunding()->setPosition(3);

        $this->createListener([new Crowdfunding()->setPosition(100)])->preFlush($crowdfunding, $this->createPreFlushArgs());

        $this->assertSame(3, $crowdfunding->getPosition());
    }

    // A DATETIME_MUTABLE column: an immutable stamp is refused by Doctrine at flush, on the very save this listener runs for
    public function testPreFlushStampsTheModificationWithAMutableDate(): void
    {
        $crowdfunding = new Crowdfunding();

        $this->createListener()->preFlush($crowdfunding, $this->createPreFlushArgs());

        $this->assertInstanceOf(\DateTime::class, $crowdfunding->getModification());
    }

    public function testPrePersistStampsTheCreationWithAMutableDate(): void
    {
        $crowdfunding = new Crowdfunding();

        $this->createListener()->prePersist($crowdfunding, new PrePersistEventArgs($crowdfunding, $this->createStub(ObjectManager::class)));

        $this->assertInstanceOf(\DateTime::class, $crowdfunding->getCreation());
    }

    // Security only guarantees its own UserInterface: a campaign saved from the CLI, or by a user entity not implementing the c975L one, keeps none rather than being handed something it cannot store
    public function testPreFlushLeavesTheUserNullWhenNobodyIsLoggedIn(): void
    {
        $crowdfunding = new Crowdfunding();

        $this->createListener()->preFlush($crowdfunding, $this->createPreFlushArgs());

        $this->assertNull($crowdfunding->getUser());
    }

    public function testPreFlushRecordsWhoCreatedTheCampaign(): void
    {
        $user = $this->createStub(UserInterface::class);
        $crowdfunding = new Crowdfunding();

        $this->createListener(user: $user)->preFlush($crowdfunding, $this->createPreFlushArgs());

        $this->assertSame($user, $crowdfunding->getUser());
    }

    // The column holds the last editor rather than the author: this listener stamps the modification just above, so any row saved again by somebody logged in carries them from that save on
    public function testPreFlushHandsTheRowToWhoeverEditsIt(): void
    {
        $author = $this->createStub(UserInterface::class);
        $editor = $this->createStub(UserInterface::class);

        $crowdfunding = new Crowdfunding()->setUser($author);
        $crowdfunding->setCreation(new \DateTime('2026-01-01 10:00:00'));

        $this->createListener(user: $editor)->preFlush($crowdfunding, $this->createPreFlushArgs());

        $this->assertSame($editor, $crowdfunding->getUser());
    }

    // Whoever is logged in owns the write: the row is handed back to the unit of work so the column actually reaches the UPDATE
    public function testPreFlushPersistsTheRowItAssignedAUserTo(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($this->createStub(EntityRepository::class));
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(Crowdfunding::class));

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($this->createStub(UserInterface::class));

        new CrowdfundingListener($security, $entityManager)->preFlush(new Crowdfunding(), $this->createPreFlushArgs());
    }

    private function createPreFlushArgs(): PreFlushEventArgs
    {
        return new PreFlushEventArgs($this->createStub(EntityManagerInterface::class));
    }

    /** @param list<Crowdfunding> $existing */
    private function createListener(array $existing = [], ?UserInterface $user = null): CrowdfundingListener
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findAll')->willReturn($existing);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new CrowdfundingListener($security, $entityManager);
    }
}
