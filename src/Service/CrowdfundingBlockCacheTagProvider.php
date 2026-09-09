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

// The slider is the one kind of this bundle whose render is cached, and it carries the campaign tag CrowdfundingBlockCacheInvalidator drops, no Block event signalling a change of the campaign its template resolves live. The two others are declared "cacheable: false" instead, the answer belonging to the kind rather than to the instance - see the comments on their own service definitions
class CrowdfundingBlockCacheTagProvider implements BlockCacheTagProviderInterface
{
    public function getCacheTagResolvers(): array
    {
        return [
            // The campaign's medias and nothing else: no date is read, no visitor named, so the tag is all this one needs
            'crowdfunding_slider' => static fn (Block $block): array => [CrowdfundingBlockCacheInvalidator::CACHE_TAG_CROWDFUNDING],
        ];
    }
}
