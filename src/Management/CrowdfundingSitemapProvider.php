<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Management;

use c975L\ConfigBundle\Management\SitemapProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Service\CrowdfundingServiceInterface;

// Declares the crowdfunding index and each campaign page (public/sitemap-crowdfunding.xml) - CrowdfundingBundle's contribution to the site's sitemap-index.xml, written by ConfigBundle's SitemapWriter (c975l:sitemaps:create) with no command of its own to run. Lotteries are deliberately left out: their url is keyed by a 13-character identifier rather than a readable slug, and a drawn lottery is over - neither is worth submitting to a search engine
class CrowdfundingSitemapProvider implements SitemapProviderInterface
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly CrowdfundingServiceInterface $crowdfundingService,
    ) {
    }

    public function getSitemapName(): string
    {
        return 'crowdfunding';
    }

    // A sitemap only accepts absolute urls, so there's nothing to declare before "site-url" is configured
    public function getUrls(): array
    {
        $urlRoot = rtrim((string) $this->configService->get('site-url'), '/');
        if ('' === $urlRoot) {
            return [];
        }

        $urls = [[
            'loc' => $urlRoot . '/crowdfunding',
            'lastmod' => date('Y-m-d'),
            'changefreq' => 'weekly',
            'priority' => 8,
        ]];

        foreach ($this->crowdfundingService->findAllSorted() as $crowdfunding) {
            $slug = $crowdfunding->getSlug();
            if (null === $slug || '' === $slug) {
                continue;
            }

            // A campaign still collecting changes often (news, counters); a finished one is an archive page that no longer moves
            $isRunning = null === $crowdfunding->getEndDate() || $crowdfunding->getEndDate() >= new \DateTime('today');

            $urls[] = [
                'loc' => $urlRoot . '/crowdfunding/' . $slug,
                'lastmod' => ($crowdfunding->getModification() ?? $crowdfunding->getCreation())?->format('Y-m-d') ?? date('Y-m-d'),
                'changefreq' => $isRunning ? 'daily' : 'yearly',
                'priority' => $isRunning ? 8 : 4,
            ];
        }

        return $urls;
    }
}
