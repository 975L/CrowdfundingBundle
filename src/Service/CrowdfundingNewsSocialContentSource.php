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
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\UiBundle\Contract\SocialContentSourceInterface;
use c975L\UiBundle\Model\SocialContent;

// Hands SocialBundle's publication the news of the running campaigns, most recent first - a news has no page, image nor visibility of its own, borrowing its campaign's as CrowdfundingSocialContentSource hands it and linking to itself on that page
class CrowdfundingNewsSocialContentSource implements SocialContentSourceInterface
{
    // A news is news for a month: past that, posting it would announce what the followers already lived through
    private const int FRESH_DAYS = 30;

    public function __construct(
        private readonly CrowdfundingRepository $crowdfundingRepository,
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

    public function getNextContent(array $excludedIds): ?SocialContent
    {
        $fresh = new \DateTime(sprintf('-%d days', self::FRESH_DAYS))->setTime(0, 0);
        $candidates = [];
        foreach ($this->crowdfundingRepository->findAllSorted() as $crowdfunding) {
            foreach ($crowdfunding->getNews() as $news) {
                if (!\in_array((string) $news->getId(), $excludedIds, true) && $news->getPublishedDate() >= $fresh && $news->getPublishedDate() <= new \DateTime()) {
                    $candidates[] = $news;
                }
            }
        }
        usort($candidates, static fn (CrowdfundingNews $a, CrowdfundingNews $b): int => $b->getPublishedDate() <=> $a->getPublishedDate());

        foreach ($candidates as $news) {
            $content = $this->toContent($news);
            if (null !== $content) {
                return $content;
            }
        }

        return null;
    }

    // Null for a news removed since its post was prepared, or whose campaign was taken off the site or ended
    public function getContent(string $sourceId): ?SocialContent
    {
        $news = $this->newsRepository->find((int) $sourceId);

        return $news instanceof CrowdfundingNews ? $this->toContent($news) : null;
    }

    private function toContent(CrowdfundingNews $news): ?SocialContent
    {
        $campaignId = $news->getCrowdfunding()?->getId();
        $campaign = null === $campaignId ? null : $this->campaignSource->getContent((string) $campaignId);
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
