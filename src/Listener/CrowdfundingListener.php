<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Listener;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Listener\Traits\UserTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

// Places, dates and signs a campaign when it is written and when an editor really changes it - a contribution dating it (see CrowdfundingBasketItemProvider) changing neither its author nor anything else
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Crowdfunding::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: Crowdfunding::class)]
class CrowdfundingListener
{
    use UserTrait;

    // What a contribution writes on the campaign by itself, the amount it brought and when
    private const array COUNTERS = ['amountAchieved', 'modification'];

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // A new campaign is placed after the others, dated and signed
    public function prePersist(Crowdfunding $entity, PrePersistEventArgs $event): void
    {
        $this->placeLast($entity);
        // A date the row already carries is kept, so a frozen demo dataset or an import keeps its own
        if (null === $entity->getCreation()) {
            $entity->setCreation(new \DateTime());
        }
        if (null === $entity->getModification()) {
            $entity->setModification(new \DateTime());
        }
        $this->stampUser($entity, false);
    }

    // Doctrine only calls it on a real change set, and recomputes it afterwards, so the date and the author written here are saved with the edit
    public function preUpdate(Crowdfunding $entity, PreUpdateEventArgs $event): void
    {
        if (!$this->isEdited($event, self::COUNTERS)) {
            return;
        }

        $this->placeLast($entity);
        $entity->setModification(new \DateTime());
        $this->stampUser($entity, true);
    }

    // The column is nullable and the index page orders on it: five apart, so an admin can slip a campaign between two without renumbering the lot
    private function placeLast(Crowdfunding $entity): void
    {
        if (null !== $entity->getPosition()) {
            return;
        }

        $maxPosition = 0;
        foreach ($this->entityManager->getRepository(Crowdfunding::class)->findAll() as $crowdfunding) {
            $maxPosition = max($maxPosition, $crowdfunding->getPosition());
        }
        $entity->setPosition($maxPosition + 5);
    }
}
