<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Listener;

use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpartMedia;
use c975L\CrowdfundingBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

// Signs a counterpart's picture on a write or a real edit only, never on a flush that leaves it unchanged
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: CrowdfundingCounterpartMedia::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: CrowdfundingCounterpartMedia::class)]
class CrowdfundingCounterpartMediaListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
    ) {
    }

    // A new picture is signed by the signed-in account
    public function prePersist(CrowdfundingCounterpartMedia $entity, PrePersistEventArgs $event): void
    {
        $this->stampUser($entity, false);
    }

    // An edited picture keeps the author it was given
    public function preUpdate(CrowdfundingCounterpartMedia $entity, PreUpdateEventArgs $event): void
    {
        $this->stampUser($entity, false);
    }
}
