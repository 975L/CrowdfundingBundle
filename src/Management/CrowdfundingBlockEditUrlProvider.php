<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Management;

use c975L\CrowdfundingBundle\Controller\Management\CrowdfundingCrudController;
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\UiBundle\Contract\BlockEditUrlProviderInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Service\BlockFocusUrl;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// Resolves, for UiBundle's front-end "Edit this block" hover button, the EasyAdmin edit URL of the campaign owning a given Block - the campaign page then behaves as a site page's blocks do in SiteBundle. Discovered on its interface alone, no tag needed (see UiBundle's BlockEditUrlProviderPass)
class CrowdfundingBlockEditUrlProvider implements BlockEditUrlProviderInterface
{
    public function __construct(
        private readonly CrowdfundingRepository $crowdfundingRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
    ) {
    }

    public function getEditUrls(array $blocks): array
    {
        $blockIds = array_filter(array_map(static fn (Block $block): ?int => $block->getId(), $blocks));

        if ([] === $blockIds) {
            return [];
        }

        $urls = [];
        foreach ($this->crowdfundingRepository->findByBlockIds($blockIds) as $crowdfunding) {
            foreach ($crowdfunding->getBlocks() as $block) {
                if (\in_array($block->getId(), $blockIds, true)) {
                    $urls[$block->getId()] = BlockFocusUrl::build($this->adminUrlGenerator, CrowdfundingCrudController::class, $crowdfunding->getId(), $block);
                }
            }
        }

        return $urls;
    }
}
