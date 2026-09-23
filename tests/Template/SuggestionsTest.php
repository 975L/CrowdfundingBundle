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

// The names and prices offered as an editor types a counterpart or a prize: the template draws the lists, the form types point at them by id, and nothing but this test holds the two ends together
class SuggestionsTest extends TestCase
{
    private const string TEMPLATE = '/templates/management/_suggestions.html.twig';

    // An id drawn here and no longer pointed at by a form type would silently offer nothing at all, a datalist missing being a plain text field
    public function testEveryListTheFormTypesPointAtIsDrawn(): void
    {
        $template = $this->template();

        foreach (['crowdfunding-counterpart-titles', 'crowdfunding-counterpart-prices', 'crowdfunding-prize-titles'] as $id) {
            $this->assertStringContainsString('<datalist id="' . $id . '">', $template);
        }

        $pointed = [
            '/Form/CrowdfundingCounterpartType.php' => ['crowdfunding-counterpart-titles', 'crowdfunding-counterpart-prices'],
            '/Form/LotteryPrizeType.php' => ['crowdfunding-prize-titles'],
        ];

        foreach ($pointed as $file => $ids) {
            $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/src' . $file);
            foreach ($ids as $id) {
                $this->assertStringContainsString("'list' => '" . $id . "'", $source);
            }
        }
    }

    // TranslationDomainTest holds the keys against the catalogs; what is counted here is that the two lists are whole, a name dropped from the template going unnoticed otherwise
    public function testTenTierNamesAndFivePrizeNamesAreOffered(): void
    {
        $template = $this->template();

        $this->assertSame(10, substr_count($template, "'label.counterpart_name_"));
        $this->assertSame(5, substr_count($template, "'label.prize_name_"));
    }

    // The ladder is typed in euros, the field dividing the cents the column holds: a list in cents would offer 5 cents for a 5 euro tier
    public function testThePriceLadderIsOfferedInEuros(): void
    {
        $this->assertStringContainsString('[5, 10, 20, 50, 75, 100, 200]', $this->template());
    }

    private function template(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . self::TEMPLATE);
    }
}
