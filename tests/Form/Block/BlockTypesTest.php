<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Form\Block;

use c975L\CrowdfundingBundle\Form\Block\CampaignsBlockType;
use c975L\CrowdfundingBundle\Form\Block\CounterpartsBlockType;
use c975L\CrowdfundingBundle\Form\Block\LotteryBlockType;
use c975L\CrowdfundingBundle\Form\Block\SliderBlockType;
use c975L\CrowdfundingBundle\Tests\Form\FormFieldsTestCase;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;

// The block kinds this bundle registers: what they let an editor set, and what they deliberately do not
class BlockTypesTest extends FormFieldsTestCase
{
    /** @return iterable<string, array{AbstractType}> */
    public static function blockTypes(): iterable
    {
        yield 'slider' => [new SliderBlockType()];
        yield 'counterparts' => [new CounterpartsBlockType()];
        yield 'lottery' => [new LotteryBlockType()];
        yield 'campaigns' => [new CampaignsBlockType(new BlockAnchorSlugger(new AsciiSlugger()))];
    }

    // BlockType translates the embedded data form in the "ui" domain: a type forgetting this renders every one of its labels raw
    #[DataProvider('blockTypes')]
    public function testEachTypeIsTranslatedInThisBundlesCatalogue(AbstractType $type): void
    {
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $this->assertSame('crowdfunding', $resolver->resolve()['translation_domain']);
    }

    // Nothing on these blocks names the campaign: it is read from the route being rendered (see CrowdfundingBlockExtension), so a block cannot end up showing another campaign's funding
    #[DataProvider('blockTypes')]
    public function testNoTypeAsksWhichCampaignToShow(AbstractType $type): void
    {
        $fields = array_keys($this->buildFields($type));

        $this->assertSame([], array_intersect($fields, ['crowdfunding', 'crowdfundingId', 'slug', 'campaign']));
    }

    // The tiers are the campaign's own rows: the block says how to lay them out, never what they are
    public function testTheCounterpartsBlockOnlyHoldsLayout(): void
    {
        $fields = $this->buildFields(new CounterpartsBlockType());

        // No "columns" any more: the tiers are read as rows, one open at a time, so there is no column count to choose
        $this->assertSame(['title', 'highlightedSlug', 'lowStockThreshold'], array_keys($fields));
    }

    // The prizes are read from the LotteryPrize rows the draw is actually made on: typing them here would let the page and the draw disagree
    public function testTheLotteryBlockHoldsNoPrize(): void
    {
        $fields = array_keys($this->buildFields(new LotteryBlockType()));

        $this->assertSame(['title', 'intro', 'showTicketsCount'], $fields);
    }

    // The medias are the campaign's own: an editor laying them out again would have to upload them twice
    public function testTheSliderBlockHoldsNoMedia(): void
    {
        $fields = array_keys($this->buildFields(new SliderBlockType()));

        $this->assertSame(['duration', 'ratio'], $fields);
    }

    // The one kind placed on an ordinary page, hence the only one carrying a head: the section it opens is written here rather than in a "text_section" laid above it, the two having to be moved, hidden and translated together
    public function testTheCampaignsBlockCarriesItsOwnSectionHead(): void
    {
        $fields = array_keys($this->buildFields(new CampaignsBlockType(new BlockAnchorSlugger(new AsciiSlugger()))));

        $this->assertSame(['anchor', 'eyebrow', 'title', 'content', 'linkLabel', 'linkUrl', 'max', 'background'], $fields);
    }
}
