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
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

// A campaign is placed, dated and signed on a write and on a real edit only: a contribution dating it never hands it to the contributor
class CrowdfundingListenerTest extends TestCase
{
    // The column is nullable and the index page orders on it: a campaign saved without one would sort before every other
    public function testPrePersistPlacesANewCampaignAfterTheLastOne(): void
    {
        $crowdfunding = new Crowdfunding();

        $this->createListener([new Crowdfunding()->setPosition(10), new Crowdfunding()->setPosition(25)])->prePersist($crowdfunding, $this->persistArgs($crowdfunding));

        $this->assertSame(30, $crowdfunding->getPosition());
    }

    // Five apart rather than one, so an admin can slip a campaign between two without renumbering the lot
    public function testPrePersistStartsAtFiveOnAnEmptyTable(): void
    {
        $crowdfunding = new Crowdfunding();

        $this->createListener()->prePersist($crowdfunding, $this->persistArgs($crowdfunding));

        $this->assertSame(5, $crowdfunding->getPosition());
    }

    // A position an admin dragged into place is theirs, and is never recomputed on the next save
    public function testPreUpdateLeavesAPositionAlreadySet(): void
    {
        $crowdfunding = new Crowdfunding()->setPosition(3);

        $this->createListener([new Crowdfunding()->setPosition(100)])->preUpdate($crowdfunding, $this->updateArgs($crowdfunding, ['title' => ['a', 'b']]));

        $this->assertSame(3, $crowdfunding->getPosition());
    }

    // DATETIME_MUTABLE columns, both set on an insert since preUpdate never runs on one
    public function testPrePersistStampsBothDatesWithMutableDates(): void
    {
        $crowdfunding = new Crowdfunding();

        $this->createListener()->prePersist($crowdfunding, $this->persistArgs($crowdfunding));

        $this->assertInstanceOf(\DateTime::class, $crowdfunding->getCreation());
        $this->assertInstanceOf(\DateTime::class, $crowdfunding->getModification());
    }

    // A campaign built with its own dates (the demo dataset) keeps them
    public function testPrePersistKeepsTheDatesItCarries(): void
    {
        $crowdfunding = new Crowdfunding();
        $crowdfunding->setCreation(new \DateTime('2026-02-01'));

        $this->createListener()->prePersist($crowdfunding, $this->persistArgs($crowdfunding));

        $this->assertSame('2026-02-01', $crowdfunding->getCreation()->format('Y-m-d'));
    }

    // Security only guarantees its own UserInterface: a campaign saved from the CLI keeps none
    public function testPrePersistLeavesTheUserNullWhenNobodyIsLoggedIn(): void
    {
        $crowdfunding = new Crowdfunding();

        $this->createListener()->prePersist($crowdfunding, $this->persistArgs($crowdfunding));

        $this->assertNull($crowdfunding->getUser());
    }

    public function testPrePersistRecordsWhoCreatedTheCampaign(): void
    {
        $user = $this->createStub(UserInterface::class);
        $crowdfunding = new Crowdfunding();

        $this->createListener(user: $user)->prePersist($crowdfunding, $this->persistArgs($crowdfunding));

        $this->assertSame($user, $crowdfunding->getUser());
    }

    // An editor's change hands the row to them
    public function testAnEditHandsTheRowToWhoeverEditsIt(): void
    {
        $author = $this->createStub(UserInterface::class);
        $editor = $this->createStub(UserInterface::class);
        $crowdfunding = new Crowdfunding()->setUser($author)->setPosition(5);

        $this->createListener(user: $editor)->preUpdate($crowdfunding, $this->updateArgs($crowdfunding, ['title' => ['a', 'b']]));

        $this->assertSame($editor, $crowdfunding->getUser());
    }

    // A member adding a counterpart to their basket, or a contribution dating the campaign, never becomes its author
    public function testAContributionChangesNeitherTheAuthorNorTheDate(): void
    {
        $author = $this->createStub(UserInterface::class);
        $crowdfunding = new Crowdfunding()->setUser($author)->setPosition(5);
        $crowdfunding->setModification(new \DateTime('2026-01-12'));

        $this->createListener(user: $this->createStub(UserInterface::class))->preUpdate($crowdfunding, $this->updateArgs($crowdfunding, ['modification' => [null, new \DateTime()]]));

        $this->assertSame($author, $crowdfunding->getUser());
        $this->assertSame('2026-01-12', $crowdfunding->getModification()->format('Y-m-d'));
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
