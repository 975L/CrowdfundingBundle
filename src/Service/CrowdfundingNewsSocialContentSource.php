<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\CrowdfundingBundle\Repository\CrowdfundingNewsRepository;
use c975L\UiBundle\Contract\BrowsableSocialContentSourceInterface;
use c975L\UiBundle\Contract\SocialContentSourceInterface;
use c975L\UiBundle\Model\SocialContent;

// Hands SocialBundle's publication the news of the running campaigns, most recent first - a news has no page, image nor visibility of its own, borrowing its campaign's as CrowdfundingSocialContentSource hands it and linking to itself on that page
class CrowdfundingNewsSocialContentSource implements BrowsableSocialContentSourceInterface, SocialContentSourceInterface
{
    // A news is news for a month: past that, posting it would announce what the followers already lived through
    private const int FRESH_DAYS = 30;

    public function __construct(
        private readonly CrowdfundingNewsRepository $newsRepository,
        private readonly CrowdfundingSocialContentSource $campaignSource,
    ) {
    }

    public function getSourceType(): string
    {
        return 'crowdfunding_news';
    }

    // Never: a news told once is told
    public function getRepeatAfterDays(): ?int
    {
        return null;
    }

    // The freshest news still free, as the browse screen would offer it first
    public function getNextContent(array $excludedIds): ?SocialContent
    {
        return $this->findContents($excludedIds, [], 1)[0] ?? null;
    }

    // The fresh news of the running campaigns still free, the most recent first - their campaign, joined and filtered by the query, is not read again. News have no groups, so the scopes are ignored
    public function findContents(array $excludedIds, array $scopeIds, int $limit): array
    {
        $fresh = new \DateTime(sprintf('-%d days', self::FRESH_DAYS))->setTime(0, 0);
        $contents = [];
        foreach ($this->newsRepository->findFreshLatest($excludedIds, $fresh, $limit) as $news) {
            $crowdfunding = $news->getCrowdfunding();
            $contents[] = null === $crowdfunding ? null : $this->toContent($news, $this->campaignSource->contentOf($crowdfunding));
        }

        return array_values(array_filter($contents));
    }

    // Never a group: news are not split into any
    public function getContentScope(string $sourceId): ?string
    {
        return null;
    }

    // Null for a news removed since its post was prepared, or whose campaign was taken off the site or ended
    public function getContent(string $sourceId): ?SocialContent
    {
        $news = $this->newsRepository->find((int) $sourceId);
        $campaignId = $news?->getCrowdfunding()?->getId();

        return $news instanceof CrowdfundingNews && null !== $campaignId ? $this->toContent($news, $this->campaignSource->getContent((string) $campaignId)) : null;
    }

    // The news on its campaign's content, null when the campaign has none to give
    private function toContent(CrowdfundingNews $news, ?SocialContent $campaign): ?SocialContent
    {
        if (null === $campaign) {
            return null;
        }

        return new SocialContent(
            sourceId: (string) $news->getId(),
            title: (string) $news->getTitle(),
            // The news itself on the campaign's page, where each one carries its own anchor
            url: $campaign->url . '#news-' . $news->getId(),
            imagePath: $campaign->imagePath,
            imageUrl: $campaign->imageUrl,
            imageAlt: $campaign->imageAlt,
            variables: array_filter([
                'description' => trim(html_entity_decode(strip_tags((string) $news->getContent()))),
                'campaign' => $campaign->title,
            ]),
        );
    }
}
