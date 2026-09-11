<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Listener;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\CrowdfundingBundle\Entity\LotteryPrize;
use c975L\CrowdfundingBundle\Listener\CrowdfundingTranslationPurgeListener;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslator;
use c975L\UiBundle\Repository\TranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Translations name their owner without a foreign key: this listener is the only thing taking them away with the row
class CrowdfundingTranslationPurgeListenerTest extends TestCase
{
    // Each kind is purged under the owner its translations were filed under, with the id it held before Doctrine nulled it
    #[DataProvider('translatableRows')]
    public function testARemovedRowTakesItsTranslationsWithIt(object $row, string $owner): void
    {
        new \ReflectionProperty($row, 'id')->setValue($row, 12);

        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->once())->method('deleteByOwner')->with($owner, 12);

        $this->remove(new CrowdfundingTranslationPurgeListener($repository), $row);
    }

    /** @return iterable<string, array{object, string}> */
    public static function translatableRows(): iterable
    {
        yield 'campaign' => [new Crowdfunding(), CrowdfundingTranslator::OWNER_CAMPAIGN];
        yield 'counterpart' => [new CrowdfundingCounterpart(), CrowdfundingTranslator::OWNER_COUNTERPART];
        yield 'news' => [new CrowdfundingNews(), CrowdfundingTranslator::OWNER_NEWS];
        yield 'prize' => [new LotteryPrize(), CrowdfundingTranslator::OWNER_PRIZE];
    }

    // A row never saved has no translation to take away
    public function testARowNeverSavedPurgesNothing(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        $this->remove(new CrowdfundingTranslationPurgeListener($repository), new Crowdfunding());
    }

    // Every removal of a flush reaches this listener, and a row of another kind is none of its business
    public function testAnotherEntityPurgesNothing(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        $this->remove(new CrowdfundingTranslationPurgeListener($repository), new \stdClass());
    }

    // The purge follows a removal noted beforehand, and never a postRemove the listener was not told of
    public function testARemovalNotNotedBeforehandPurgesNothing(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        $crowdfunding = new Crowdfunding();
        new \ReflectionProperty($crowdfunding, 'id')->setValue($crowdfunding, 12);

        new CrowdfundingTranslationPurgeListener($repository)->postRemove(new PostRemoveEventArgs($crowdfunding, $this->createStub(EntityManagerInterface::class)));
    }

    // Both events of one removal, in the order Doctrine dispatches them
    private function remove(CrowdfundingTranslationPurgeListener $listener, object $entity): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $listener->preRemove(new PreRemoveEventArgs($entity, $entityManager));
        $listener->postRemove(new PostRemoveEventArgs($entity, $entityManager));
    }
}
