<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Listener;

use c975L\CrowdfundingBundle\Entity\CrowdfundingVideo;
use c975L\CrowdfundingBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

// Signs a campaign's video on a write or a real edit only, never on a flush that leaves it unchanged
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: CrowdfundingVideo::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: CrowdfundingVideo::class)]
class CrowdfundingVideoListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
    ) {
    }

    // A new video is signed by the signed-in account
    public function prePersist(CrowdfundingVideo $entity, PrePersistEventArgs $event): void
    {
        $this->stampUser($entity, false);
    }

    // An edited video keeps the author it was given
    public function preUpdate(CrowdfundingVideo $entity, PreUpdateEventArgs $event): void
    {
        $this->stampUser($entity, false);
    }
}
