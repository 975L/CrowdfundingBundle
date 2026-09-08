<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Service;

use c975L\CrowdfundingBundle\Service\CrowdfundingShowcaseProvider;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Service\BlockFixtureMediaAttacher;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class CrowdfundingShowcaseProviderTest extends TestCase
{
    // The slider alone: the counterparts and the lottery draw PaymentBundle's basket around a tier a visitor could click
    public function testOnlyTheSliderIsShownInThePicker(): void
    {
        $showcases = $this->createProvider(3)->getShowcases();

        $this->assertCount(1, $showcases);
        $this->assertSame('crowdfunding_slider', reset($showcases)['kind']);
    }

    // The picker's card is named and described in this bundle's own catalogue, the same keys the "ui.block" tag declares
    public function testTheCardIsNamedInTheCrowdfundingCatalogue(): void
    {
        $showcases = $this->createProvider(3)->getShowcases();

        $this->assertArrayHasKey('label.block_slider|crowdfunding', $showcases);
        $this->assertSame('label.block_slider_description|crowdfunding', $showcases['label.block_slider|crowdfunding']['description']);
    }

    // A slider with no image in it shows nothing worth looking at: a site declaring no placeholder gets no card rather than an empty frame
    public function testASiteWithoutAnyPlaceholderImageGetsNoCard(): void
    {
        $this->assertSame([], $this->createProvider(0)->getShowcases());
    }

    // The attacher stops answering once its own images run out, and the showcase renders what it got rather than looping
    public function testTheShowcaseRendersTheImagesTheSiteActuallyHas(): void
    {
        $rendered = $this->createProvider(2)->getShowcases();

        $this->assertStringContainsString('2 medias', reset($rendered)['variants']['']);
    }

    private function createProvider(int $placeholders): CrowdfundingShowcaseProvider
    {
        $attacher = $this->createStub(BlockFixtureMediaAttacher::class);
        $attacher->method('nextPlaceholderImage')->willReturnOnConsecutiveCalls(
            ...array_merge(array_fill(0, $placeholders, new Media()), [null])
        );

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static fn (string $name, array $context = []): string => \count($context['media']) . ' medias'
        );

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = [], ?string $domain = null): string => $id . '|' . $domain
        );

        return new CrowdfundingShowcaseProvider($twig, $translator, $attacher);
    }
}
