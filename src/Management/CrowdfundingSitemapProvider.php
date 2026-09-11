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
use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\CrowdfundingBundle\Service\CrowdfundingServiceInterface;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslatedLocales;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslator;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Declares the crowdfunding index and each campaign page (public/sitemap-crowdfunding.xml) - CrowdfundingBundle's contribution to the site's sitemap-index.xml, written by ConfigBundle's SitemapWriter (c975l:sitemaps:create) with no command of its own to run. Lotteries are deliberately left out: their url is keyed by a 13-character identifier rather than a readable slug, and a drawn lottery is over - neither is worth submitting to a search engine
class CrowdfundingSitemapProvider implements SitemapProviderInterface
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly CrowdfundingServiceInterface $crowdfundingService,
        private readonly CrowdfundingTranslatedLocales $translatedLocales,
        private readonly CrowdfundingTranslator $crowdfundingTranslator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SiteLocales $siteLocales,
    ) {
    }

    public function getSitemapName(): string
    {
        return 'crowdfunding';
    }

    // A sitemap only accepts absolute urls, so there's nothing to declare before "site-url" is configured. Each page is declared once per language it really answers in, every entry carrying the whole group: a language's url is only ever crawled if the sitemap names it, and the group alone leaves the other languages undeclared (see SitePageSitemapProvider, whose reading this follows)
    public function getUrls(): array
    {
        $urlRoot = rtrim((string) $this->configService->get('site-url'), '/');
        if ('' === $urlRoot) {
            return [];
        }

        $urls = $this->localized([
            'lastmod' => date('Y-m-d'),
            'changefreq' => 'weekly',
            'priority' => 8,
        ], $urlRoot . '/crowdfunding', $this->alternates($urlRoot, 'crowdfunding_index', [], $this->translatedLocales->forIndex()));

        // Every language of every campaign read ahead, so naming each one's group below costs one query per language rather than one per campaign
        $campaigns = $this->crowdfundingService->findAllSorted();
        $this->crowdfundingTranslator->preloadEveryLanguage($campaigns);

        foreach ($campaigns as $crowdfunding) {
            $slug = $crowdfunding->getSlug();
            if (null === $slug || '' === $slug) {
                continue;
            }

            // A campaign still collecting changes often (news, counters); a finished one is an archive page that no longer moves
            $isRunning = null === $crowdfunding->getEndDate() || $crowdfunding->getEndDate() >= new \DateTime('today');

            // The group names only the languages the campaign's own title is written in, the url answering in every one of them regardless (see CrowdfundingTranslator::translatedLocales): naming the others would hand a search engine the writing language's words under another language's address
            $urls = [...$urls, ...$this->localized([
                'lastmod' => ($crowdfunding->getModification() ?? $crowdfunding->getCreation())?->format('Y-m-d') ?? date('Y-m-d'),
                'changefreq' => $isRunning ? 'daily' : 'yearly',
                'priority' => $isRunning ? 8 : 4,
            ], $urlRoot . '/crowdfunding/' . $slug, $this->alternates($urlRoot, 'crowdfunding_display', ['slug' => $slug], $this->crowdfundingTranslator->translatedLocales($crowdfunding)))];
        }

        return $urls;
    }

    // The same page once per language, each entry carrying the whole group. The writing language comes first (see SiteLocales::all()), so the entry a single-language site has always had stays byte for byte the first one
    /**
     * @param array<string, mixed>  $url
     * @param array<string, string> $alternates
     *
     * @return list<array<string, mixed>>
     */
    private function localized(array $url, string $canonical, array $alternates): array
    {
        return array_map(
            static fn (string $loc): array => ['loc' => $loc, ...$url, 'alternates' => $alternates],
            [] === $alternates ? [$canonical] : array_values($alternates),
        );
    }

    // One absolute url per language the page really answers in, hreflang => url - empty for a single language, a group naming itself alone saying nothing, and asked of the router rather than of LocalizedUrlGenerator::path(), the sitemap being written from a cron command with no request
    /**
     * @param array<string, mixed> $parameters
     * @param list<string>         $locales
     *
     * @return array<string, string>
     */
    private function alternates(string $urlRoot, string $route, array $parameters, array $locales): array
    {
        $default = $this->siteLocales->getDefaultLocale();
        if (\count($locales) < 2 || !\in_array($default, $locales, true)) {
            return [];
        }

        $alternates = [];
        foreach ($locales as $locale) {
            try {
                $alternates[$locale] = $urlRoot . ($locale === $default
                    ? $this->urlGenerator->generate($route, $parameters)
                    : $this->urlGenerator->generate($route . LocalizedUrlGenerator::LOCALIZED_SUFFIX, $parameters + ['_locale' => $locale]));
            } catch (RoutingExceptionInterface) {
                // No localised twin, or none answering for these parameters: there is no group to declare rather than a sitemap that cannot be written at all
                return [];
            }
        }

        return $alternates;
    }
}
