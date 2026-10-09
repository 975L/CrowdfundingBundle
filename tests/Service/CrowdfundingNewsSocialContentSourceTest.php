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
use c975L\CrowdfundingBundle\Service\CrowdfundingNewsSocialContentSource;
use c975L\CrowdfundingBundle\Service\CrowdfundingSocialContentSource;
use c975L\UiBundle\Model\SocialContent;
use PHPUnit\Framework\TestCase;

class CrowdfundingNewsSocialContentSourceTest extends TestCase
{
    private Crowdfunding $crowdfunding;

    /** @var list<string> */
    private array $excludedIds = [];

    private ?\DateTimeInterface $since = null;

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
        $newsRepository = $this->createStub(CrowdfundingNewsRepository::class);
        $newsRepository->method('find')->willReturn($found);

        // What the query keeps, as it keeps it: free, published since the given day, the most recent first, up to the limit
        $newsRepository->method('findFreshLatest')->willReturnCallback(function (array $excludedIds, \DateTimeInterface $since, int $limit): array {
            $this->excludedIds = $excludedIds;
            $this->since = $since;
            $news = array_filter($this->crowdfunding->getNews()->getValues(), static fn (CrowdfundingNews $news): bool => !\in_array((string) $news->getId(), $excludedIds, true) && $news->getPublishedDate() >= $since);
            usort($news, static fn (CrowdfundingNews $a, CrowdfundingNews $b): int => $b->getPublishedDate() <=> $a->getPublishedDate());

            return \array_slice($news, 0, $limit);
        });

        $campaign = $campaignRunning ? new SocialContent('9', 'Album', 'https://example.org/crowdfunding/album', '/var/www/site/public/cover.webp', 'https://example.org/cover.webp') : null;
        $campaignSource = $this->createStub(CrowdfundingSocialContentSource::class);
        $campaignSource->method('getContent')->willReturn($campaign);
        $campaignSource->method('contentOf')->willReturn($campaign);

        return new CrowdfundingNewsSocialContentSource($newsRepository, $campaignSource);
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

    // What a post's news is chosen among: the fresh ones still free, from the first day of the window on - news having no groups, the scopes given change nothing
    public function testTheContentsToChooseAreTheFreeFreshNews(): void
    {
        $this->addNews(2, '-2 days');
        $this->addNews(1, '-10 days');

        $contents = $this->createSource()->findContents(['7'], ['3'], 48);

        $this->assertSame(['2', '1'], array_map(static fn (SocialContent $content): string => $content->sourceId, $contents));
        $this->assertSame(['7'], $this->excludedIds);
        $this->assertSame(new \DateTime('-30 days')->format('Y-m-d 00:00:00'), $this->since?->format('Y-m-d H:i:s'));
        $this->assertSame([], $this->createSource(campaignRunning: false)->findContents([], [], 48));
    }

    // A news belongs to no group, so a post's news is never drawn again from one
    public function testANewsHasNoScope(): void
    {
        $this->assertNull($this->createSource()->getContentScope('1'));
    }
}
