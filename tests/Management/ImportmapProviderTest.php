<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Management;

use c975L\ConfigBundle\Management\ImportmapProviderInterface;
use c975L\CrowdfundingBundle\Management\ImportmapProvider;
use c975L\CrowdfundingBundle\Service\ScriptProvider;
use PHPUnit\Framework\TestCase;

// What "c975l:config:check-importmap" reads to tell a site the entry it is missing: without it, the barrel ScriptProvider announces resolves to nothing in the browser
class ImportmapProviderTest extends TestCase
{
    public function testItDeclaresTheBarrelAsAnEntrypoint(): void
    {
        $entries = new ImportmapProvider()->getImportmapEntries();

        $this->assertSame(['@c975l/crowdfunding-bundle/controllers.js' => ['path' => 'assets/controllers.js', 'entrypoint' => true]], $entries);
    }

    // The import name is the same string on both sides: one naming what the other does not is a barrel loaded from an entry that does not exist
    public function testTheEntryNamesWhatTheScriptProviderAnnounces(): void
    {
        $this->assertSame(new ScriptProvider()->getScripts(), array_keys(new ImportmapProvider()->getImportmapEntries()));
    }

    // The path is relative to the package, and a rename there leaves the site importing a file that is not shipped
    public function testTheFileItDeclaresIsShipped(): void
    {
        foreach (new ImportmapProvider()->getImportmapEntries() as $entry) {
            $this->assertFileExists(\dirname(__DIR__, 2) . '/' . $entry['path']);
        }
    }

    // Nothing of this bundle is drawn in the back office by a controller of its own, the campaign screens being EasyAdmin's
    public function testItDeclaresNoAdminEntry(): void
    {
        $this->assertSame([], new ImportmapProvider()->getAdminImportmapEntries());
    }

    // Found by TaggedInterfacePass through the contract, not by a tag written by hand
    public function testItImplementsTheConfigContract(): void
    {
        $this->assertInstanceOf(ImportmapProviderInterface::class, new ImportmapProvider());
    }
}
