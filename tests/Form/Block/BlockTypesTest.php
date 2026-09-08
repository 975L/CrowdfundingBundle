<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Form\Block;

use c975L\CrowdfundingBundle\Form\Block\CounterpartsBlockType;
use c975L\CrowdfundingBundle\Form\Block\LotteryBlockType;
use c975L\CrowdfundingBundle\Form\Block\SliderBlockType;
use c975L\CrowdfundingBundle\Tests\Form\FormFieldsTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The three kinds composing a campaign page: what they let an editor set, and what they deliberately do not
class BlockTypesTest extends FormFieldsTestCase
{
    /** @return iterable<string, array{AbstractType}> */
    public static function blockTypes(): iterable
    {
        yield 'slider' => [new SliderBlockType()];
        yield 'counterparts' => [new CounterpartsBlockType()];
        yield 'lottery' => [new LotteryBlockType()];
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

        $this->assertSame(['title', 'highlightedSlug', 'lowStockThreshold', 'columns'], array_keys($fields));
        $this->assertSame(['2' => 2, '3' => 3, '4' => 4], $fields['columns']['options']['choices']);
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
}
