<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Listener;

use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

// The dates of a news, wherever it is written: the campaign page, where its author posts it, and the campaign's edit form, where an editor corrects it. They used to be stamped by CrowdfundingService::addNews(), which the back office never goes through - a news added there would have reached a table declaring all three columns not-null
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: CrowdfundingNews::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: CrowdfundingNews::class)]
class CrowdfundingNewsListener
{
    // "preUpdate" and not "preFlush": Doctrine calls an entity's preFlush listener for the whole identity map, so every news the campaign page had merely read was stamped again on any later flush of the same request. UnitOfWork recomputes the change set itself right after calling this, so nothing has to be recomputed here
    public function preUpdate(CrowdfundingNews $entity, PreUpdateEventArgs $event): void
    {
        $entity->setModification(new \DateTime());
    }

    // The publication date is only defaulted, never imposed: a news reaching here with one already set - a fixture, an import, a later screen offering the field - keeps it
    public function prePersist(CrowdfundingNews $entity, PrePersistEventArgs $event): void
    {
        $entity->setCreation(new \DateTime());

        // The column is not nullable and "preUpdate" never runs on an insert: without this, a news written in the back office would reach the table with no modification date at all
        $entity->setModification(new \DateTime());

        if (null === $entity->getPublishedDate()) {
            $entity->setPublishedDate(new \DateTime());
        }
    }
}
