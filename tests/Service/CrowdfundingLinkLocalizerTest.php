<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Service;

use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\CrowdfundingBundle\Service\CrowdfundingLinkLocalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CrowdfundingLinkLocalizerTest extends TestCase
{
    // A card pointing at the campaigns from an English page leads into the English index, not back into the writing language
    public function testTheIndexLinkIsReadInTheLanguageThePageAroundItIsReadIn(): void
    {
        $this->assertSame('/en/crowdfunding', $this->localizer('en')->localize('/crowdfunding'));
    }

    // A word linked inside a rich text is a link like any other
    public function testTheLinksOfARichTextAreRewritten(): void
    {
        $this->assertSame(
            'voir <a href="/en/crowdfunding">nos collectes</a>',
            $this->localizer('en')->localize('voir <a href="/crowdfunding">nos collectes</a>'),
        );
    }

    // A campaign and its draw are read at that language's url too: "/en" is the language the site is read in, not a claim about the row (see CrowdfundingTranslatedLocales)
    public function testACampaignIsLinkedInTheLanguageBeingRead(): void
    {
        $localizer = $this->localizer('en');

        $this->assertSame('/en/crowdfunding/toit-ecole', $localizer->localize('/crowdfunding/toit-ecole'));
        $this->assertSame('/en/crowdfunding/lottery/aBcDeF1234567', $localizer->localize('/crowdfunding/lottery/aBcDeF1234567'));
    }

    // A preview and the draw's own endpoint have no localised route at all: a rule matching on the "/crowdfunding" prefix would rewrite them into urls nothing answers
    public function testAPathWithNoLocalisedTwinIsGivenBackUntouched(): void
    {
        $localizer = $this->localizer('en');

        foreach (['/crowdfunding/toit-ecole/preview', '/crowdfunding/lottery/aBcDeF1234567/draw/2', '/crowdfunding/'] as $path) {
            $this->assertSame($path, $localizer->localize($path));
        }
    }

    // The no-regression contract: the language the site is written in keeps the urls it always had
    public function testTheWritingLanguageIsLeftExactlyAsItWas(): void
    {
        $this->assertSame('/crowdfunding', $this->localizer(null)->localize('/crowdfunding'));
    }

    private function localizer(?string $readingLocale): CrowdfundingLinkLocalizer
    {
        $request = Request::create('/');
        if (null !== $readingLocale) {
            $request->attributes->set('_locale', $readingLocale);
        }

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []): string => match ($route) {
                'crowdfunding_index' => '/crowdfunding',
                'crowdfunding_index_localized' => '/' . $parameters['_locale'] . '/crowdfunding',
                'crowdfunding_display' => '/crowdfunding/' . $parameters['slug'],
                'crowdfunding_display_localized' => '/' . $parameters['_locale'] . '/crowdfunding/' . $parameters['slug'],
                'lottery_display' => '/crowdfunding/lottery/' . $parameters['identifier'],
                'lottery_display_localized' => '/' . $parameters['_locale'] . '/crowdfunding/lottery/' . $parameters['identifier'],
                default => throw new RouteNotFoundException($route),
            }
        );

        return new CrowdfundingLinkLocalizer(
            new LocalizedUrlGenerator($router, new SiteLocales(['fr', 'en'], 'fr'), new RequestStack([$request])),
        );
    }
}
