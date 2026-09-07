<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Form;

use c975L\CrowdfundingBundle\Form\CrowdfundingCounterpartMediaType;
use c975L\CrowdfundingBundle\Form\CrowdfundingCounterpartType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;

// What a campaign offers in return for a contribution, edited as a collection from the campaign's own screen
class CrowdfundingCounterpartTypeTest extends FormFieldsTestCase
{
    public function testTheSlugIsShownButNeverTyped(): void
    {
        $fields = $this->buildFields(new CrowdfundingCounterpartType());

        $this->assertTrue($fields['slug']['options']['attr']['readonly'], 'The slug is rebuilt from the title by the listener on every save.');
        $this->assertSame('', $fields['slug']['options']['empty_data']);
    }

    // The run already taken is what validateAddition() compares the limit against: an admin editing it by hand would let a counterpart be oversold
    public function testTheOrderedQuantityIsShownButNeverTyped(): void
    {
        $fields = $this->buildFields(new CrowdfundingCounterpartType());

        $this->assertTrue($fields['orderedQuantity']['options']['attr']['readonly']);
    }

    // The price is an amount in cents, as everywhere in the ecosystem, and the field divides it for the admin
    public function testThePriceIsTypedInEurosAndStoredInCents(): void
    {
        $fields = $this->buildFields(new CrowdfundingCounterpartType());

        $this->assertSame(MoneyType::class, $fields['price']['type']);
        $this->assertSame(100, $fields['price']['options']['divisor']);
    }

    // Zero to ten, the same range the entity's setter holds: a counterpart entitling to none is simply not a lottery counterpart
    public function testTheLotteryTicketsAreOfferedFromZeroToTen(): void
    {
        $fields = $this->buildFields(new CrowdfundingCounterpartType());

        $this->assertSame(ChoiceType::class, $fields['lotteryTickets']['type']);
        $this->assertSame(range(0, 10), array_values($fields['lotteryTickets']['options']['choices']));
    }

    // The currency is filled in rather than asked for: a campaign whose counterparts each carried their own would price a basket in two currencies
    public function testTheCurrencyDefaultsToEuros(): void
    {
        $fields = $this->buildFields(new CrowdfundingCounterpartType());

        $this->assertSame('eur', $fields['currency']['options']['empty_data']);
    }

    // The picture is edited in place, the counterpart itself being a row of a collection
    public function testThePictureIsEditedInsideTheCounterpart(): void
    {
        $fields = $this->buildFields(new CrowdfundingCounterpartType());

        $this->assertSame(CrowdfundingCounterpartMediaType::class, $fields['media']['type']);
        $this->assertFalse($fields['media']['options']['label']);
    }
}
