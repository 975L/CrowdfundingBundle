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
use Symfony\Component\Yaml\Yaml;

// The three kinds resolve their campaign live at render time, which no Block event ever signals a change of: the slider carries a tag of its own, the two reading a date or a visitor are not cached at all
class CrowdfundingBlockCacheTagProviderTest extends TestCase
{
    // The one kind of this bundle whose render is cached: a resolver for anything else would be a kind config/services.yaml declares "cacheable: false", where the tag would never be read
    public function testTheSliderIsTheOnlyKindCarryingAResolver(): void
    {
        $this->assertSame(
            ['crowdfunding_slider'],
            array_keys(new CrowdfundingBlockCacheTagProvider()->getCacheTagResolvers()),
        );
    }

    // The medias and nothing else: the tag CrowdfundingCacheInvalidationListener drops is all this one needs
    public function testTheSliderIsCachedUnderTheCampaignTag(): void
    {
        $this->assertSame(
            [CrowdfundingBlockCacheInvalidator::CACHE_TAG_CROWDFUNDING],
            new CrowdfundingBlockCacheTagProvider()->getCacheTagResolvers()['crowdfunding_slider'](
                new Block()->setKind('crowdfunding_slider'),
            ),
        );
    }

    /** @return list<array{0: string}> */
    public static function kindsRenderingLive(): array
    {
        return [['crowdfunding_counterparts'], ['crowdfunding_lottery']];
    }

    // An entry never expires and no event fires the day a campaign ends, so a tier's button and a draw's date are never cached - said once on the kind rather than vetoed on each of its instances
    #[DataProvider('kindsRenderingLive')]
    public function testAKindReadingADateOrAVisitorIsNotCacheable(string $kind): void
    {
        $this->assertFalse($this->blockTag($kind)['cacheable']);
    }

    // The slider says the opposite, and is what the resolver above is for
    public function testTheSliderIsDeclaredCacheable(): void
    {
        $this->assertTrue($this->blockTag('crowdfunding_slider')['cacheable']);
    }

    /** @return array<string, mixed> */
    private function blockTag(string $kind): array
    {
        $services = Yaml::parseFile(\dirname(__DIR__, 2) . '/config/services.yaml')['services'];

        foreach ($services as $definition) {
            foreach ($definition['tags'] ?? [] as $tag) {
                if ('ui.block' === ($tag['name'] ?? null) && $kind === ($tag['kind'] ?? null)) {
                    return $tag;
                }
            }
        }

        $this->fail(sprintf('No "ui.block" tag declares the kind "%s".', $kind));
    }
}
