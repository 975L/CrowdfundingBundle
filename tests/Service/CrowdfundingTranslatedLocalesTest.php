<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Service;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslatedLocales;
use PHPUnit\Framework\TestCase;

class CrowdfundingTranslatedLocalesTest extends TestCase
{
    // Everything on the index but the campaigns' own titles is this bundle's interface, which ships as a catalogue per language
    public function testTheIndexSaysSomethingInEveryLanguageTheSiteDeclares(): void
    {
        $this->assertSame(['fr', 'en', 'es'], $this->locales()->forIndex());
    }

    // A campaign page and its draw answer in every language the site declares: "/en" is the language the site is read in, not a claim about the row - a title still in French under an English interface is a page half translated, not another page
    public function testACampaignPageAnswersInEveryLanguageTheSiteDeclares(): void
    {
        $this->assertSame(['fr', 'en', 'es'], $this->locales()->forCrowdfunding(new Crowdfunding()));
        $this->assertSame(['fr', 'en', 'es'], $this->locales()->forLottery(new Lottery()));
    }

    private function locales(): CrowdfundingTranslatedLocales
    {
        return new CrowdfundingTranslatedLocales(new SiteLocales(['fr', 'en', 'es'], 'fr'));
    }
}
