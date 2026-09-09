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

    // What a template writes between the tags of a component is compiled as an embedded template, which "trans_default_domain" does not reach: a key named there without its domain prints raw, in either quote
    public function testNoKeyBetweenComponentTagsReliesOnTheDefaultDomain(): void
    {
        foreach ($this->sourceFiles() as $file) {
            foreach ($this->slotContents($file) as $slot) {
                $this->assertDoesNotMatchRegularExpression(
                    "/['\"][a-zA-Z0-9_.]+['\"]\\s*\\|\\s*trans(?!\\(\\s*\\{[^}]*\\}\\s*,\\s*['\"])/",
                    $slot,
                    sprintf('%s names a key between the tags of a component without its domain, where "trans_default_domain" does not reach it', basename($file))
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

    // What a Twig file writes between the opening and closing tags of a component, a self-closing tag opening no slot at all
    /** @return list<string> */
    private function slotContents(string $file): array
    {
        if (!str_ends_with($file, '.twig')) {
            return [];
        }

        preg_match_all('/<twig:[A-Za-z0-9:]+[^>]*(?<!\/)>(.*?)<\/twig:/s', (string) file_get_contents($file), $matches);

        return $matches[1];
    }

    // Every key this bundle resolves in its own domain, keyed by the file naming it
    /** @return array<string, string> */
    private function usedKeys(): array
    {
        $used = [];

        foreach ($this->sourceFiles() as $file) {
            $contents = (string) file_get_contents($file);
            $name = basename($file);

            foreach ($this->keysNamedIn($file, $contents) as $key) {
                $used[$key] = $name;
            }
        }

        return $used;
    }

    // The four ways a key is named, a file being read by whichever of them applies to it
    /** @return list<string> */
    private function keysNamedIn(string $file, string $contents): array
    {
        $keys = $this->twigKeys($contents);
        $keys = array_merge($keys, $this->phpKeys($contents));

        // A service definition names its keys as plain tag attributes, quoted and nothing else
        if (str_ends_with($file, '.yaml')) {
            $keys = array_merge($keys, $this->yamlKeys($contents));
        }

        // The email chain names its keys as bare arguments - a subject key handed to the sender, a sentence handed to the template provider - which no trans() call spells out. Both directories only ever name this bundle's own catalogue
        if (str_contains($file, '/src/Email/') || str_contains($file, '/src/MessageHandler/')) {
            $keys = array_merge($keys, $this->emailKeys($contents));
        }

        // A form type resolving its own labels in the bundle's domain, and the menu and guided-project providers naming theirs the same way, carry no domain of their own; "narration" is left out, resolved in the "_narration" domain NarrationCatalogueTest holds
        if (str_contains($contents, "'translation_domain' => 'crowdfunding'")) {
            $keys = array_merge($keys, $this->declaredLabelKeys($contents));
            $keys = array_merge($keys, $this->traitLabelKeys($contents));
        }

        return $keys;
    }

    // The fields a form type opts into rather than writes: UiBundle's traits add them, and their labels are looked up in the domain the type itself names - so this bundle ships them without a single one of its own files spelling them out
    /** @return list<string> */
    private function traitLabelKeys(string $contents): array
    {
        $traits = [
            'HasAnchorFieldTrait' => ['label.anchor', 'label.anchor_help'],
            'HasBackgroundFieldTrait' => [
                'label.section_background',
                'label.section_background_help',
                'label.section_background_muted',
                'label.section_background_primary',
                'label.section_background_dark',
                'label.section_background_none',
            ],
        ];

        $keys = [];
        foreach ($traits as $trait => $traitKeys) {
            if (str_contains($contents, 'use ' . $trait . ';')) {
                $keys = array_merge($keys, $traitKeys);
            }
        }

        return $keys;
    }

    // Twig: 'key'|trans({}, 'crowdfunding'), or without a domain in a file declaring the default one - the parentheses being optional, "'key'|trans" is how the emails named theirs and a regexp asking for them missed every one. Either quote, Twig taking both: "label.name" in Prize.html.twig was named nowhere this test could see it, and printed raw
    /** @return list<string> */
    private function twigKeys(string $contents): array
    {
        $default = str_contains($contents, "trans_default_domain 'crowdfunding'");
        preg_match_all("/['\"]([a-zA-Z0-9_.]+)['\"]\\s*\\|\\s*trans(?:\\(\\s*(?:\\{[^}]*\\})?\\s*(?:,\\s*['\"]([a-z_]+)['\"])?)?/", $contents, $matches, PREG_SET_ORDER);
        $keys = [];

        foreach ($matches as $match) {
            $domain = $match[2] ?? '';
            if ('crowdfunding' === $domain || ('' === $domain && $default)) {
                $keys[] = $match[1];
            }
        }

        return $keys;
    }

    // PHP: trans('key', [...], 'crowdfunding') and the t() the CRUD fields take, in either quote as in Twig above - the parameters are read as anything but a closing bracket, a key handed placeholders being named no differently from one handed none
    /** @return list<string> */
    private function phpKeys(string $contents): array
    {
        preg_match_all("/(?:trans|\\bt)\\(\\s*['\"]([a-zA-Z0-9_.]+)['\"]\\s*,\\s*\\[[^\\]]*\\]\\s*,\\s*['\"]crowdfunding['\"]\\s*\\)/", $contents, $matches);

        return $matches[1];
    }

    // The subject prefix reads its own word in PaymentBundle's catalogue, the bundle that declares the "shop-name" key beside it
    /** @return list<string> */
    private function emailKeys(string $contents): array
    {
        preg_match_all("/'((?:label|text)\\.[a-zA-Z0-9_.]+)'/", $contents, $matches);

        return array_values(array_filter(
            $matches[1],
            static fn (string $key): bool => !str_contains($contents, sprintf("'%s', [], 'payment'", $key))
        ));
    }

    // The labels a form type or a provider declares as plain array values, resolved in the domain the class itself names - a ChoiceType naming its own the other way round, the key standing left of the value it stores
    /** @return list<string> */
    private function declaredLabelKeys(string $contents): array
    {
        preg_match_all("/'(?:label|help|description)' => '([a-z][a-zA-Z0-9_]*\\.[a-zA-Z0-9_.]+)'/", $contents, $matches);
        preg_match_all("/'((?:label|text)\\.[a-zA-Z0-9_.]+)'\\s*=>/", $contents, $choices);

        return array_merge($matches[1], $choices[1]);
    }

    /** @return array<string, string> */
    private function catalog(string $locale): array
    {
        $xliff = simplexml_load_file(__DIR__ . '/../translations/crowdfunding.' . $locale . '.xlf');
        $translations = [];

        foreach ($xliff->file->body->{'trans-unit'} as $unit) {
            $translations[(string) $unit->source] = (string) $unit->target;
        }

        return $translations;
    }

    // Every key a "ui.block" tag names for its kind: the label shown in the picker, the sentence under it, and the category it is filed in
    /** @return list<string> */
    private function yamlKeys(string $contents): array
    {
        preg_match_all("/^\\s*(?:label|description|category):\\s*['\"]([a-z_]+\\.[a-zA-Z0-9_.]+)['\"]/m", $contents, $matches);

        return $matches[1];
    }

    /** @return list<string> */
    private function sourceFiles(): array
    {
        $files = [];

        // "config" joins them since the block kinds declare their own label, description and category straight on the "ui.block" tag, where no trans() call ever spells them out
        foreach (['/../src', '/../templates', '/../config'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . $directory));
            foreach ($iterator as $file) {
                if ($file->isFile() && \in_array($file->getExtension(), ['php', 'twig', 'yaml'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
