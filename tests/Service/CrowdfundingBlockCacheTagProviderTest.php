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
use c975L\CrowdfundingBundle\Service\CrowdfundingBlockCacheTagProvider;
use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The three kinds resolve their campaign live at render time, which no Block event ever signals a change of: one carries a tag of its own, the two reading a date or a visitor answer null instead
class CrowdfundingBlockCacheTagProviderTest extends TestCase
{
    // Exactly the three kinds config/services.yaml declares, or a kind renders with no answer at all
    public function testEveryKindOfThisBundleCarriesAResolver(): void
    {
        $this->assertSame(
            ['crowdfunding_slider', 'crowdfunding_counterparts', 'crowdfunding_lottery'],
            array_keys(new CrowdfundingBlockCacheTagProvider()->getCacheTagResolvers()),
        );
    }

    // The medias and nothing else: the tag CrowdfundingCacheInvalidationListener drops is all this one needs
    public function testTheSliderIsCachedUnderTheCampaignTag(): void
    {
        $this->assertSame(
            [CrowdfundingBlockCacheInvalidator::CACHE_TAG_CROWDFUNDING],
            $this->resolve('crowdfunding_slider'),
        );
    }

    /** @return list<array{0: string}> */
    public static function kindsRenderingLive(): array
    {
        return [['crowdfunding_counterparts'], ['crowdfunding_lottery']];
    }

    // An entry never expires and no event fires the day a campaign ends, so a tier's button and a draw's date are never cached
    #[DataProvider('kindsRenderingLive')]
    public function testAKindReadingADateOrAVisitorVetoesItsOwnEntry(string $kind): void
    {
        $this->assertNull($this->resolve($kind));
    }

    /** @return ?list<string> */
    private function resolve(string $kind): ?array
    {
        return new CrowdfundingBlockCacheTagProvider()->getCacheTagResolvers()[$kind](new Block()->setKind($kind));
    }
}
