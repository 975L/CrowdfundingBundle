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
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

// Places and signs a campaign's picture on a write or a real edit only, never on a flush that leaves it unchanged
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: CrowdfundingMedia::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: CrowdfundingMedia::class)]
class CrowdfundingMediaListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
    ) {
    }

    // A new picture goes after its campaign's last one, signed by the signed-in account
    public function prePersist(CrowdfundingMedia $entity, PrePersistEventArgs $event): void
    {
        $this->placeAndSign($entity);
    }

    // A picture whose place was cleared goes back after the others
    public function preUpdate(CrowdfundingMedia $entity, PreUpdateEventArgs $event): void
    {
        $this->placeAndSign($entity);
    }

    // Position after the campaign's other pictures, author kept once given
    private function placeAndSign(CrowdfundingMedia $entity): void
    {
        if (null === $entity->getPosition()) {
            $maxPosition = 0;
            foreach ($entity->getCrowdfunding()?->getMedias() ?? [] as $media) {
                $maxPosition = max($maxPosition, (int) $media->getPosition());
            }
            $entity->setPosition($maxPosition + 5);
        }

        $this->stampUser($entity, false);
    }
}
