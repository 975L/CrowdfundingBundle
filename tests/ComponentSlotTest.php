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

// What a template writes between the tags of a component is compiled as an embedded template, rendered in the component's own context: a name the component declares as a prop wins over the one the calling template set. "Prize" lost the gold of its drawn ticket that way until 09/09/2026, its "class" reaching the slot as the card's empty one
class ComponentSlotTest extends TestCase
{
    // Every UiBundle component declares a "class" prop, so the name is never the calling template's inside a slot
    public function testNoSlotReadsAClassVariable(): void
    {
        foreach ($this->templates() as $file) {
            foreach ($this->slotContents($file) as $slot) {
                $this->assertDoesNotMatchRegularExpression(
                    '/\{\{[^}]*\bclass\b[^}]*\}\}/',
                    $slot,
                    sprintf('%s prints a "class" variable between the tags of a component, where it resolves to the component\'s own prop and not to what the template set', basename($file))
                );
            }
        }
    }

    // The templates of the bundle
    /** @return list<string> */
    private function templates(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../templates'));

        foreach ($iterator as $file) {
            if ($file->isFile() && 'twig' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    // What each template nests between the tags of a component, a self-closing call nesting nothing
    /** @return list<string> */
    private function slotContents(string $file): array
    {
        preg_match_all('/<twig:[A-Za-z0-9:]+[^>]*(?<!\/)>(.*?)<\/twig:/s', (string) file_get_contents($file), $matches);

        return $matches[1];
    }
}
