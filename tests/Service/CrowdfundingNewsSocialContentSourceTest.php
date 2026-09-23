<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Service;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\CrowdfundingBundle\Repository\CrowdfundingNewsRepository;
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\CrowdfundingBundle\Service\CrowdfundingNewsSocialContentSource;
use c975L\CrowdfundingBundle\Service\CrowdfundingSocialContentSource;
use c975L\UiBundle\Model\SocialContent;
use PHPUnit\Framework\TestCase;

class CrowdfundingNewsSocialContentSourceTest extends TestCase
{
    private Crowdfunding $crowdfunding;

    protected function setUp(): void
    {
        $this->crowdfunding = new Crowdfunding()->setTitle('Album')->setSlug('album');
        new \ReflectionProperty(Crowdfunding::class, 'id')->setValue($this->crowdfunding, 9);
    }

    private function addNews(int $id, string $published): CrowdfundingNews
    {
        $news = new CrowdfundingNews()->setTitle('Nouvelle ' . $id)->setContent('<p>Les planches sont finies</p>')->setPublishedDate(new \DateTime($published)->setTime(0, 0))->setCrowdfunding($this->crowdfunding);
        new \ReflectionProperty(CrowdfundingNews::class, 'id')->setValue($news, $id);
        $this->crowdfunding->getNews()->add($news);

        return $news;
    }

    private function createSource(bool $campaignRunning = true, ?CrowdfundingNews $found = null): CrowdfundingNewsSocialContentSource
    {
        $repository = $this->createStub(CrowdfundingRepository::class);
        $repository->method('findAllSorted')->willReturn([$this->crowdfunding]);

        $newsRepository = $this->createStub(CrowdfundingNewsRepository::class);
        $newsRepository->method('find')->willReturn($found);

        $campaignSource = $this->createStub(CrowdfundingSocialContentSource::class);
        $campaignSource->method('getContent')->willReturn($campaignRunning ? new SocialContent('9', 'Album', 'https://example.org/crowdfunding/album', '/var/www/site/public/cover.webp', 'https://example.org/cover.webp') : null);

        return new CrowdfundingNewsSocialContentSource($repository, $newsRepository, $campaignSource);
    }

    // The latest news first, linked to itself on its campaign's page, with the campaign's cover
    public function testTheMostRecentNewsIsHandedOverOnItsCampaignsPage(): void
    {
        $this->addNews(1, '-10 days');
        $this->addNews(2, '-2 days');

        $content = $this->createSource()->getNextContent([]);

        $this->assertSame('2', $content?->sourceId);
        $this->assertSame('https://example.org/crowdfunding/album#news-2', $content->url);
        $this->assertSame('/var/www/site/public/cover.webp', $content->imagePath);
        $this->assertSame(['description' => 'Les planches sont finies', 'campaign' => 'Album'], $content->variables);
    }

    public function testANewsOlderThanAMonthIsNoLongerNews(): void
    {
        $this->addNews(1, '-40 days');

        $this->assertNull($this->createSource()->getNextContent([]));
    }

    // The window is counted in days, not in hours: a news of exactly thirty days is still on its last one
    public function testANewsOfExactlyAMonthIsStillNews(): void
    {
        $this->addNews(1, '-30 days');

        $this->assertSame('1', $this->createSource()->getNextContent([])?->sourceId);
    }

    public function testANewsOfACampaignOffTheSiteIsNotHandedOver(): void
    {
        $this->addNews(1, '-2 days');

        $this->assertNull($this->createSource(campaignRunning: false)->getNextContent([]));
    }

    public function testANewsIsReadAgainByItsId(): void
    {
        $news = $this->addNews(1, '-2 days');

        $this->assertSame('1', $this->createSource(found: $news)->getContent('1')?->sourceId);
        $this->assertNull($this->createSource()->getContent('1'));
    }
}
