<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use Symfony\Component\Form\FormInterface;

interface CrowdfundingServiceInterface
{
    /**
     * Builds one of the bundle's own forms by name, bound to the given entity.
     *
     * @param string $name   the form's short name
     * @param mixed  $object the entity the form is bound to
     */
    public function createForm(string $name, $object): FormInterface;

    /**
     * @return Crowdfunding[] in no particular order
     */
    public function findAll();

    // Ordered by their admin-defined position
    /** @return list<Crowdfunding> */
    public function findAllSorted();

    public function findOneById(int $id): ?Crowdfunding;

    /**
     * Adds a news to a crowdfunding.
     */
    public function addNews(Crowdfunding $crowdfunding, CrowdfundingNews $news): void;
}
