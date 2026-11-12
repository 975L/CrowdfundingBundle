<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Listener;

use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpartMedia;
use c975L\CrowdfundingBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\SluggerInterface;

// Names, dates and signs a counterpart when it is written and when an editor really changes it - a contribution counting its run (see CrowdfundingBasketItemProvider::countOrdered()) changing neither its author nor anything else
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: CrowdfundingCounterpart::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: CrowdfundingCounterpart::class)]
class CrowdfundingCounterpartListener
{
    use UserTrait;

    // What a contribution writes on the counterpart by itself, the run it counted and when
    private const array COUNTERS = ['orderedQuantity', 'modification'];

    public function __construct(
        private readonly Security $security,
        private readonly SluggerInterface $slugger,
    ) {
    }

    // A new counterpart gets its media placeholder, its slug, its dates and its author
    public function prePersist(CrowdfundingCounterpart $entity, PrePersistEventArgs $event): void
    {
        // Needs to add empty placeholder and a add contents aftewards because the owner is not yet persisted
        if (null === $entity->getMedia()) {
            $crowdfundingCounterpartMedia = new CrowdfundingCounterpartMedia();
            $crowdfundingCounterpartMedia->setUpdatedAt(new \DateTimeImmutable());
            $crowdfundingCounterpartMedia->setCrowdfundingCounterpart($entity);
            $entity->setMedia($crowdfundingCounterpartMedia);
        }
        $entity->setSlug($this->slugger->slug((string) $entity->getTitle())->lower());
        // A date the row already carries is kept, so a frozen demo dataset or an import keeps its own
        if (null === $entity->getCreation()) {
            $entity->setCreation(new \DateTime());
        }
        if (null === $entity->getModification()) {
            $entity->setModification(new \DateTime());
        }
        $this->stampUser($entity, false);
    }

    // Doctrine recomputes the change set after this, so the slug, the date and the author written here are saved with the edit
    public function preUpdate(CrowdfundingCounterpart $entity, PreUpdateEventArgs $event): void
    {
        if (!$this->isEdited($event, self::COUNTERS)) {
            return;
        }

        $entity->setSlug($this->slugger->slug((string) $entity->getTitle())->lower());
        $entity->setModification(new \DateTime());
        $this->stampUser($entity, true);
    }
}
