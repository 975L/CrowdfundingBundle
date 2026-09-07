<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Management;

use c975L\ConfigBundle\Management\MenuProviderInterface;
use c975L\CrowdfundingBundle\Controller\Management\CrowdfundingCrudController;

class MenuProvider implements MenuProviderInterface
{
    public function getMenuSection(): array
    {
        return [
            'label' => 'label.crowdfundings',
            'translation_domain' => 'crowdfunding',
        ];
    }

    public function getMenus(): array
    {
        return [
            'crowdfunding' => [
                'controller' => CrowdfundingCrudController::class,
                'label' => 'label.crowdfundings',
                'narration' => 'narration.crowdfundings',
                'translation_domain' => 'crowdfunding',
                'icon' => 'fas fa-money-bill',
                // The very text the campaigns screen opens on (see crowdfunding_crud_index.html.twig), reused as-is for the onboarding tour rather than written again for it
                'description' => 'label.info_crowdfunding',
                // No 'role': the whole CRUD sits behind site-role-admin (see CrowdfundingCrudController::configureActions), which is the key this entry already defaults to
            ],
        ];
    }

    public function getLinks(): array
    {
        return [
            'crowdfunding' => [
                'label' => 'label.crowdfundings',
                'narration' => 'narration.crowdfunding_index',
                'name' => 'crowdfunding_index',
                'translation_domain' => 'crowdfunding',
                'icon' => '',
                // What the public index announces itself as (see crowdfunding/index.html.twig), rather than a sentence written for the tour alone
                'description' => 'text.crowdfundings',
            ],
        ];
    }
}
