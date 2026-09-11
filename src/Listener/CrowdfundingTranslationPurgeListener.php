<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Listener;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\CrowdfundingBundle\Entity\LotteryPrize;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslator;
use c975L\UiBundle\Repository\TranslationRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

// Takes a row's translations away with the row, as SiteBundle's PageTranslationPurgeListener does for a page: they name their owner without a foreign key, so a campaign deleted for good - or a tier, a follow-up or a prize removed by orphanRemoval - would otherwise leave them to the next row landing on its id
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postRemove)]
class CrowdfundingTranslationPurgeListener
{
    // The rows being removed and what their translations are filed under, noted while each still has its id
    /** @var \WeakMap<object, array{string, int}> */
    private \WeakMap $pending;

    public function __construct(private readonly TranslationRepository $repository)
    {
        $this->pending = new \WeakMap();
    }

    // Doctrine hands a removed row's id back to null before postRemove is dispatched, so the id is read here while it still exists
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        $owner = $this->owner($entity);
        $id = null === $owner ? null : $entity->getId();
        if (null === $owner || !\is_int($id)) {
            return;
        }

        $this->pending[$entity] = [$owner, $id];
    }

    // Purged once the row is gone and inside the flush's own transaction, so a removal the database refuses keeps its translations
    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!isset($this->pending[$entity])) {
            return;
        }

        [$owner, $id] = $this->pending[$entity];
        unset($this->pending[$entity]);

        // A DQL delete rather than a remove(): a flush is already running, and nothing here needs hydrating
        $this->repository->deleteByOwner($owner, $id);
    }

    // What a row's translations are filed under, null for a row of another kind
    private function owner(object $entity): ?string
    {
        return match (true) {
            $entity instanceof Crowdfunding => CrowdfundingTranslator::OWNER_CAMPAIGN,
            $entity instanceof CrowdfundingCounterpart => CrowdfundingTranslator::OWNER_COUNTERPART,
            $entity instanceof CrowdfundingNews => CrowdfundingTranslator::OWNER_NEWS,
            $entity instanceof LotteryPrize => CrowdfundingTranslator::OWNER_PRIZE,
            default => null,
        };
    }
}
