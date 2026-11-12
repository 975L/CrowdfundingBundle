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
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\ByteString;

// Identifies, dates and signs a lottery when it is written, dates and signs it again when it is really edited
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Lottery::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: Lottery::class)]
class LotteryListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
    ) {
    }

    // Generates a unique lottery number - Format: XXX-YYYY-ZZZZ (3 letters, 4 letters/numbers, 4 letters/numbers)
    public function prePersist(Lottery $entity, PrePersistEventArgs $event): void
    {
        $prefix = strtoupper(ByteString::fromRandom(3, 'BCDFGHJKLMNPQRSTVWXYZ')->toString());
        $randomPart1 = strtoupper(bin2hex(random_bytes(2)));
        $randomPart2 = strtoupper(bin2hex(random_bytes(2)));

        $entity->setIdentifier($prefix . '-' . $randomPart1 . '-' . $randomPart2);
        $entity->setIsActive(true);
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
    public function preUpdate(Lottery $entity, PreUpdateEventArgs $event): void
    {
        $entity->setModification(new \DateTime());
        $this->stampUser($entity, true);
    }
}
