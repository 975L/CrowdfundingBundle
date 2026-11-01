<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Management\CrowdfundingAlertProvider;
use c975L\CrowdfundingBundle\Repository\CrowdfundingCounterpartRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// A run sold past its cap is said on the dashboard, a customer having paid for what it no longer holds
class CrowdfundingAlertProviderTest extends TestCase
{
    public function testNothingOversoldRaisesNothing(): void
    {
        $this->assertSame([], $this->provider([])->getAlerts());
    }

    public function testAnOversoldRunIsAlerted(): void
    {
        $alerts = $this->provider(['Gold', 'Silver'])->getAlerts();

        $this->assertCount(1, $alerts);
        $this->assertSame('label.counterparts_oversold', $alerts[0]['label']);
        $this->assertSame('description.counterparts_oversold 2 Gold, Silver', $alerts[0]['description']);
        $this->assertSame(Config::SEVERITY_WARNING, $alerts[0]['severity']);
    }

    /** @param list<string> $oversold */
    private function provider(array $oversold): CrowdfundingAlertProvider
    {
        $repository = $this->createStub(CrowdfundingCounterpartRepository::class);
        $repository->method('findOversoldTitles')->willReturn($oversold);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters = []): string => trim($id . ' ' . implode(' ', $parameters)));

        $urlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $urlGenerator->method('unsetAll')->willReturnSelf();
        $urlGenerator->method('setController')->willReturnSelf();
        $urlGenerator->method('setAction')->willReturnSelf();
        $urlGenerator->method('generateUrl')->willReturn('/admin/crowdfunding');

        return new CrowdfundingAlertProvider($repository, $this->createStub(ConfigServiceInterface::class), $urlGenerator, $translator);
    }
}
