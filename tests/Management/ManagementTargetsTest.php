<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\ConfigBundle\Test\ManagementTargetsTestCase;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Management\CrowdfundingGuidedProjectProvider;
use c975L\CrowdfundingBundle\Management\LinkableRouteProvider;
use c975L\CrowdfundingBundle\Management\MenuProvider;
use c975L\CrowdfundingBundle\Service\CrowdfundingServiceInterface;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslatedLocales;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;

// Every CRUD controller and route this bundle's management providers name, checked against what its controllers actually declare - see ConfigBundle's ManagementTargetsTestCase
class ManagementTargetsTest extends ManagementTargetsTestCase
{
    protected function managementProviders(): iterable
    {
        return [
            new MenuProvider($this->createStub(ConfigServiceInterface::class)),
            new LinkableRouteProvider($this->crowdfundingService(), $this->createStub(TranslatorInterface::class), new CrowdfundingTranslatedLocales(new SiteLocales(['fr'], 'fr'), $this->createStub(CrowdfundingTranslator::class))),
            // The socle's own recorder rather than a bare stub, so the controller each parcours opens on is read back and checked (see ManagementTargetsTestCase)
            new CrowdfundingGuidedProjectProvider($this->adminUrlGenerator(), $this->createStub(ConfigServiceInterface::class)),
        ];
    }

    // One campaign is enough to have the route its entries name checked too - an empty list would leave the index as the only linkable target
    private function crowdfundingService(): CrowdfundingServiceInterface
    {
        $crowdfundingService = $this->createStub(CrowdfundingServiceInterface::class);
        $crowdfundingService->method('findAllSorted')->willReturn([new Crowdfunding()->setSlug('sauver-les-chats')->setTitle('Sauver les chats')]);

        return $crowdfundingService;
    }

    // The public campaign pages on top of ConfigBundle's own screens, the menu link pointing at the index of the first
    #[\Override]
    protected function controllerDirectories(): array
    {
        return [...parent::controllerDirectories(), __DIR__ . '/../../src/Controller', __DIR__ . '/../../src/Controller/Management'];
    }
}
