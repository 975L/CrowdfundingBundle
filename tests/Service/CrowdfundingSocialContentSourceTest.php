<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Service;

use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingMedia;
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\CrowdfundingBundle\Service\CrowdfundingSocialContentSource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CrowdfundingSocialContentSourceTest extends TestCase
{
    private function createCrowdfunding(int $id, ?string $endDate = '+10 days', bool $hidden = false, ?string $beginDate = '-10 days'): Crowdfunding
    {
        $crowdfunding = new Crowdfunding()->setTitle('Album ' . $id)->setSlug('album-' . $id)->setDescription('<p>Le tome 2</p>')->setAuthorName('Laurent')->setHidden($hidden);
        $crowdfunding->setBeginDate(null === $beginDate ? null : new \DateTime($beginDate));
        $crowdfunding->setEndDate(null === $endDate ? null : new \DateTime($endDate));
        $crowdfunding->addMedia(new CrowdfundingMedia()->setKind(CrowdfundingMedia::KIND_COVER)->setName('medias/crowdfunding/crowdfundings/album-' . $id . '.webp'));
        new \ReflectionProperty(Crowdfunding::class, 'id')->setValue($crowdfunding, $id);

        return $crowdfunding;
    }

    /** @param list<Crowdfunding> $crowdfundings */
    private function createSource(array $crowdfundings, ?string $siteUrl = 'https://example.org'): CrowdfundingSocialContentSource
    {
        $repository = $this->createStub(CrowdfundingRepository::class);
        $repository->method('findAllSorted')->willReturn($crowdfundings);
        $repository->method('find')->willReturn($crowdfundings[0] ?? null);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static fn (string $route, array $parameters): string => '/crowdfunding/' . $parameters['slug']);

        $siteUrlResolver = $this->createStub(SiteUrlResolver::class);
        $siteUrlResolver->method('siteUrl')->willReturn($siteUrl);

        return new CrowdfundingSocialContentSource($repository, $urlGenerator, $siteUrlResolver, '/var/www/site');
    }

    public function testTheNextRunningCampaignIsHandedOverWithItsCover(): void
    {
        $content = $this->createSource([$this->createCrowdfunding(1), $this->createCrowdfunding(2)])->getNextContent(['1']);

        $this->assertSame('2', $content?->sourceId);
        $this->assertSame('https://example.org/crowdfunding/album-2', $content->url);
        $this->assertSame('/var/www/site/public/medias/crowdfunding/crowdfundings/album-2.webp', $content->imagePath);
        $this->assertSame(['description' => 'Le tome 2', 'author' => 'Laurent'], $content->variables);
    }

    // A finished campaign can no longer take what a post would call for
    public function testAnEndedCampaignIsNeverOffered(): void
    {
        $this->assertNull($this->createSource([$this->createCrowdfunding(1, '-1 day')])->getNextContent([]));
        $this->assertSame('1', $this->createSource([$this->createCrowdfunding(1, 'today')])->getNextContent([])?->sourceId);
    }

    // The basket refuses a campaign not open yet: a post calling for contributions would be ahead of it
    public function testACampaignNotOpenYetIsNeverOffered(): void
    {
        $this->assertNull($this->createSource([$this->createCrowdfunding(1, beginDate: '+1 day')])->getNextContent([]));
        $this->assertSame('1', $this->createSource([$this->createCrowdfunding(1, beginDate: 'today')])->getNextContent([])?->sourceId);
    }

    // A campaign left without dates is a draft, not something to publish every month
    public function testACampaignWithoutDatesIsNeverOffered(): void
    {
        $this->assertNull($this->createSource([$this->createCrowdfunding(1, endDate: null)])->getNextContent([]));
        $this->assertNull($this->createSource([$this->createCrowdfunding(1, beginDate: null)])->getNextContent([]));
    }

    public function testNothingIsHandedOverWhileTheSiteUrlIsUnset(): void
    {
        $this->assertNull($this->createSource([$this->createCrowdfunding(1)], null)->getNextContent([]));
    }

    public function testACampaignIsRecalledEveryMonth(): void
    {
        $this->assertSame(30, $this->createSource([])->getRepeatAfterDays());
    }

    public function testACampaignHiddenSinceIsNotReadAgain(): void
    {
        $this->assertSame('1', $this->createSource([$this->createCrowdfunding(1)])->getContent('1')?->sourceId);
        $this->assertNull($this->createSource([$this->createCrowdfunding(1, hidden: true)])->getContent('1'));
    }
}
