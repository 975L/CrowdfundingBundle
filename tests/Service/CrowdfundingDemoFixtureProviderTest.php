<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Service;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributorCounterpart;
use c975L\CrowdfundingBundle\Service\CrowdfundingDemoFixtureProvider;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use c975L\UiBundle\Service\DemoFixtureTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// The campaign a demo site is running, with its tiers and the people who already chose one
class CrowdfundingDemoFixtureProviderTest extends TestCase
{
    private function provider(): CrowdfundingDemoFixtureProvider
    {
        // Each language answers with words of its own, so a key read in English is a translation of the French one
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key, array $parameters = [], ?string $domain = null, ?string $locale = null): string => ($locale ?? 'fr') . ':' . $key);

        // No placeholder declared, so no file is copied and the campaign is seeded without pictures
        return new CrowdfundingDemoFixtureProvider($translator, new DemoFixtureTranslator($translator, ['fr', 'en', 'es'], 'fr'), new PlaceholderMediaRegistry(), sys_get_temp_dir());
    }

    /** @return list<object> */
    private function fixtures(): array
    {
        return iterator_to_array($this->provider()->getDemoFixtures(), false);
    }

    // The campaign first, then its contributors, then the tiers they chose: each yielded after what it points at
    public function testItYieldsTheCampaignThenItsContributorsThenTheirTiers(): void
    {
        $fixtures = $this->fixtures();

        $this->assertInstanceOf(Crowdfunding::class, $fixtures[0]);
        $this->assertCount(8, array_filter($fixtures, static fn (object $row): bool => $row instanceof CrowdfundingContributor));
        $this->assertCount(8, array_filter($fixtures, static fn (object $row): bool => $row instanceof CrowdfundingContributorCounterpart));
        $this->assertInstanceOf(CrowdfundingContributorCounterpart::class, end($fixtures));
    }

    // Open to visitors, with three tiers and a news
    public function testTheCampaignIsOpenWithThreeTiersAndANews(): void
    {
        $crowdfunding = $this->fixtures()[0];

        $this->assertFalse($crowdfunding->isHidden());
        $this->assertCount(3, $crowdfunding->getCounterparts());
        $this->assertCount(1, $crowdfunding->getNews());
        $this->assertSame(300000, $crowdfunding->getAmountGoal());
    }

    // One lottery riding the campaign's cascade, not drawn yet and with a single prize, so the lottery screens and the draw video's guided project have something to open
    public function testTheCampaignRunsOneLotteryWithOnePrize(): void
    {
        $lotteries = $this->fixtures()[0]->getLotteries();

        $this->assertCount(1, $lotteries);
        $this->assertCount(1, $lotteries->first()->getPrizes());
        $this->assertSame('fr:label.sample_prize_title', $lotteries->first()->getPrizes()->first()->getTitle());
    }

    // What the campaign has reached and what each tier has sold answer for the contributions, the way a paid basket bumps both
    public function testTheAmountReachedAndTheTiersSoldAnswerForTheContributions(): void
    {
        $crowdfunding = $this->fixtures()[0];

        $sum = 0;
        $sold = 0;
        foreach ($crowdfunding->getCounterparts() as $counterpart) {
            $sum += $counterpart->getPrice() * $counterpart->getOrderedQuantity();
            $sold += $counterpart->getOrderedQuantity();
        }

        $this->assertSame(69000, $crowdfunding->getAmountAchieved());
        $this->assertSame($crowdfunding->getAmountAchieved(), $sum);
        $this->assertSame(13, $sold);
    }

    // The campaign said in English and in Spanish once it has an identifier, its rich fields in the box they are stored in
    public function testTheCampaignIsTranslatedOnTheSecondPass(): void
    {
        $provider = $this->provider();
        $crowdfunding = iterator_to_array($provider->getDemoFixtures(), false)[0];
        new \ReflectionProperty(Crowdfunding::class, 'id')->setValue($crowdfunding, 1);

        $translations = array_filter(
            iterator_to_array($provider->getLinkedDemoFixtures(), false),
            static fn (Translation $translation): bool => 1 === $translation->getOwnerId() && 'crowdfunding_campaign' === $translation->getOwnerType(),
        );

        // Three fields, two languages
        $this->assertCount(6, $translations);
        $values = array_map(static fn (Translation $translation): ?string => $translation->getValue(), array_values($translations));
        $this->assertContains('en:label.sample_crowdfunding_title', $values);
        $this->assertContains('<div>es:label.sample_crowdfunding_description</div>', $values);
    }
}
