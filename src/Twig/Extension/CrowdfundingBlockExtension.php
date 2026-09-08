<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Twig\Extension;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\UiBundle\Entity\Block;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Attribute\AsTwigFunction;

// Resolves, at render time, the campaign the block templates of this bundle display - a Block only ever stores how to show it (a number of columns, a threshold), never the campaign itself, so a block never goes stale against the funding. Same split as ShopBundle's ShopBlockExtension and BookBundle's BookBlockExtension
class CrowdfundingBlockExtension implements ResetInterface
{
    // The route a campaign is served under, and the one a block reads its campaign from - the kinds of this bundle only ever compose that page
    private const string CROWDFUNDING_ROUTE = 'crowdfunding_display';

    /** @var array<string, ?Crowdfunding> */
    private array $bySlug = [];

    public function __construct(
        private readonly CrowdfundingRepository $crowdfundingRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    // The campaign the page being rendered is about, read from the request: a block of this bundle placed anywhere else answers null, its template then rendering nothing rather than half a section
    #[AsTwigFunction('crowdfunding_block_campaign')]
    public function getCampaign(): ?Crowdfunding
    {
        $slug = $this->currentSlug();

        if (null === $slug) {
            return null;
        }

        return $this->bySlug[$slug] ??= $this->crowdfundingRepository->findOneBySlug($slug);
    }

    // Every kind held by a campaign, two levels of slots included: what a hardcoded section of crowdfunding/display.html.twig reads to step aside once the editor has placed the block taking it over
    /**
     * @param iterable<Block> $blocks
     *
     * @return list<string>
     */
    #[AsTwigFunction('crowdfunding_block_sheet_kinds')]
    public function getSheetKinds(iterable $blocks): array
    {
        $kinds = [];
        foreach ($blocks as $block) {
            $kinds[] = (string) $block->getKind();
            foreach ($block->getSlots() as $slot) {
                $kinds[] = (string) $slot->getKind();
                foreach ($slot->getSlots() as $nested) {
                    $kinds[] = (string) $nested->getKind();
                }
            }
        }

        return array_values(array_unique($kinds));
    }

    // Dropped between two requests, a worker runtime (FrankenPHP, RoadRunner...) keeping this service alive from one to the next - the campaign read for one visitor would otherwise be served to the next
    public function reset(): void
    {
        $this->bySlug = [];
    }

    // The campaign of the page being rendered, and only there: a block placed on a page that is not a campaign has no campaign to show
    private function currentSlug(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || self::CROWDFUNDING_ROUTE !== $request->attributes->get('_route')) {
            return null;
        }

        $slug = $request->attributes->get('slug');

        return \is_string($slug) && '' !== $slug ? $slug : null;
    }
}
