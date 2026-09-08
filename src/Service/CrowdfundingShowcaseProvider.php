<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use c975L\UiBundle\Contract\GalleryShowcaseProviderInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Service\BlockFixtureMediaAttacher;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

// Shows this bundle's slider in a block showcase (see UiBundle's GalleryShowcaseRegistry), no kind of it fitting BlockFixtureProviderInterface since their templates resolve a live campaign a showcase has none of; the counterparts and the lottery are left out, both drawing PaymentBundle's basket around a tier a visitor could click
class CrowdfundingShowcaseProvider implements GalleryShowcaseProviderInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly BlockFixtureMediaAttacher $mediaAttacher,
    ) {
    }

    public function getShowcases(): array
    {
        $medias = $this->placeholderMedias(3);

        // A slider with no image in it shows nothing worth looking at, so a site declaring no placeholder image gets no showcase rather than an empty frame
        if ([] === $medias) {
            return [];
        }

        return [
            $this->label('label.block_slider') => [
                'description' => $this->label('label.block_slider_description'),
                'kind' => 'crowdfunding_slider',
                'variants' => ['' => $this->twig->render('@c975LUi/components/Slider/Slider.html.twig', [
                    'media' => $medias,
                    'id' => 'showcase-crowdfunding-slider',
                    'class' => 'img-500',
                    'duration' => 3500,
                ])],
            ],
        ];
    }

    /** @return list<Media> */
    private function placeholderMedias(int $count): array
    {
        $medias = [];
        for ($i = 0; $i < $count; ++$i) {
            $media = $this->mediaAttacher->nextPlaceholderImage();
            if (null === $media) {
                break;
            }

            $medias[] = $media;
        }

        return $medias;
    }

    private function label(string $key): string
    {
        return $this->translator->trans($key, [], 'crowdfunding');
    }
}
