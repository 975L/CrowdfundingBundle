<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\UiBundle\Contract\InternalLinkLocalizerInterface;

// Rewrites this bundle's own links into the language the page around them is being read in - the counterpart of SiteBundle's PageLinkLocalizer, for the three screens it owns. Without it a visitor reading "/en/" is sent back into the writing language at the first click on a card pointing at the campaigns
class CrowdfundingLinkLocalizer implements InternalLinkLocalizerInterface
{
    // The three screens this bundle answers both bare and localised, listed rather than matched on the "/crowdfunding" prefix: a campaign's preview and the draw endpoint live under it with no localised route at all
    private const array PATHS = [
        '#^/crowdfunding$#' => 'crowdfunding_index',
        '#^/crowdfunding/lottery/(?<identifier>[a-zA-Z0-9\-]{13})$#' => 'lottery_display',
        '#^/crowdfunding/(?<slug>[a-zA-Z0-9\-]+)$#' => 'crowdfunding_display',
    ];

    public function __construct(private readonly LocalizedUrlGenerator $localizedUrlGenerator)
    {
    }

    public function localize(string $value): string
    {
        // A whole rich text: only what an href holds is a link, the same words elsewhere in the prose being prose
        if (str_contains($value, 'href="')) {
            return (string) preg_replace_callback(
                '#href="([^"]*)"#',
                fn (array $matches): string => sprintf('href="%s"', $this->localizePath($matches[1])),
                $value
            );
        }

        return $this->localizePath($value);
    }

    // The generator holds the whole rule - the language being read, the twin, the fallback - so a stored link, a menu item and a template's own "localized_path" all read a link the same way
    private function localizePath(string $path): string
    {
        foreach (self::PATHS as $pattern => $route) {
            if (1 !== preg_match($pattern, $path, $matches)) {
                continue;
            }

            // The named groups the pattern happens to hold, which is the slug, the draw's identifier or nothing at all - read off the match rather than listed beside it, so a route gaining a parameter needs its pattern changed and nothing else
            $parameters = [];
            foreach ($matches as $name => $value) {
                if (\is_string($name)) {
                    $parameters[$name] = $value;
                }
            }

            return $this->localizedUrlGenerator->path($route, $parameters);
        }

        return $path;
    }
}
