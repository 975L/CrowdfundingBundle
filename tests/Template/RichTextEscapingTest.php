<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Template;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;

// What a visitor types is escaped wherever it is printed, what an editor writes goes through the sanitizer: nothing stored is printed as it was typed, what is already in the database included
class RichTextEscapingTest extends TestCase
{
    private const string ATTACK = '<img src=x onerror=alert(1)>';

    // Each field an editor writes in Trix, in the template printing it
    private const array RICH_FIELDS = [
        'components/Crowdfunding/Presentation.html.twig' => ['crowdfunding.description', 'crowdfunding.useFor'],
        'components/Crowdfunding/UseFor.html.twig' => ['crowdfunding.useFor'],
        'components/Crowdfunding/Author.html.twig' => ['presentation'],
        'components/Crowdfunding/News.html.twig' => ['news.content'],
        'components/Crowdfunding/Counterpart.html.twig' => ['counterpart.description'],
        'components/Lottery/Prize.html.twig' => ['prize.description'],
        'components/Lottery/Lottery.html.twig' => ['prize.description'],
    ];

    public function testNoTemplatePrintsAnythingRaw(): void
    {
        $raw = [];
        foreach (new Finder()->files()->in(\dirname(__DIR__, 2) . '/templates')->name('*.twig') as $file) {
            $source = (string) preg_replace('/\{#.*?#\}/s', '', $file->getContents());
            if (1 === preg_match('/\|\s*raw\b/', $source)) {
                $raw[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $raw);
    }

    /** @return iterable<string, array{string, string}> */
    public static function richFields(): iterable
    {
        foreach (self::RICH_FIELDS as $template => $fields) {
            foreach ($fields as $field) {
                yield $template . ' ' . $field => [$template, $field];
            }
        }
    }

    // Under the sanitizer CoreBundle declares, a field left empty included: the filter takes a string and answers null with a TypeError
    #[DataProvider('richFields')]
    public function testTheRichFieldsGoThroughTheSanitizer(string $template, string $field): void
    {
        $this->assertStringContainsString($field . "|default('')|sanitize_html('c975l.rich')", $this->read($template));
    }

    public function testTheBlockParagraphGoesThroughTheSanitizer(): void
    {
        $this->assertStringContainsString("content|sanitize_html('c975l.rich')", $this->read('blocks/Campaigns.html.twig'));
    }

    // The attack the audit names: a contributor's message, typed by anyone paying nothing, on the campaign's page
    public function testAContributorsMessageIsEscapedAndKeepsItsLineBreaks(): void
    {
        $html = $this->render('components/Crowdfunding/Contributor.html.twig', [
            'contributor' => ['message' => self::ATTACK . "\nsecond line", 'name' => self::ATTACK, 'creation' => new \DateTime()],
        ]);

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;<br />', $html);
    }

    // The same contributor's name, drawn into the label of the prize they won
    public function testAWinnersNameIsEscapedInsideItsLabel(): void
    {
        preg_match('#<p data-lottery-target="winnerName">.*</p>#', $this->read('components/Lottery/Prize.html.twig'), $matches);

        $twig = new Environment(new ArrayLoader(['line' => $matches[0]]));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        $html = $twig->render('line', ['winner' => true, 'contributorName' => self::ATTACK]);

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('<strong>&lt;img src=x onerror=alert(1)&gt;</strong>', $html);
    }

    // The draw's button hands the fetch the token the controller asks for
    public function testTheDrawButtonCarriesItsToken(): void
    {
        $this->assertStringContainsString("data-token=\"{{ csrf_token('lottery_draw') }}\"", $this->read('components/Lottery/DrawButton.html.twig'));
        $this->assertStringContainsString('"X-CSRF-Token": token', (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/js/lottery.js'));
    }

    // A field left empty renders as nothing rather than ending the page on the sanitizer's TypeError
    public function testAnEmptyRichFieldRendersNothing(): void
    {
        $twig = new Environment(new ArrayLoader(['field' => "{{ news.content|default('')|sanitize_html('c975l.rich') }}"]));
        // The signature of the real filter, which takes a string and nothing else
        $twig->addFilter(new TwigFilter('sanitize_html', static fn (string $html, ?string $name = null): string => $html, ['is_safe' => ['html']]));

        $this->assertSame('', $twig->render('field', ['news' => ['content' => null]]));
    }

    /** @param array<string, mixed> $context */
    private function render(string $template, array $context): string
    {
        $twig = new Environment(new ArrayLoader(['template' => $this->read($template)]));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        return $twig->render('template', $context);
    }

    private function read(string $template): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/' . $template);
    }
}
