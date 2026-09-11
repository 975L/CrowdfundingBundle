<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Twig;

use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslator;
use c975L\CrowdfundingBundle\Twig\Extension\CrowdfundingBlockExtension;
use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\LocaleSwitcher;

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

        $this->assertNull($this->extension($repository, $stack)->getCampaign());
    }

    // The preview is the campaign's own page, minus the block cache: the kinds of this bundle compose it there as they do on the public route, or an editor would read a page with every one of its blocks blank
    public function testTheCampaignIsAlsoReadOnThePreviewRoute(): void
    {
        $repository = $this->createMock(CrowdfundingRepository::class);
        $repository->expects($this->once())->method('findOneBySlug')->with('sauver-les-chats')->willReturn(new Crowdfunding());

        $request = new Request();
        $request->attributes->set('_route', 'crowdfunding_preview');
        $request->attributes->set('slug', 'sauver-les-chats');

        $this->assertInstanceOf(Crowdfunding::class, $this->extension($repository, new RequestStack([$request]))->getCampaign());
    }

    // The localised twin of the campaign's route is the same page read in another language: the kinds of this bundle compose it there too, or "/en/crowdfunding/x" would lose every section a block took over
    public function testTheCampaignIsAlsoReadOnTheLocalisedRoute(): void
    {
        $repository = $this->createMock(CrowdfundingRepository::class);
        $repository->expects($this->once())->method('findOneBySlug')->with('sauver-les-chats')->willReturn(new Crowdfunding());

        $request = new Request();
        $request->attributes->set('_route', 'crowdfunding_display_localized');
        $request->attributes->set('slug', 'sauver-les-chats');

        $this->assertInstanceOf(Crowdfunding::class, $this->extension($repository, new RequestStack([$request]))->getCampaign());
    }

    // The titles a listing block prints follow the language the page is read in, laid over once however many times the block asks
    public function testTheCampaignsListedAreTranslatedOnce(): void
    {
        $campaigns = [new Crowdfunding(), new Crowdfunding()];

        $repository = $this->createStub(CrowdfundingRepository::class);
        $repository->method('findAllSorted')->willReturn($campaigns);

        $translator = $this->createMock(CrowdfundingTranslator::class);
        $translator->expects($this->once())->method('apply')->with($campaigns);

        $extension = $this->extension($repository, translator: $translator);
        $extension->getCampaigns();

        $this->assertCount(1, $extension->getCampaigns(1));
    }

    // Nothing at all outside a request - a warm-up, a command, a test rendering the template by hand
    public function testWithoutARequestNoCampaignIsRead(): void
    {
        $this->assertNull($this->extension()->getCampaign());
    }

    private function extension(?CrowdfundingRepository $repository = null, ?RequestStack $stack = null, ?CrowdfundingTranslator $translator = null): CrowdfundingBlockExtension
    {
        return new CrowdfundingBlockExtension(
            $repository ?? $this->createStub(CrowdfundingRepository::class),
            $stack ?? new RequestStack(),
            new LocalizedRouteNegotiator(new SiteLocales(['fr'], 'fr'), new LocaleSwitcher('fr', []), $this->createStub(UrlGeneratorInterface::class)),
            $translator ?? $this->createStub(CrowdfundingTranslator::class),
        );
    }
}
