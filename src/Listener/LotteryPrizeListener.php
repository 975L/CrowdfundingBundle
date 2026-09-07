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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: LotteryPrize::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: LotteryPrize::class)]
class LotteryPrizeListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function preFlush(LotteryPrize $entity, PreFlushEventArgs $event): void
    {
        $entity->setModification(new \DateTime());
        $this->setUser($entity);
    }

    public function prePersist(LotteryPrize $entity, PrePersistEventArgs $event): void
    {
        $entity->setCreation(new \DateTime());
    }
}
