<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Listener;

use c975L\CrowdfundingBundle\Entity\LotteryPrize;
use c975L\CrowdfundingBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

// Dates and signs a prize when it is written and when it is really edited
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: LotteryPrize::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: LotteryPrize::class)]
class LotteryPrizeListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
    ) {
    }

    // A new prize is dated and signed
    public function prePersist(LotteryPrize $entity, PrePersistEventArgs $event): void
    {
        // A date the row already carries is kept, so a frozen demo dataset or an import keeps its own
        if (null === $entity->getCreation()) {
            $entity->setCreation(new \DateTime());
        }
        if (null === $entity->getModification()) {
            $entity->setModification(new \DateTime());
        }
        $this->stampUser($entity, false);
    }

    // Doctrine only calls it on a real change set, and recomputes it afterwards
    public function preUpdate(LotteryPrize $entity, PreUpdateEventArgs $event): void
    {
        $entity->setModification(new \DateTime());
        $this->stampUser($entity, true);
    }
}
