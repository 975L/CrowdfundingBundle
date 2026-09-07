<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Listener;

use c975L\CrowdfundingBundle\Entity\CrowdfundingMedia;
use c975L\CrowdfundingBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: CrowdfundingMedia::class)]
class CrowdfundingMediaListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function preFlush(CrowdfundingMedia $entity, PreFlushEventArgs $event): void
    {
        if (null === $entity->getPosition()) {
            $maxPosition = 0;
            foreach ($entity->getCrowdfunding()->getMedias() as $media) {
                $maxPosition = max($maxPosition, $media->getPosition());
            }
            $entity->setPosition($maxPosition + 5);
        }
        $this->setUser($entity);
    }
}
