<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Service;

use c975L\CrowdfundingBundle\Service\CrowdfundingBlockCacheInvalidator;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CrowdfundingBlockCacheInvalidatorTest extends TestCase
{
    // The one tag the slider is cached under, and the one place it is dropped from
    public function testItDropsTheTagTheCachedKindsCarry(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())
            ->method('invalidateTags')
            ->with(['crowdfunding_campaign'])
        ;

        new CrowdfundingBlockCacheInvalidator($cache)->invalidateCrowdfunding();
    }

    // The constant is what CrowdfundingBlockCacheTagProvider hands the renderer: the two must name the same string
    public function testTheTagIsNamedOnce(): void
    {
        $this->assertSame('crowdfunding_campaign', CrowdfundingBlockCacheInvalidator::CACHE_TAG_CROWDFUNDING);
    }
}
