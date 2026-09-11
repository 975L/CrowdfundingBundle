<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Form;

use c975L\CrowdfundingBundle\Form\LotteryPrizeType;
use c975L\CrowdfundingBundle\Form\LotteryType;
use c975L\CrowdfundingBundle\Form\LotteryVideoType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

// The lottery a campaign may run, edited from the campaign's own screen
class LotteryTypeTest extends FormFieldsTestCase
{
    // The identifier is the lottery's public url, drawn by the listener on the first save
    public function testTheIdentifierIsShownButNeverTyped(): void
    {
        $fields = $this->buildFields(new LotteryType());

        $this->assertTrue($fields['identifier']['options']['attr']['readonly']);
        $this->assertFalse($fields['identifier']['options']['required']);
    }

    // Prizes and videos are rows an admin adds and removes in place, and the collection has to own them: without by_reference at false, a row added on screen never reaches the setter
    public function testThePrizesAndVideosAreEditedInPlace(): void
    {
        $fields = $this->buildFields(new LotteryType());

        foreach (['prizes' => LotteryPrizeType::class, 'videos' => LotteryVideoType::class] as $name => $entryType) {
            $this->assertSame(CollectionType::class, $fields[$name]['type']);
            $this->assertSame($entryType, $fields[$name]['options']['entry_type']);
            $this->assertTrue($fields[$name]['options']['allow_add']);
            $this->assertTrue($fields[$name]['options']['allow_delete']);
            $this->assertFalse($fields[$name]['options']['by_reference']);
        }
    }

    // The draw date is picked in one field rather than in six selects
    public function testTheDrawDateIsPickedAsASingleText(): void
    {
        $fields = $this->buildFields(new LotteryType());

        $this->assertSame(DateTimeType::class, $fields['drawDate']['type']);
        $this->assertSame('single_text', $fields['drawDate']['options']['widget']);
    }

    // A campaign has the lottery switched off until an admin turns it on
    public function testTheLotteryIsSwitchedOnByACheckbox(): void
    {
        $fields = $this->buildFields(new LotteryType());

        $this->assertFalse($fields['isActive']['options']['required'], 'A required checkbox cannot be unticked.');
    }

    // A draw says nothing of its own in any language: its language screen offers the prizes alone, through their own type, and neither adds nor removes one
    public function testALanguageScreenOffersThePrizesAlone(): void
    {
        $fields = $this->buildFields(new LotteryType(), ['translation_locale' => 'en']);

        $this->assertSame(['prizes'], array_keys($fields));
        $this->assertSame(['translation_locale' => 'en'], $fields['prizes']['options']['entry_options']);
        $this->assertFalse($fields['prizes']['options']['allow_add']);
        $this->assertFalse($fields['prizes']['options']['allow_delete']);
    }
}
