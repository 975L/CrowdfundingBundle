<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests;

use PHPUnit\Framework\TestCase;

// This bundle spoke the "shop" domain and shipped no catalog of its own until 07/09/2026, while requiring neither ShopBundle nor its catalog: a site running a campaign without the shop showed raw keys on every page. These tests keep it from coming back
class TranslationDomainTest extends TestCase
{
    private const array LOCALES = ['en', 'fr', 'es'];

    // "site" is SiteBundle's own, shipped by a bundle every satellite depends on, and "payment" is this bundle's own dependency - both stay legitimate
    private const array FOREIGN_DOMAINS = ['shop', 'book', 'gallery', 'social'];

    // Nothing here may name another satellite's catalog: that bundle may simply not be installed
    public function testNoFileSpeaksAnotherBundleDomain(): void
    {
        foreach ($this->sourceFiles() as $file) {
            $contents = (string) file_get_contents($file);

            foreach (self::FOREIGN_DOMAINS as $domain) {
                $this->assertDoesNotMatchRegularExpression(
                    sprintf("/trans_default_domain\\s+'%s'|,\\s*'%s'\\s*\\)|'translation_domain'\\s*=>\\s*'%s'/", $domain, $domain, $domain),
                    $contents,
                    sprintf('%s names the "%s" domain, whose catalog this bundle does not ship', basename($file), $domain)
                );
            }
        }
    }

    // A key named in the code and absent from the catalog renders as itself - "label.counterparts" printed as such on the page
    public function testEveryKeyTheCodeNamesIsShipped(): void
    {
        $used = $this->usedKeys();
        $this->assertNotEmpty($used, 'No key was read from "src/" or "templates/", so this test checked nothing at all.');

        foreach (self::LOCALES as $locale) {
            $catalog = $this->catalog($locale);

            foreach ($used as $key => $file) {
                $this->assertArrayHasKey($key, $catalog, sprintf('"%s" names "%s", which "crowdfunding.%s.xlf" does not ship.', $file, $key, $locale));
            }
        }
    }

    // The other way round: a key nobody names any more is dead weight in a catalog written by hand
    public function testNoKeyIsShippedWithoutBeingNamed(): void
    {
        $used = $this->usedKeys();

        foreach (array_keys($this->catalog('en')) as $key) {
            $this->assertArrayHasKey($key, $used, sprintf('"crowdfunding.en.xlf" ships "%s", which nothing in "src/" or "templates/" names any more.', $key));
        }
    }

    // A key present in one locale and not in the others shows up raw for part of the visitors only, which is the hardest kind to notice
    public function testEveryLocaleCarriesTheSameKeys(): void
    {
        $reference = array_keys($this->catalog('en'));
        sort($reference);

        foreach (self::LOCALES as $locale) {
            $keys = array_keys($this->catalog($locale));
            sort($keys);

            $this->assertSame($reference, $keys, sprintf('The %s catalog does not carry the same keys as the English one', $locale));
        }
    }

    public function testNoTranslationIsEmpty(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach ($this->catalog($locale) as $key => $value) {
                $this->assertNotSame('', $value, sprintf('"%s" is empty in the %s catalog', $key, $locale));
            }
        }
    }

    /**
     * Every key this bundle resolves in its own domain, keyed by the file naming it.
     *
     * @return array<string, string>
     */
    private function usedKeys(): array
    {
        $used = [];

        foreach ($this->sourceFiles() as $file) {
            $contents = (string) file_get_contents($file);
            $name = basename($file);
            $default = str_contains($contents, "trans_default_domain 'crowdfunding'");

            // Twig: 'key'|trans({}, 'crowdfunding'), or without a domain in a file declaring the default one
            // The parentheses are optional in Twig: "'key'|trans" under a trans_default_domain is how the emails named theirs, and a regexp asking for them missed every one
            preg_match_all("/'([a-zA-Z0-9_.]+)'\\s*\\|\\s*trans(?:\\(\\s*(?:\\{[^}]*\\})?\\s*(?:,\\s*'([a-z_]+)')?)?/", $contents, $twig, PREG_SET_ORDER);
            foreach ($twig as $match) {
                $domain = $match[2] ?? '';
                if ('crowdfunding' === $domain || ('' === $domain && $default)) {
                    $used[$match[1]] = $name;
                }
            }

            // PHP: trans('key', [], 'crowdfunding') and the t() the CRUD fields take
            preg_match_all("/(?:trans|\\bt)\\(\\s*'([a-zA-Z0-9_.]+)'\\s*,\\s*\\[\\]\\s*,\\s*'crowdfunding'\\s*\\)/", $contents, $php);
            foreach ($php[1] as $key) {
                $used[$key] = $name;
            }

            // The email chain names its keys as bare arguments - a subject key handed to the sender, a sentence handed to the template provider - which no trans() call spells out. Both directories only ever name this bundle's own catalogue
            if (str_contains($file, '/src/Email/') || str_contains($file, '/src/MessageHandler/')) {
                preg_match_all("/'((?:label|text)\\.[a-zA-Z0-9_.]+)'/", $contents, $emails);
                foreach ($emails[1] as $key) {
                    // The subject prefix reads its own word in PaymentBundle's catalogue, the bundle that declares the "shop-name" key beside it
                    if (str_contains($contents, sprintf("'%s', [], 'payment'", $key))) {
                        continue;
                    }

                    $used[$key] = $name;
                }
            }

            // A form type resolving its own labels in the bundle's domain: they carry no domain of their own
            if (str_contains($contents, "'translation_domain' => 'crowdfunding'")) {
                preg_match_all("/'(?:label|help)' => '([a-z][a-zA-Z0-9_]*\\.[a-zA-Z0-9_.]+)'/", $contents, $labels);
                foreach ($labels[1] as $key) {
                    $used[$key] = $name;
                }
            }
        }

        return $used;
    }

    /**
     * @return array<string, string>
     */
    private function catalog(string $locale): array
    {
        $xliff = simplexml_load_file(__DIR__ . '/../translations/crowdfunding.' . $locale . '.xlf');
        $translations = [];

        foreach ($xliff->file->body->{'trans-unit'} as $unit) {
            $translations[(string) $unit->source] = (string) $unit->target;
        }

        return $translations;
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (['/../src', '/../templates'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . $directory));
            foreach ($iterator as $file) {
                if ($file->isFile() && \in_array($file->getExtension(), ['php', 'twig'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
