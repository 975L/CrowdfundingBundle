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
use c975L\CrowdfundingBundle\Entity\CrowdfundingMedia;
use c975L\CrowdfundingBundle\Service\CrowdfundingBlockCacheInvalidator;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

// Drops the cached renders of this bundle's blocks whenever what they read changes - the campaign itself and its medias, which is all "crowdfunding_slider" draws and the only kind of this bundle whose render is cached (the two others are "cacheable: false", see config/services.yaml). A tier or a contribution is left out on purpose: no cache entry holds either. The writes are only noted, the tag dropped once the flush is over: dropped before the commit, a concurrent request would cache the old state again
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class CrowdfundingCacheInvalidationListener
{
    private bool $pending = false;

    public function __construct(private readonly CrowdfundingBlockCacheInvalidator $invalidator)
    {
    }

    // An inserted row is noted
    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->note($args->getObject());
    }

    // An updated row is noted
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->note($args->getObject());
    }

    // A removed row is noted
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->note($args->getObject());
    }

    // The tag dropped once, after the flush that wrote what it covers
    public function postFlush(PostFlushEventArgs $args): void
    {
        if (!$this->pending) {
            return;
        }

        $this->pending = false;
        $this->invalidator->invalidateCrowdfunding();
    }

    // Whether the row is one the cached render reads
    private function note(object $entity): void
    {
        if ($entity instanceof Crowdfunding || $entity instanceof CrowdfundingMedia) {
            $this->pending = true;
        }
    }
}
