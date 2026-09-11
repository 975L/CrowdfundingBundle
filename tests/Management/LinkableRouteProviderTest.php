<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Management;

use c975L\ConfigBundle\Management\LinkableRouteProviderInterface;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Management\LinkableRouteProvider;
use c975L\CrowdfundingBundle\Service\CrowdfundingServiceInterface;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslatedLocales;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// What a SiteBundle menu item can point at: this bundle owns no Page, so without this its pages are unreachable from a navbar
class LinkableRouteProviderTest extends TestCase
{
    public function testTheIndexIsAlwaysOffered(): void
    {
        $routes = $this->createProvider()->getLinkableRoutes();

        $this->assertArrayHasKey('crowdfunding_index', $routes);
        $this->assertSame('label.our_crowdfundings', $routes['crowdfunding_index']['label']);
        $this->assertSame('crowdfunding', $routes['crowdfunding_index']['translation_domain']);
    }

    // A campaign is offered by name, and the item stores the slug rather than the id: the url stays what a visitor reads
    public function testEachCampaignIsOfferedByItsOwnTitle(): void
    {
        $routes = $this->createProvider([$this->createCrowdfunding(7, 'sauver-les-chats', 'Sauver les chats')])->getLinkableRoutes();

        $this->assertSame('Sauver les chats', $routes['crowdfunding.7']['label']);
        $this->assertFalse($routes['crowdfunding.7']['translation_domain'], 'A campaign title is shown as it was typed, never run through the translator.');
        $this->assertSame('crowdfunding_display', $routes['crowdfunding.7']['route']);
        $this->assertSame(['slug' => 'sauver-les-chats'], $routes['crowdfunding.7']['params']);
    }

    // The select holds every page of the site: a bare title there says nothing about what it is
    public function testTheBackOfficeSelectSaysWhatACampaignIs(): void
    {
        $routes = $this->createProvider([$this->createCrowdfunding(7, 'sauver-les-chats', 'Sauver les chats')])->getLinkableRoutes();

        $this->assertSame('label.crowdfunding - Sauver les chats', $routes['crowdfunding.7']['picker_label']);
    }

    // Read in another language a menu item is written in that language's url, which only holds where the target really answers - and both of these answer in every language the site declares (see CrowdfundingTranslatedLocales)
    public function testEachEntrySaysWhichLanguagesItAnswersIn(): void
    {
        $routes = $this->createProvider([$this->createCrowdfunding(7, 'sauver-les-chats', 'Sauver les chats')])->getLinkableRoutes();

        $this->assertSame(['fr', 'en'], $routes['crowdfunding_index']['locales']);
        $this->assertSame(['fr', 'en'], $routes['crowdfunding.7']['locales']);
    }

    // A key is what a menu item stores ("route:KEY"): a bare row id is ambiguous the moment another bundle has a row of the same number
    public function testEveryCampaignKeyCarriesALiteralOfItsOwn(): void
    {
        $routes = $this->createProvider([$this->createCrowdfunding(7, 'sauver-les-chats', 'Sauver les chats')])->getLinkableRoutes();

        foreach (array_keys($routes) as $key) {
            $this->assertFalse(is_numeric($key));
        }
    }

    // A campaign with no slug has no url to point at, and would be offered as a menu target leading to "/crowdfunding/"
    public function testACampaignWithoutASlugIsNotOffered(): void
    {
        $routes = $this->createProvider([$this->createCrowdfunding(7, null, 'Sans slug')])->getLinkableRoutes();

        $this->assertCount(1, $routes);
    }

    // Found by TaggedInterfacePass through the contract, not by a tag written by hand
    public function testItImplementsTheConfigContract(): void
    {
        $this->assertInstanceOf(LinkableRouteProviderInterface::class, $this->createProvider());
    }

    // setSlug() does not accept null, but the column is nullable and a row can perfectly well hold none - written straight through reflection to reproduce that
    private function createCrowdfunding(int $id, ?string $slug, string $title): Crowdfunding
    {
        $crowdfunding = new Crowdfunding()->setTitle($title);
        new \ReflectionProperty(Crowdfunding::class, 'id')->setValue($crowdfunding, $id);
        new \ReflectionProperty(Crowdfunding::class, 'slug')->setValue($crowdfunding, $slug);

        return $crowdfunding;
    }

    /** @param list<Crowdfunding> $crowdfundings */
    private function createProvider(array $crowdfundings = []): LinkableRouteProvider
    {
        $crowdfundingService = $this->createStub(CrowdfundingServiceInterface::class);
        $crowdfundingService->method('findAllSorted')->willReturn($crowdfundings);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new LinkableRouteProvider($crowdfundingService, $translator, new CrowdfundingTranslatedLocales(new SiteLocales(['fr', 'en'], 'fr')));
    }
}
