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
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingMedia;
use c975L\CrowdfundingBundle\Listener\CrowdfundingCacheInvalidationListener;
use c975L\CrowdfundingBundle\Service\CrowdfundingBlockCacheInvalidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CrowdfundingCacheInvalidationListenerTest extends TestCase
{
    /** @return list<array{0: object}> */
    public static function rowsTheCampaignIsDrawnFrom(): array
    {
        return [
            [new Crowdfunding()],
            [new CrowdfundingMedia()],
        ];
    }

    // Every row the slider's render is drawn from drops the tag, whichever of the three events wrote it - the campaign and its plates, and nothing else
    #[DataProvider('rowsTheCampaignIsDrawnFrom')]
    public function testEachRowTheRenderReadsDropsTheCampaignTag(object $entity): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->exactly(3))
            ->method('invalidateTags')
            ->with([CrowdfundingBlockCacheInvalidator::CACHE_TAG_CROWDFUNDING])
        ;

        $listener = new CrowdfundingCacheInvalidationListener(new CrowdfundingBlockCacheInvalidator($cache));
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $listener->postPersist(new PostPersistEventArgs($entity, $entityManager));
        $listener->postUpdate(new PostUpdateEventArgs($entity, $entityManager));
        $listener->preRemove(new PreRemoveEventArgs($entity, $entityManager));
    }

    /** @return list<array{0: object}> */
    public static function rowsNoCacheEntryHolds(): array
    {
        return [
            [new CrowdfundingCounterpart()],
            [new CrowdfundingContributor()],
        ];
    }

    // A row no cached render is drawn from leaves the cache alone, the listener being registered on every entity of the application - the tiers and the contributions are only ever printed by kinds declared "cacheable: false" and by the campaign page itself, neither of which has an entry to drop
    #[DataProvider('rowsNoCacheEntryHolds')]
    public function testARowNoCachedRenderReadsLeavesTheCacheAlone(object $entity): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        new CrowdfundingCacheInvalidationListener(new CrowdfundingBlockCacheInvalidator($cache))
            ->postUpdate(new PostUpdateEventArgs($entity, $this->createStub(EntityManagerInterface::class)))
        ;
    }
}
