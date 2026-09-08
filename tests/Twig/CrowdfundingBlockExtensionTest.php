<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Twig;

use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\CrowdfundingBundle\Service\CrowdfundingBlockCacheTagProvider;
use c975L\CrowdfundingBundle\Twig\Extension\CrowdfundingBlockExtension;
use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

// What the campaign page reads to know which of its sections a block has taken over, and where a block finds the campaign it shows
class CrowdfundingBlockExtensionTest extends TestCase
{
    // A section steps aside for a block placed inside a container as much as for one laid out on its own: the editor moved it, they did not remove it
    public function testTheKindsOfASlotAndOfItsOwnSlotAreReported(): void
    {
        $nested = new Block()->setKind('crowdfunding_counterparts');
        $slot = new Block()->setKind('flex_column');
        $slot->addSlot($nested);
        $container = new Block()->setKind('flex_columns');
        $container->addSlot($slot);

        $kinds = $this->extension()->getSheetKinds([$container, new Block()->setKind('text_section')]);

        $this->assertContains('crowdfunding_counterparts', $kinds);
        $this->assertContains('text_section', $kinds);
    }

    // Read by "not in" on every hardcoded section of the page: a kind named twice would answer the same, and the list is what the template loops over
    public function testEachKindIsReportedOnce(): void
    {
        $kinds = $this->extension()->getSheetKinds([
            new Block()->setKind('crowdfunding_slider'),
            new Block()->setKind('crowdfunding_slider'),
        ]);

        $this->assertSame(['crowdfunding_slider'], $kinds);
    }

    // A campaign block placed on a site page has no campaign to show: the repository is never even asked
    public function testABlockRenderedOutsideACampaignPageFindsNoCampaign(): void
    {
        $repository = $this->createMock(CrowdfundingRepository::class);
        $repository->expects($this->never())->method('findOneBySlug');

        $request = new Request();
        $request->attributes->set('_route', 'page_display');
        $request->attributes->set('slug', 'les-triados-trafic');

        $stack = new RequestStack([$request]);

        $this->assertNull(new CrowdfundingBlockExtension($repository, $stack)->getCampaign());
    }

    // Nothing at all outside a request - a warm-up, a command, a test rendering the template by hand
    public function testWithoutARequestNoCampaignIsRead(): void
    {
        $this->assertNull($this->extension()->getCampaign());
    }

    // Two of the three kinds have to be rendered live: "cacheable" is declared once per kind, while what forbids an entry belongs to what they draw - a button read against today's date, a date read in the visitor's own timezone
    public function testOnlyTheSliderIsCached(): void
    {
        $resolvers = new CrowdfundingBlockCacheTagProvider()->getCacheTagResolvers();
        $block = new Block();

        $this->assertSame(['crowdfunding_campaign'], $resolvers['crowdfunding_slider']($block));
        $this->assertNull($resolvers['crowdfunding_counterparts']($block));
        $this->assertNull($resolvers['crowdfunding_lottery']($block));
    }

    private function extension(): CrowdfundingBlockExtension
    {
        return new CrowdfundingBlockExtension($this->createStub(CrowdfundingRepository::class), new RequestStack());
    }
}
