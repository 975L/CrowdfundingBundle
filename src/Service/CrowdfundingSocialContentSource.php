<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\UiBundle\Contract\BrowsableSocialContentSourceInterface;
use c975L\UiBundle\Contract\SocialContentSourceInterface;
use c975L\UiBundle\Model\SocialContent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Hands SocialBundle's publication the campaigns still collecting, in the order the site lists them - a site without SocialBundle simply never asks. What went out where is SocialBundle's to record
class CrowdfundingSocialContentSource implements BrowsableSocialContentSourceInterface, SocialContentSourceInterface
{
    // A running campaign lives on reminders: a month between two is what keeps it in sight without wearing its followers out
    private const int REPEAT_AFTER_DAYS = 30;

    public function __construct(
        private readonly CrowdfundingRepository $crowdfundingRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SiteUrlResolver $siteUrlResolver,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function getSourceType(): string
    {
        return 'crowdfunding';
    }

    public function getRepeatAfterDays(): ?int
    {
        return self::REPEAT_AFTER_DAYS;
    }

    public function getNextContent(array $excludedIds): ?SocialContent
    {
        foreach ($this->crowdfundingRepository->findAllSorted() as $crowdfunding) {
            if (!\in_array((string) $crowdfunding->getId(), $excludedIds, true) && $this->isRunning($crowdfunding)) {
                return $this->contentOf($crowdfunding);
            }
        }

        return null;
    }

    // The campaigns open today and still free, in the order the site lists them - the one the next automatic post would take comes first. Campaigns have no groups, so the scopes are ignored
    public function findContents(array $excludedIds, array $scopeIds, int $limit): array
    {
        return array_values(array_filter(array_map($this->contentOf(...), $this->crowdfundingRepository->findRunningSorted($excludedIds, $limit))));
    }

    // Never a group: campaigns are not split into any
    public function getContentScope(string $sourceId): ?string
    {
        return null;
    }

    // Null for a campaign taken off the site, or ended, since its post was prepared
    public function getContent(string $sourceId): ?SocialContent
    {
        $crowdfunding = $this->crowdfundingRepository->find((int) $sourceId);

        return $crowdfunding instanceof Crowdfunding && !$crowdfunding->isHidden() && !$crowdfunding->isDeleted() && $this->isRunning($crowdfunding)
            ? $this->contentOf($crowdfunding)
            : null;
    }

    // A campaign not open yet has nothing to ask for, a finished one no longer has, and one without dates is not ready to be shown at all
    private function isRunning(Crowdfunding $crowdfunding): bool
    {
        $beginDate = $crowdfunding->getBeginDate();
        $endDate = $crowdfunding->getEndDate();
        if (null === $beginDate || null === $endDate) {
            return false;
        }

        $today = new \DateTime()->format('Ymd');

        return $beginDate->format('Ymd') <= $today && $endDate->format('Ymd') >= $today;
    }

    // The content of a campaign already loaded and known to be running - null while "site-url" is unset: the run happens in a console, with no request to take the host from
    public function contentOf(Crowdfunding $crowdfunding): ?SocialContent
    {
        $siteUrl = $this->siteUrlResolver->siteUrl();
        if (null === $siteUrl) {
            return null;
        }

        // The image the campaign's own page shares: its cover, its hero otherwise
        $image = $crowdfunding->getCovers()->first() ?: $crowdfunding->getHeroes()->first() ?: null;
        $name = $image?->getName();

        return new SocialContent(
            sourceId: (string) $crowdfunding->getId(),
            title: (string) $crowdfunding->getTitle(),
            url: $siteUrl . $this->urlGenerator->generate('crowdfunding_display', ['slug' => $crowdfunding->getSlug()]),
            imagePath: null === $name ? null : $this->projectDir . '/public/' . $name,
            imageUrl: null === $name ? null : $siteUrl . '/' . $name,
            imageAlt: $image?->getAlt() ?? (string) $crowdfunding->getTitle(),
            variables: array_filter([
                'description' => trim(html_entity_decode(strip_tags((string) $crowdfunding->getDescription()))),
                'author' => (string) $crowdfunding->getAuthorName(),
            ]),
        );
    }
}
