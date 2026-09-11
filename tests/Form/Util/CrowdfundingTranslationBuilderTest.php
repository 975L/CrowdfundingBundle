<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Form\Util;

use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Form\CrowdfundingCounterpartType;
use c975L\CrowdfundingBundle\Form\Util\CrowdfundingTranslationBuilder;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

// A tier on the campaign's language screen: its three texts and nothing else, written to the translation table rather than over the tier
class CrowdfundingTranslationBuilderTest extends TestCase
{
    /** @var list<array{0: object, 1: string, 2: array<string, mixed>}> */
    private array $staged = [];

    private function createFormFactory(): FormFactoryInterface
    {
        $crowdfundingTranslator = $this->createStub(CrowdfundingTranslator::class);
        $crowdfundingTranslator->method('promptValues')->willReturn(['title' => '[Palier or]', 'description' => null, 'expectedDelivery' => '[Décembre]']);
        $crowdfundingTranslator->method('stage')->willReturnCallback(function (object $row, string $locale, array $values): void {
            $this->staged[] = [$row, $locale, $values];
        });

        return Forms::createFormFactoryBuilder()
            ->addType(new CrowdfundingCounterpartType(new CrowdfundingTranslationBuilder($crowdfundingTranslator)))
            ->getFormFactory();
    }

    private function counterpart(): CrowdfundingCounterpart
    {
        $counterpart = new CrowdfundingCounterpart();
        $counterpart->setTitle('Palier or');
        new \ReflectionProperty(CrowdfundingCounterpart::class, 'id')->setValue($counterpart, 7);

        return $counterpart;
    }

    // A price, a quantity and a slug are not the language's to change, and the texts offered are held apart from the row
    public function testALanguageScreenOffersTheTextsAloneUnmapped(): void
    {
        $form = $this->createFormFactory()->create(CrowdfundingCounterpartType::class, $this->counterpart(), ['translation_locale' => 'en']);

        $this->assertSame(['id', 'title', 'description', 'expectedDelivery'], array_keys($form->all()));
        foreach ($form->all() as $child) {
            $this->assertFalse($child->getConfig()->getMapped());
        }
        $this->assertSame('[Palier or]', $form->get('title')->getData());
    }

    // What is typed goes to the translator, and the tier keeps the words it was written with
    public function testWhatIsTypedIsStagedAndLeavesTheTierAlone(): void
    {
        $counterpart = $this->counterpart();
        $form = $this->createFormFactory()->create(CrowdfundingCounterpartType::class, $counterpart, ['translation_locale' => 'en']);

        $form->submit(['id' => '7', 'title' => 'Gold tier', 'description' => '', 'expectedDelivery' => 'December']);

        $this->assertSame('Palier or', $counterpart->getTitle());
        $this->assertSame([[$counterpart, 'en', ['title' => 'Gold tier', 'description' => null, 'expectedDelivery' => 'December']]], $this->staged);
    }
}
