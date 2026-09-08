<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use Symfony\Contracts\Cache\TagAwareCacheInterface;

// The tag every cached block of this bundle carries, and the one place it is dropped from. UiBundle's BlockCacheInvalidationListener only ever invalidates the changed Block itself, and knows nothing of the campaign those blocks read at render time - the same gap ShopBundle closes for its catalog
class CrowdfundingBlockCacheInvalidator
{
    // Carried by the kinds reading a campaign: its medias, its counterparts, the quantities already ordered
    public const string CACHE_TAG_CROWDFUNDING = 'crowdfunding_campaign';

    public function __construct(private readonly TagAwareCacheInterface $cache)
    {
    }

    public function invalidateCrowdfunding(): void
    {
        $this->cache->invalidateTags([self::CACHE_TAG_CROWDFUNDING]);
    }
}
