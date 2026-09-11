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
use c975L\CrowdfundingBundle\Form\Util\CrowdfundingTranslationBuilder;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class LotteryPrizeTypeTest extends FormFieldsTestCase
{
    // Five ranks, the same range the entity's setter holds and the drawing route accepts
    public function testTheRankIsPickedFromOneToFive(): void
    {
        $fields = $this->buildFields(new LotteryPrizeType());

        $this->assertSame(ChoiceType::class, $fields['rank']['type']);
        $this->assertSame([1, 2, 3, 4, 5], array_values($fields['rank']['options']['choices']));
        $this->assertTrue($fields['rank']['options']['required'], 'A prize with no rank could never be drawn, the url naming one.');
    }

    // The prize's own description is what the winner's email prints
    public function testAPrizeCarriesATitleAndAnOptionalDescription(): void
    {
        $fields = $this->buildFields(new LotteryPrizeType());

        $this->assertTrue($fields['title']['options']['required']);
        $this->assertFalse($fields['description']['options']['required']);
    }

    // A language screen offers the prize's two texts through the shared builder, and never its rank, the same in every language
    public function testALanguageScreenOffersTheTextsAlone(): void
    {
        $translationBuilder = $this->createMock(CrowdfundingTranslationBuilder::class);
        $translationBuilder->expects($this->once())->method('build')->with(
            $this->anything(),
            'en',
            $this->callback(static fn (array $fields): bool => ['title', 'description'] === array_keys($fields)),
        );

        $this->assertSame([], $this->buildFields(new LotteryPrizeType($translationBuilder), ['translation_locale' => 'en']));
    }
}
