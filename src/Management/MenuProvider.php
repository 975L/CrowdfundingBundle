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
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Controller\Management\CrowdfundingCrudController;

class MenuProvider implements MenuProviderInterface
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getMenuSection(): array
    {
        return [
            'label' => 'label.crowdfundings',
            'translation_domain' => 'crowdfunding',
            'icon' => 'fas fa-hand-holding-heart',
        ];
    }

    public function getMenus(): array
    {
        return [
            'crowdfunding' => [
                'controller' => CrowdfundingCrudController::class,
                'label' => 'label.campaigns',
                'narration' => 'narration.crowdfundings',
                'translation_domain' => 'crowdfunding',
                'icon' => 'fas fa-money-bill',
                // The very text the campaigns screen opens on (see crowdfunding_crud_index.html.twig), reused as-is for the onboarding tour rather than written again for it
                'description' => 'label.info_crowdfunding',
                // The bar CrowdfundingCrudController sets on its own index - named rather than left to the entry's own default, which is site-role-admin and would keep the screen out of the menu of the editors that may now reach it
                'role' => $this->configService->get('site-role-editor'),
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
                'icon' => 'fas fa-hand-holding-dollar',
                // Leaves the admin for the site's own public page, so it opens in a new tab and joins the "Liens" section rather than this bundle's own entries (see MenuBuilder::getMenuItems())
                'target' => '_blank',
                // What the public index announces itself as (see crowdfunding/index.html.twig), rather than a sentence written for the tour alone
                'description' => 'text.crowdfundings',
            ],
        ];
    }
}
