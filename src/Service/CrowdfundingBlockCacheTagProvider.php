<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use c975L\UiBundle\Contract\BlockCacheTagProviderInterface;
use c975L\UiBundle\Entity\Block;

// The slider carries the campaign tag CrowdfundingBlockCacheInvalidator drops, no Block event signalling a change of the campaign its template resolves live; the two others veto their own entry, "cacheable" being declared once per kind while the answer belongs to what they draw
class CrowdfundingBlockCacheTagProvider implements BlockCacheTagProviderInterface
{
    public function getCacheTagResolvers(): array
    {
        return [
            // The campaign's medias and nothing else: no date is read, no visitor named, so the tag is all this one needs
            'crowdfunding_slider' => static fn (Block $block): array => [CrowdfundingBlockCacheInvalidator::CACHE_TAG_CROWDFUNDING],
            // Never cached: each tier draws its own button, which reads the campaign's dates against the current time (see CrowdfundingCounterpart/AddButton.html.twig). An entry never expires and no event fires the day a campaign ends, so a cached grid would keep offering to contribute to a campaign that closed - and the basket messages laid beside the tiers belong to whoever is reading, not to everybody
            'crowdfunding_counterparts' => static fn (Block $block): ?array => null,
            // Never cached either: the draws print their dates in the visitor's own timezone (see Lottery.html.twig, app.session.get('user_timezone')) and offer the draw button to an administrator alone. An entry is shared by everyone who reads the page, so it would serve one visitor's timezone to the next, and either hide that button from the administrator or hand it to everybody
            'crowdfunding_lottery' => static fn (Block $block): ?array => null,
        ];
    }
}
