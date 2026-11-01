<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Management\AlertProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Controller\Management\CrowdfundingCrudController;
use c975L\CrowdfundingBundle\Repository\CrowdfundingCounterpartRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Says on the dashboard what two simultaneous payments can do to a limited run: a contributor paid for a counterpart the run no longer holds, and only the shop can decide what they are given instead (see CrowdfundingBasketItemProvider::countOrdered())
class CrowdfundingAlertProvider implements AlertProviderInterface
{
    public function __construct(
        private readonly CrowdfundingCounterpartRepository $counterpartRepository,
        private readonly ConfigServiceInterface $configService,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // One alert while a run stands past its cap, gone once the shop has raised the cap
    public function getAlerts(): array
    {
        $titles = $this->counterpartRepository->findOversoldTitles();
        if ([] === $titles) {
            return [];
        }

        return [[
            'label' => $this->translator->trans('label.counterparts_oversold', [], 'crowdfunding'),
            'description' => $this->translator->trans('description.counterparts_oversold', ['%count%' => \count($titles), '%titles%' => implode(', ', $titles)], 'crowdfunding'),
            'severity' => Config::SEVERITY_WARNING,
            'url' => $this->adminUrlGenerator
                ->unsetAll()
                ->setController(CrowdfundingCrudController::class)
                ->setAction(Action::INDEX)
                ->generateUrl(),
            'role' => (string) $this->configService->get('site-role-admin'),
        ]];
    }
}
