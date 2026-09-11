<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Service;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\CrowdfundingBundle\Entity\LotteryPrize;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslator;
use c975L\UiBundle\Service\ContentTranslator;
use PHPUnit\Framework\TestCase;

class CrowdfundingTranslatorTest extends TestCase
{
    // A site declaring a single language reads nothing at all: the row answers what it was written with
    public function testARowIsLeftAloneWhenNothingIsTranslated(): void
    {
        $campaign = $this->createCampaign();

        $this->translator(active: false)->apply([$campaign]);

        $this->assertSame('Le toit de l\'école', $campaign->getTitle());
        $this->assertSame(['fr'], $this->translator(active: false)->translatedLocales($campaign));
    }

    // What the language being read says, laid over the row for this render and no longer than that
    public function testTheTranslatedTextsAreTheOnesRead(): void
    {
        $campaign = $this->createCampaign();

        $this->translator(translated: ['title' => 'The school roof'])->apply([$campaign]);

        $this->assertSame('The school roof', $campaign->getTitle());
        // Untranslated, so the text the row was written with is what is read - an absent field, not a null one
        $this->assertSame('Cent tuiles à remplacer.', $campaign->getDescription());
    }

    // What tells an untouched field from a written one, whatever language is being rendered
    public function testTheRowStillHandsBackTheTextItWasWrittenWith(): void
    {
        $campaign = $this->createCampaign();

        $this->translator(translated: ['title' => 'The school roof'])->apply([$campaign]);

        $this->assertSame('Le toit de l\'école', $campaign->getUntranslated('title'));
    }

    // A row answers in the languages its own name was written in, its own included: what a localised url is gated on
    public function testARowAnswersInTheLanguagesItsOwnNameWasWrittenIn(): void
    {
        $campaign = $this->createCampaign();

        $this->assertSame(['fr', 'en'], $this->translator(values: ['en' => ['title' => 'The school roof']])->translatedLocales($campaign));
        $this->assertSame(['fr'], $this->translator(values: ['en' => ['description' => 'A solid top.']])->translatedLocales($campaign));
    }

    // Each kind is named apart, so a campaign 12 and a tier 12 never read each other's words
    public function testEachKindIsNamedApart(): void
    {
        $translator = $this->translator();

        $this->assertSame(CrowdfundingTranslator::OWNER_CAMPAIGN, $translator->owner(new Crowdfunding()));
        $this->assertSame(CrowdfundingTranslator::OWNER_COUNTERPART, $translator->owner(new CrowdfundingCounterpart()));
        $this->assertSame(CrowdfundingTranslator::OWNER_NEWS, $translator->owner(new CrowdfundingNews()));
        $this->assertSame(CrowdfundingTranslator::OWNER_PRIZE, $translator->owner(new LotteryPrize()));
    }

    // A row never saved has no id: nothing to hang a translation on, and nothing to read
    public function testARowWithNoIdReadsNothing(): void
    {
        $contentTranslator = $this->createMock(ContentTranslator::class);
        $contentTranslator->method('isActive')->willReturn(true);
        $contentTranslator->expects($this->never())->method('all');

        $this->assertSame([], new CrowdfundingTranslator($contentTranslator, new SiteLocales(['fr', 'en'], 'fr'))->all(new Crowdfunding()));
    }

    private function createCampaign(): Crowdfunding
    {
        $campaign = new Crowdfunding();
        $campaign->setTitle('Le toit de l\'école')->setDescription('Cent tuiles à remplacer.');
        new \ReflectionProperty(Crowdfunding::class, 'id')->setValue($campaign, 12);

        return $campaign;
    }

    /**
     * @param array<string, string|null>                $translated what the language being read says
     * @param array<string, array<string, string|null>> $values     locale => field => value, what each language says
     */
    private function translator(bool $active = true, array $translated = [], array $values = []): CrowdfundingTranslator
    {
        $contentTranslator = $this->createStub(ContentTranslator::class);
        $contentTranslator->method('isActive')->willReturn($active);
        $contentTranslator->method('getTranslatableLocales')->willReturn($active ? ['en'] : []);
        $contentTranslator->method('translate')->willReturn($translated);
        $contentTranslator->method('values')->willReturnCallback(
            static fn (string $owner, int $id, string $locale): array => $values[$locale] ?? []
        );

        return new CrowdfundingTranslator($contentTranslator, new SiteLocales(['fr', 'en'], 'fr'));
    }
}
