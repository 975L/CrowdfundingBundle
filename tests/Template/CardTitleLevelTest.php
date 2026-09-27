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
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

// The rank of a campaign card's title: <h3> under the head the block draws, <h2> when it draws none and the cards sit straight under the page's <h1> - an <h3> there skips a level, which is RGAA criterion 9.1 failing on the /crowdfunding page
class CardTitleLevelTest extends TestCase
{
    // The case this exists for: a block with no head sits straight under the page's <h1>
    public function testABlockWithNoHeadDrawsItsCardsAsH2(): void
    {
        $this->assertStringContainsString('level="h2"', $this->card($this->renderBlock([])));
    }

    // A head of its own is the <h2> the cards then hang under, an eyebrow standing as that heading exactly as Section/_head.html.twig draws it
    public function testABlockCarryingAHeadDrawsItsCardsAsH3(): void
    {
        $this->assertStringContainsString('level="h3"', $this->card($this->renderBlock(['title' => 'Nos campagnes'])));
        $this->assertStringContainsString('level="h3"', $this->card($this->renderBlock(['eyebrow' => 'Financement'])));
    }

    // The /crowdfunding page opens on its cards with nothing between them and the layout's <h1>
    public function testTheListingPageDrawsItsCardsAsH2(): void
    {
        $this->assertMatchesRegularExpression('#<twig:c975LCrowdfunding:Crowdfunding:Crowdfunding [^>]*level="h2"#', $this->read('components/Crowdfunding/Crowdfundings.html.twig'));
    }

    // Matched against the offered levels before it reaches the card, never interpolated: a tag name is not something a caller writes
    public function testTheCardMatchesTheLevelItHandsTheCardComponent(): void
    {
        $template = $this->read('components/Crowdfunding/Crowdfunding.html.twig');

        $this->assertStringContainsString("{% set level = level|default('') in ['h2', 'h3', 'h4'] ? level : 'h3' %}", $template);
        $this->assertStringContainsString('level="{{ level }}"', $template);
        $this->assertStringNotContainsString('level="h3"', $template);
    }

    // The one card call, as a bare Environment writes it out
    private function card(string $html): string
    {
        $this->assertSame(1, preg_match('#<twig:c975LCrowdfunding:Crowdfunding:Crowdfunding [^>]*/>#', $html, $matches), $html);

        return $matches[0];
    }

    // The block rendered through a bare Environment, its campaigns stubbed
    private function renderBlock(array $context): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'c975LCrowdfunding');
        $loader->addPath(\dirname(__DIR__, 2) . '/vendor/c975l/core-bundle/UiBundle/templates', 'c975LUi');
        $twig = new Environment($loader);

        // One campaign is enough for the block to draw its row - a string rather than an entity, the bare Environment writing the prop out as text
        $twig->addFunction(new TwigFunction('crowdfunding_block_campaigns', static fn (): array => ['campaign']));

        return $twig->render('@c975LCrowdfunding/blocks/Campaigns.html.twig', [...$context, 'anchor_id' => 'block-1']);
    }

    // Comments left out: they name the <h3> they explain, which is not markup
    private function read(string $template): string
    {
        return (string) preg_replace('/\{#.*?#\}/s', '', (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/' . $template));
    }
}
