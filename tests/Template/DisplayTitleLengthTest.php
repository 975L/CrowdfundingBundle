<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Template;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;

// A campaign page's title: the label then the campaign's name while the whole stays within 65 characters, the name alone past that. The lines setting it are taken out of the page and rendered on their own, the rest of the page asking for a whole kernel
class DisplayTitleLengthTest extends TestCase
{
    private const string DISPLAY = __DIR__ . '/../../templates/crowdfunding/display.html.twig';

    // A short name keeps its label
    public function testAShortNameKeepsItsLabel(): void
    {
        $this->assertSame('Financement participatif : "Château Hurlton"', $this->title('Château Hurlton'));
    }

    // A name the label would push past 65 characters is kept alone
    public function testALongNameDropsTheLabel(): void
    {
        $this->assertSame('La Guilde des Seigneurs - Château Hurlton', $this->title('La Guilde des Seigneurs - Château Hurlton'));
    }

    // The title the page hands its layout for a campaign of this name
    private function title(string $name): string
    {
        $this->assertSame(1, preg_match('/\{% set labelledTitle = .+?\n\{% set title = .+?%\}/s', (string) file_get_contents(self::DISPLAY), $matches), 'The page no longer sets its title this way - check this test still says what it means.');

        $twig = new Environment(new ArrayLoader(['display' => $matches[0] . '{{ title }}']), ['autoescape' => false]);
        $twig->addFilter(new TwigFilter('trans', static fn (string $id): string => 'Financement participatif'));

        return $twig->render('display', ['crowdfunding' => ['title' => $name]]);
    }
}
