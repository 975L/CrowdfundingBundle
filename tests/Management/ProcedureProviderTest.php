<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Management;

use c975L\CrowdfundingBundle\Management\ProcedureProvider;
use PHPUnit\Framework\TestCase;

class ProcedureProviderTest extends TestCase
{
    // Reads the bundle's own config/procedures.json and turns each row into a slug + title + body entry
    public function testGetProceduresReturnsOneEntryPerJsonRow(): void
    {
        $rawEntries = $this->rawEntries();

        $entries = new ProcedureProvider()->getProcedures();

        $this->assertCount(\count($rawEntries), $entries);
        $this->assertSame($rawEntries[0]['slug'], $entries[0]['slug']);
        $this->assertNotSame('', $entries[0]['title']);
        $this->assertNotSame('', $entries[0]['body']);
    }

    // Every procedure is written in the three languages the back-office is read in, a missing one falling back to English and reading as an oversight
    public function testEveryProcedureIsTranslatedInEveryLocale(): void
    {
        foreach ($this->rawEntries() as $entry) {
            foreach (['fr', 'en', 'es'] as $locale) {
                $this->assertArrayHasKey($locale, $entry['title'], $entry['slug']);
                $this->assertArrayHasKey($locale, $entry['body'], $entry['slug']);
            }
        }
    }

    // The one thing the procedure exists to say: a draw is written and mailed on the click, so the recording has to be running before it - a body losing that sentence would be a page of tool names
    public function testTheDrawProcedureWarnsThatThereIsNoSecondTake(): void
    {
        $entry = $this->rawEntries()[0];

        $this->assertSame('filmer-tirage-loterie', $entry['slug']);
        $this->assertStringContainsString('avant', $entry['body']['fr']);
        $this->assertStringContainsString('seconde prise', $entry['body']['fr']);
    }

    // The one thing this procedure exists to say: the form is nowhere in the back-office, so an admin looking for it in management never finds it
    public function testTheNewsProcedureSaysTheFormIsOnThePublicPage(): void
    {
        $entry = $this->rawEntries()[1];

        $this->assertSame('publier-actualite-campagne', $entry['slug']);
        $this->assertStringContainsString('page publique', $entry['body']['fr']);
        $this->assertStringContainsString('back-office', $entry['body']['fr']);
    }

    private function rawEntries(): array
    {
        return json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/config/procedures.json'), true);
    }
}
