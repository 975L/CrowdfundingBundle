<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Twig\Extension;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Controller\Management\CrowdfundingCrudController;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

// Sends an editor from a section of the campaign page to the very field that section is written in. What a composed block gets for free through CrowdfundingBlockEditUrlProvider, the page's own hardcoded sections had nothing for: they are not blocks, so the "Edit this block" overlay had nothing to hang on and the campaign's story, its use of the funds and its author were only reachable by hunting down the right field on a form holding dozens
class CrowdfundingEditExtension
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly ConfigServiceInterface $configService,
        private readonly Security $security,
    ) {
    }

    // The campaign's edit screen, opened on one named field: UiBundle's field-focus.js reads "focusField", shows the tab that field sits in, scrolls to it and puts the caret in it. Answers null to anyone but an editor, so the URL is never written into a page a visitor reads
    #[AsTwigFunction('crowdfunding_edit_url')]
    public function getEditUrl(?Crowdfunding $crowdfunding, string $field): ?string
    {
        if (null === $crowdfunding || !$this->security->isGranted((string) $this->configService->get('site-role-editor'))) {
            return null;
        }

        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController(CrowdfundingCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($crowdfunding->getId())
            ->set('focusField', $field)
            ->generateUrl();
    }
}
