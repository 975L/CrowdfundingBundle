<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Service;

use c975L\CrowdfundingBundle\Service\StylesheetProvider;
use c975L\UiBundle\Contract\BundleStylesheetProviderInterface;
use PHPUnit\Framework\TestCase;

class StylesheetProviderTest extends TestCase
{
    // The campaign and lottery rules are contributed to UiBundle, which is what loads them on a site page - they lived in ShopBundle before this bundle was extracted from it
    public function testGetStylesheetsReturnsTheBundleStylesheet(): void
    {
        $this->assertSame(['bundles/c975lcrowdfunding/css/styles.min.css'], new StylesheetProvider()->getStylesheets());
    }

    // The path is served from public/, and a rename there leaves the site loading a 404 the browser reports nowhere
    public function testTheStylesheetItAnnouncesIsShipped(): void
    {
        $this->assertFileExists(\dirname(__DIR__, 2) . '/public/css/styles.min.css');
    }

    // Found by TaggedInterfacePass through the contract, not by a tag written by hand
    public function testItImplementsTheUiContract(): void
    {
        $this->assertInstanceOf(BundleStylesheetProviderInterface::class, new StylesheetProvider());
    }
}
