<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\Lottery;

// Which languages each screen of this bundle really says something in - the one thing every localised url here is gated on (see LocalizedRouteNegotiator). The whole answer lives here rather than in two controllers, so translating the campaigns is a change to this file and to nothing else
class CrowdfundingTranslatedLocales
{
    public function __construct(private readonly SiteLocales $siteLocales)
    {
    }

    // The index says the same thing in every language the site declares, everything on it but the campaigns' titles being this bundle's own interface
    /** @return list<string> */
    public function forIndex(): array
    {
        return $this->siteLocales->all();
    }

    // A campaign page answers in every language the site declares, translated or not: "/en" is the language the site is read in, not a claim about the row - that one is CrowdfundingTranslator::translatedLocales(), the one an "hreflang" group may name
    /** @return list<string> */
    public function forCrowdfunding(Crowdfunding $crowdfunding): array
    {
        return $this->siteLocales->all();
    }

    // A draw answers wherever its campaign does
    /** @return list<string> */
    public function forLottery(Lottery $lottery): array
    {
        return $this->siteLocales->all();
    }
}
