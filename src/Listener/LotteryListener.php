<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Listener;

use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\ByteString;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: Lottery::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Lottery::class)]
class LotteryListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function preFlush(Lottery $entity, PreFlushEventArgs $event): void
    {
        $entity->setModification(new \DateTime());
        $this->setUser($entity);
    }

    public function prePersist(Lottery $entity, PrePersistEventArgs $event): void
    {
        // Generates a unique lottery number - Format: XXX-YYYY-ZZZ (3 letters, 4 letters/numbers, 4 letters/numbers)

        $prefix = strtoupper(ByteString::fromRandom(3, 'BCDFGHJKLMNPQRSTVWXYZ')->toString());
        $randomPart1 = strtoupper(bin2hex(random_bytes(2)));
        $randomPart2 = strtoupper(bin2hex(random_bytes(2)));

        $entity->setIdentifier($prefix . '-' . $randomPart1 . '-' . $randomPart2);
        $entity->setIsActive(true);
        $entity->setCreation(new \DateTime());
    }
}
