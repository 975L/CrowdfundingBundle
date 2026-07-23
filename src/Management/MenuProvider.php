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
            'translation_domain' => 'shop',
        ];
    }

    public function getMenus(): array
    {
        return [
            'crowdfunding' => [
                'controller' => CrowdfundingCrudController::class,
                'label' => 'label.crowdfundings',
                'translation_domain' => 'shop',
                'icon' => 'fas fa-money-bill',
            ],
        ];
    }

    public function getLinks(): array
    {
        return [
            'crowdfunding' => [
                'label' => 'label.crowdfundings',
                'name' => 'crowdfunding_index',
                'translation_domain' => 'shop',
                'icon' => '',
            ],
        ];
    }
}
