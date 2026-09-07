<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// The wheel's own wording, written by JS after a fetch where a Twig |trans never reaches: a locale dropped falls back to English, a missing key renders raw to the visitor
class TranslationsJsTest extends TestCase
{
    private const array LOCALES = ['en', 'es', 'fr'];

    public function testEveryShippedLocaleIsPresent(): void
    {
        $translations = $this->loadTranslations();

        foreach (self::LOCALES as $locale) {
            $this->assertArrayHasKey($locale, $translations);
        }
    }

    // A key present in one locale only is a key that renders raw for every other visitor
    public function testEveryLocaleCarriesTheSameKeys(): void
    {
        $translations = $this->loadTranslations();
        $reference = array_keys($translations['en']);

        foreach (self::LOCALES as $locale) {
            $this->assertSame($reference, array_keys($translations[$locale]), sprintf('The "%s" locale does not carry the same keys as "en"', $locale));
        }
    }

    // Every key the controller asks for must exist, or Handlers.translate() hands the raw key to the visitor
    public function testTheControllerAsksForNoUnknownKey(): void
    {
        $script = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/js/lottery.js');
        preg_match_all('/Handlers\.translate\(\s*"([^"]+)"/', $script, $matches);

        $this->assertNotEmpty($matches[1], 'lottery.js asks for no translation, the test itself is broken.');

        $available = array_keys($this->loadTranslations()['en']);
        foreach (array_unique($matches[1]) as $key) {
            $this->assertContains($key, $available, sprintf('lottery.js asks for "%s", which translations.js does not carry.', $key));
        }
    }

    // The three per-locale files were merged into one on 07/09/2026, as SiteBundle and PaymentBundle had already done: two of them are how a key ends up translated in one language and missing in another
    public function testTheOldPerLocaleFilesAreGone(): void
    {
        foreach (self::LOCALES as $locale) {
            $this->assertFileDoesNotExist(\dirname(__DIR__, 2) . '/assets/js/translations.' . $locale . '.js');
        }
    }

    /** @return array<string, array<string, string>> */
    private function loadTranslations(): array
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/js/translations.js');
        $json = substr($source, (int) strpos($source, '{'));
        $json = (string) preg_replace('/,(\s*[}\]])/', '$1', rtrim(trim($json), ';'));

        $translations = json_decode($json, true);
        $this->assertIsArray($translations, 'translations.js is not a plain object literal any more, and cannot be read without a JS runtime.');

        return $translations;
    }
}
