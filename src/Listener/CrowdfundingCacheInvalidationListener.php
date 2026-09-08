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
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpartMedia;
use c975L\CrowdfundingBundle\Entity\CrowdfundingMedia;
use c975L\CrowdfundingBundle\Service\CrowdfundingBlockCacheInvalidator;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

// Drops the cached renders of this bundle's blocks whenever the campaign they read changes - the campaign itself, its medias, a counterpart or any of its own, and a contribution raising both the amount achieved and the ordered quantities. postPersist as much as postUpdate, a brand new counterpart on a cached campaign being an INSERT
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class CrowdfundingCacheInvalidationListener
{
    public function __construct(private readonly CrowdfundingBlockCacheInvalidator $invalidator)
    {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidate($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidate($args->getObject());
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->invalidate($args->getObject());
    }

    private function invalidate(object $entity): void
    {
        if (
            $entity instanceof Crowdfunding
            || $entity instanceof CrowdfundingMedia
            || $entity instanceof CrowdfundingCounterpart
            || $entity instanceof CrowdfundingCounterpartMedia
            || $entity instanceof CrowdfundingContributor
        ) {
            $this->invalidator->invalidateCrowdfunding();
        }
    }
}
