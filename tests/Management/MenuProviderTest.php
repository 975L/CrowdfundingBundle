<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Management;

use c975L\CrowdfundingBundle\Controller\Management\CrowdfundingCrudController;
use c975L\CrowdfundingBundle\Management\MenuProvider;
use PHPUnit\Framework\TestCase;

class MenuProviderTest extends TestCase
{
    // Its own section rather than the shared "management" one, as Shop, Payment, Book and Social each keep theirs: a campaign is a surface of its own, not a screen of the site's administration
    public function testGetMenuSectionNamesTheBundleSection(): void
    {
        $this->assertSame(['label' => 'label.crowdfundings', 'translation_domain' => 'crowdfunding'], new MenuProvider()->getMenuSection());
    }

    // One entry, the campaigns, from which counterparts, news, medias and the lottery are all edited as collections
    public function testGetMenusReturnsTheCampaignEntry(): void
    {
        $menus = new MenuProvider()->getMenus();

        $this->assertCount(1, $menus);
        $this->assertSame(CrowdfundingCrudController::class, $menus['crowdfunding']['controller']);
        $this->assertSame('label.crowdfundings', $menus['crowdfunding']['label']);
        $this->assertSame('crowdfunding', $menus['crowdfunding']['translation_domain']);
    }

    // The onboarding tour builds a step per menu entry: one without a description shows its label alone, and the sentence it wants is the screen's own welcome text rather than a string written for the tour (see MenuProviderInterface)
    public function testTheCampaignEntryCarriesWhatTheOnboardingTourReadsAndSpeaks(): void
    {
        $entry = new MenuProvider()->getMenus()['crowdfunding'];

        $this->assertSame('label.info_crowdfunding', $entry['description']);
        $this->assertSame('narration.crowdfundings', $entry['narration']);
        // No role of its own: the whole CRUD sits behind site-role-admin, which is the key an entry already defaults to - naming it again would only be one more place to forget
        $this->assertArrayNotHasKey('role', $entry);
    }

    // The public index, offered in the sidebar so an admin reaches the page a visitor sees without leaving the back office
    public function testGetLinksPointsToThePublicIndex(): void
    {
        $links = new MenuProvider()->getLinks();

        $this->assertCount(1, $links);
        $this->assertSame('crowdfunding_index', $links['crowdfunding']['name']);
        $this->assertSame('crowdfunding', $links['crowdfunding']['translation_domain']);
        $this->assertSame('text.crowdfundings', $links['crowdfunding']['description']);
        $this->assertSame('narration.crowdfunding_index', $links['crowdfunding']['narration']);
    }
}
