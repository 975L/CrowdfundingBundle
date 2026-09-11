<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Management;

use c975L\ConfigBundle\Management\LinkableRouteProviderInterface;
use c975L\CrowdfundingBundle\Service\CrowdfundingServiceInterface;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslatedLocales;
use Symfony\Contracts\Translation\TranslatorInterface;

// What a SiteBundle menu item can point at without the site owning a Page for it: the campaign index, and each running campaign by name
class LinkableRouteProvider implements LinkableRouteProviderInterface
{
    public function __construct(
        private readonly CrowdfundingServiceInterface $crowdfundingService,
        private readonly TranslatorInterface $translator,
        private readonly CrowdfundingTranslatedLocales $translatedLocales,
    ) {
    }

    public function getLinkableRoutes(): array
    {
        $routes = [
            'crowdfunding_index' => [
                'label' => 'label.our_crowdfundings',
                'translation_domain' => 'crowdfunding',
                // Read in another language, a menu item pointing here is written in that language's url - which only holds while the index really answers there (see CrowdfundingTranslatedLocales)
                'locales' => $this->translatedLocales->forIndex(),
            ],
        ];

        foreach ($this->crowdfundingService->findAllSorted() as $crowdfunding) {
            $slug = $crowdfunding->getSlug();
            if (null === $slug || '' === $slug) {
                continue;
            }

            // Keyed on a literal of this bundle's own in front of the id: a bare number is ambiguous the moment another bundle has a row of the same one, and the item stored for one would render the other's target
            $routes['crowdfunding.' . $crowdfunding->getId()] = [
                // The campaign's own title is what the rendered menu item has to read, where the back office's select holds it among every page of the site and has to say what it is
                'label' => (string) $crowdfunding->getTitle(),
                'translation_domain' => false,
                'route' => 'crowdfunding_display',
                'params' => ['slug' => $slug],
                // Read in another language the item is written in that language's url, a campaign page answering in every language the site declares (see CrowdfundingTranslatedLocales)
                'locales' => $this->translatedLocales->forCrowdfunding($crowdfunding),
                'picker_label' => $this->translator->trans('label.crowdfunding', [], 'crowdfunding') . ' - ' . $crowdfunding->getTitle(),
            ];
        }

        return $routes;
    }
}
