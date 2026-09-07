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
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Management\CrowdfundingSitemapProvider;
use c975L\CrowdfundingBundle\Service\CrowdfundingServiceInterface;
use PHPUnit\Framework\TestCase;

class CrowdfundingSitemapProviderTest extends TestCase
{
    // setSlug()/setModification() don't accept null, but both properties are nullable and a row can perfectly well hold none - written straight through reflection to reproduce that
    private function createCrowdfunding(?string $slug, ?string $modification = '2026-06-08', ?string $endDate = null): Crowdfunding
    {
        $crowdfunding = new Crowdfunding();
        new \ReflectionProperty(Crowdfunding::class, 'slug')->setValue($crowdfunding, $slug);
        new \ReflectionProperty(Crowdfunding::class, 'modification')->setValue($crowdfunding, null === $modification ? null : new \DateTime($modification));
        $crowdfunding->setEndDate(null === $endDate ? null : new \DateTime($endDate));

        return $crowdfunding;
    }

    private function createProvider(?string $siteUrl, array $crowdfundings = []): CrowdfundingSitemapProvider
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        $crowdfundingService = $this->createStub(CrowdfundingServiceInterface::class);
        $crowdfundingService->method('findAllSorted')->willReturn($crowdfundings);

        return new CrowdfundingSitemapProvider($configService, $crowdfundingService);
    }

    public function testGetSitemapNameReturnsCrowdfunding(): void
    {
        $this->assertSame('crowdfunding', $this->createProvider('https://example.com')->getSitemapName());
    }

    public function testGetUrlsReturnsEmptyArrayWithoutASiteUrl(): void
    {
        $this->assertSame([], $this->createProvider(null, [$this->createCrowdfunding('un-projet')])->getUrls());
    }

    public function testGetUrlsDeclaresTheIndexEvenWithNoCampaign(): void
    {
        $urls = $this->createProvider('https://example.com/')->getUrls();

        $this->assertCount(1, $urls);
        $this->assertSame('https://example.com/crowdfunding', $urls[0]['loc']);
    }

    public function testGetUrlsDeclaresEachCampaign(): void
    {
        $provider = $this->createProvider('https://example.com', [
            $this->createCrowdfunding('un-projet'),
            $this->createCrowdfunding('un-autre-projet'),
        ]);

        $urls = $provider->getUrls();

        $this->assertCount(3, $urls);
        $this->assertSame('https://example.com/crowdfunding/un-projet', $urls[1]['loc']);
        $this->assertSame('2026-06-08', $urls[1]['lastmod']);
        $this->assertSame('https://example.com/crowdfunding/un-autre-projet', $urls[2]['loc']);
    }

    // A campaign with no slug has no url to point at - skipped rather than declaring "/crowdfunding/"
    public function testGetUrlsSkipsACampaignWithoutASlug(): void
    {
        $provider = $this->createProvider('https://example.com', [$this->createCrowdfunding(null), $this->createCrowdfunding('')]);

        $this->assertCount(1, $provider->getUrls());
    }

    // A finished campaign is an archive page that no longer moves, unlike one still collecting
    public function testGetUrlsRatesAFinishedCampaignLowerThanARunningOne(): void
    {
        $provider = $this->createProvider('https://example.com', [
            $this->createCrowdfunding('en-cours', endDate: '2099-12-31'),
            $this->createCrowdfunding('termine', endDate: '2020-01-01'),
        ]);

        $urls = $provider->getUrls();

        $this->assertSame(['daily', 8], [$urls[1]['changefreq'], $urls[1]['priority']]);
        $this->assertSame(['yearly', 4], [$urls[2]['changefreq'], $urls[2]['priority']]);
    }

    // A campaign never edited since creation is dated by its creation instead
    public function testGetUrlsFallsBackToTheCreationDate(): void
    {
        $crowdfunding = $this->createCrowdfunding('un-projet', modification: null);
        $crowdfunding->setCreation(new \DateTime('2026-02-01'));

        $this->assertSame('2026-02-01', $this->createProvider('https://example.com', [$crowdfunding])->getUrls()[1]['lastmod']);
    }

    // Every url carries the four keys SitemapWriter expects
    public function testGetUrlsReturnsCompleteEntries(): void
    {
        $provider = $this->createProvider('https://example.com', [$this->createCrowdfunding('un-projet')]);

        foreach ($provider->getUrls() as $url) {
            $this->assertSame(['loc', 'lastmod', 'changefreq', 'priority'], array_keys($url));
            $this->assertIsInt($url['priority']);
        }
    }
}
