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
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Message\LotteryTicketsMessage;
use c975L\CrowdfundingBundle\Service\CrowdfundingBasketItemProvider;
use c975L\CrowdfundingBundle\Service\CrowdfundingCounterpartServiceInterface;
use c975L\CrowdfundingBundle\Service\LotteryServiceInterface;
use c975L\PaymentBundle\Contract\BasketItemProviderInterface;
use c975L\PaymentBundle\Entity\Basket;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The checkout path itself: what PaymentBundle asks this bundle at each step of a basket holding counterparts
class CrowdfundingBasketItemProviderTest extends TestCase
{
    public function testGetKindIsTheKeyPaymentBundleFilesTheseLinesUnder(): void
    {
        $this->assertSame('crowdfunding', $this->createProvider()->getKind());
    }

    public function testItImplementsThePaymentContract(): void
    {
        $this->assertInstanceOf(BasketItemProviderInterface::class, $this->createProvider());
    }

    // The basket stores the line's id as a string, and the counterparts are keyed on an int
    public function testFindItemCastsTheIdTheBasketCarries(): void
    {
        $counterpart = new CrowdfundingCounterpart();

        $counterpartService = $this->createMock(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->expects($this->once())->method('findOneById')->with(12)->willReturn($counterpart);

        $this->assertSame($counterpart, $this->createProvider(counterpartService: $counterpartService)->findItem('12'));
    }

    public function testValidateAdditionAcceptsACounterpartOfARunningCampaign(): void
    {
        $this->assertNull($this->createProvider()->validateAddition($this->createCounterpart(), 1));
    }

    // Zero is the admin's way of taking a counterpart off the page without deleting what has already been ordered
    public function testValidateAdditionRefusesACounterpartLimitedToZero(): void
    {
        $counterpart = $this->createCounterpart()->setLimitedQuantity(0);

        $this->assertSame('label.unavailable', $this->createProvider()->validateAddition($counterpart, 1));
    }

    public function testValidateAdditionRefusesACampaignThatHasNotStarted(): void
    {
        $counterpart = $this->createCounterpart(beginDate: '+2 days', endDate: '+30 days');

        $this->assertSame('label.crowdfunding_not_started', $this->createProvider()->validateAddition($counterpart, 1));
    }

    // The end date is inclusive: the campaign runs to the last second of the day it names
    public function testValidateAdditionAcceptsTheVeryLastDayOfACampaign(): void
    {
        $counterpart = $this->createCounterpart(beginDate: '-30 days', endDate: 'now');

        $this->assertNull($this->createProvider()->validateAddition($counterpart, 1));
    }

    public function testValidateAdditionRefusesAFinishedCampaign(): void
    {
        $counterpart = $this->createCounterpart(beginDate: '-30 days', endDate: '-1 day');

        $this->assertSame('label.crowdfunding_ended', $this->createProvider()->validateAddition($counterpart, 1));
    }

    public function testValidateAdditionRefusesACounterpartWhoseRunIsOut(): void
    {
        $counterpart = $this->createCounterpart()->setLimitedQuantity(10)->setOrderedQuantity(10);

        $this->assertSame('label.no_more_items_available', $this->createProvider()->validateAddition($counterpart, 1));
    }

    public function testValidateAdditionAcceptsACounterpartWithSomeOfItsRunLeft(): void
    {
        $counterpart = $this->createCounterpart()->setLimitedQuantity(10)->setOrderedQuantity(9);

        $this->assertNull($this->createProvider()->validateAddition($counterpart, 1));
    }

    // A basket whose counterparts are all still orderable goes through
    public function testValidateCheckoutAcceptsABasketThatIsStillOrderable(): void
    {
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn($this->createCounterpart());

        $this->assertNull($this->createProvider(counterpartService: $counterpartService)->validateCheckout(new Basket(), [7 => ['quantity' => 2]]));
    }

    // A counterpart deleted while the basket sat in session: there is nothing left to give, and the checkout stops before anything is numbered or charged
    public function testValidateCheckoutRefusesACounterpartThatIsGone(): void
    {
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn(null);

        $this->assertSame('label.unavailable', $this->createProvider(counterpartService: $counterpartService)->validateCheckout(new Basket(), [7 => ['quantity' => 1]]));
    }

    // A basket filled while the campaign was running and paid after it ended - the very case a basket sitting for days produces
    public function testValidateCheckoutRefusesABasketOfACampaignThatEndedMeanwhile(): void
    {
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn($this->createCounterpart(beginDate: '-30 days', endDate: '-1 day'));

        $this->assertSame('label.crowdfunding_ended', $this->createProvider(counterpartService: $counterpartService)->validateCheckout(new Basket(), [7 => ['quantity' => 1]]));
    }

    // The comparison validateAddition() cannot make: it is asked one click at a time, and three clicks on a counterpart with two left each pass on their own
    public function testValidateCheckoutRefusesAQuantityTheRunCannotCover(): void
    {
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn($this->createCounterpart()->setLimitedQuantity(10)->setOrderedQuantity(8));

        $provider = $this->createProvider(counterpartService: $counterpartService);

        $this->assertNull($provider->validateCheckout(new Basket(), [7 => ['quantity' => 2]]), 'Exactly what is left is still orderable.');
        $this->assertSame('label.no_more_items_available', $provider->validateCheckout(new Basket(), [7 => ['quantity' => 3]]));
    }

    // An unlimited counterpart has no run to compare against, and the whole basket goes through whatever its quantity
    public function testValidateCheckoutLeavesAnUnlimitedCounterpartAlone(): void
    {
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn($this->createCounterpart());

        $this->assertNull($this->createProvider(counterpartService: $counterpartService)->validateCheckout(new Basket(), [7 => ['quantity' => 500]]));
    }

    // The basket keeps a copy of the line rather than a reference: a counterpart edited after the basket was filled must not change what the visitor is buying
    public function testToBasketDataCarriesTheCounterpartAndItsCampaign(): void
    {
        $counterpart = $this->createCounterpart()->setTitle('Le tote bag')->setPrice(2500);
        $counterpart->getCrowdfunding()->setTitle('Sauver les chats')->setSlug('sauver-les-chats');

        $data = $this->createProvider()->toBasketData($counterpart, 3);

        $this->assertSame('crowdfunding', $data['type']);
        $this->assertSame(3, $data['quantity']);
        $this->assertSame(7500, $data['total']);
        $this->assertSame(0, $data['totalVat']);
        $this->assertSame('Le tote bag', $data['item']['title']);
        $this->assertSame(['title' => 'Sauver les chats', 'slug' => 'sauver-les-chats', 'image' => null], $data['parent']);
    }

    // Timestamps and the admin who wrote the row have nothing to do in a basket kept in session, and travel to the payment provider with it
    public function testToBasketDataDropsTheAdministrativeFields(): void
    {
        $data = $this->createProvider()->toBasketData($this->createCounterpart(), 1);

        $this->assertArrayNotHasKey('creation', $data['item']);
        $this->assertArrayNotHasKey('modification', $data['item']);
        $this->assertArrayNotHasKey('user', $data['item']);
    }

    // The flag is what decides whether the checkout asks for a delivery address: a counterpart shipped to the contributor needs one, a thank-you email does not
    public function testGetContentFlagsSeparatesAShippedCounterpartFromADigitalOne(): void
    {
        $provider = $this->createProvider();

        $this->assertSame(Basket::CONTENT_FLAG_CF_SHIPPING, $provider->getContentFlags(['item' => ['requiresShipping' => true]]));
        $this->assertSame(Basket::CONTENT_FLAG_CF_DIGITAL, $provider->getContentFlags(['item' => ['requiresShipping' => false]]));
    }

    // A basket filled before the column existed carries no flag at all, and asking for the address is the safe side of the mistake
    public function testGetContentFlagsAsksForAnAddressWhenTheLineSaysNothing(): void
    {
        $this->assertSame(Basket::CONTENT_FLAG_CF_SHIPPING, $this->createProvider()->getContentFlags(['item' => []]));
    }

    // Handed to PaymentBundle, which keeps it on the basket: the payment provider confirms on a request of its own, carrying no session of this contributor
    public function testOnBasketValidatedHandsBackWhatTheContributorTyped(): void
    {
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn(new CrowdfundingCounterpart());

        $basket = new Basket();
        $basket->setEmail('contributeur@example.com');

        $checkoutData = $this->createProvider(counterpartService: $counterpartService)->onBasketValidated(
            $basket,
            [['item' => ['id' => 7], 'quantity' => 2]],
            ['coordinates' => ['contributorName' => 'Camille', 'contributorMessage' => 'Bravo !']],
        );

        $this->assertSame('Camille', $checkoutData['name']);
        $this->assertSame('Bravo !', $checkoutData['message']);
        $this->assertSame('contributeur@example.com', $checkoutData['email']);
        $this->assertSame([7 => 2], $checkoutData['counterparts']);
    }

    // A contributor giving neither name nor message stays anonymous rather than being handed empty strings the pages would print as a blank line
    public function testOnBasketValidatedLeavesAnAnonymousContributorNull(): void
    {
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn(new CrowdfundingCounterpart());

        $checkoutData = $this->createProvider(counterpartService: $counterpartService)->onBasketValidated(new Basket(), [], []);

        $this->assertNull($checkoutData['name']);
        $this->assertNull($checkoutData['message']);
        $this->assertSame([], $checkoutData['counterparts']);
    }

    // A line naming a counterpart deleted while the basket sat in session is dropped rather than carried to a checkout that could not resolve it
    public function testOnBasketValidatedDropsALineWhoseCounterpartIsGone(): void
    {
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn(null);

        $checkoutData = $this->createProvider(counterpartService: $counterpartService)->onBasketValidated(
            new Basket(),
            [['item' => ['id' => 7], 'quantity' => 2]],
            [],
        );

        $this->assertSame([], $checkoutData['counterparts']);
    }

    // The moment the payment is confirmed: the contributor becomes a row, the campaign's total moves, the run is decremented and the lottery tickets are drawn
    public function testOnBasketPaidRegistersTheContributorAndCreditsTheCampaign(): void
    {
        $counterpart = $this->createCounterpart()->setPrice(2500)->setOrderedQuantity(4);
        $crowdfunding = $counterpart->getCrowdfunding();
        $crowdfunding->setAmountAchieved(10000);

        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn($counterpart);

        $lotteryService = $this->createMock(LotteryServiceInterface::class);
        $lotteryService->expects($this->once())->method('generateTicketsForContributor')->with($this->isInstanceOf(CrowdfundingContributor::class), $counterpart, 2);

        $this->createProvider(counterpartService: $counterpartService, entityManager: $this->createEntityManagerAssigningIds(), lotteryService: $lotteryService)->onBasketPaid(
            new Basket(),
            [7 => ['quantity' => 2]],
            ['name' => 'Camille', 'message' => null, 'email' => 'camille@example.com', 'counterparts' => [7 => 2]],
        );

        $this->assertSame(6, $counterpart->getOrderedQuantity());
        $this->assertSame(15000, $crowdfunding->getAmountAchieved());
        $this->assertCount(1, $crowdfunding->getContributors());
    }

    // The message declares an int, and the id is the database's to assign: dispatched before the flush it carried a null, and the whole payment confirmation ended on a TypeError
    public function testOnBasketPaidAnnouncesTheTicketsOnlyOnceTheContributorHasAnId(): void
    {
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn($this->createCounterpart());

        $flushed = false;
        $entityManager = $this->createEntityManagerAssigningIds(function () use (&$flushed): void {
            $flushed = true;
        });

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())->method('dispatch')
            ->with($this->isInstanceOf(LotteryTicketsMessage::class))
            ->willReturnCallback(function (object $message) use (&$flushed): Envelope {
                $this->assertTrue($flushed, 'The tickets were announced before the flush, so the contributor had no id yet.');

                return new Envelope($message);
            });

        $this->createProvider(counterpartService: $counterpartService, entityManager: $entityManager, messageBus: $messageBus)->onBasketPaid(
            new Basket(),
            [7 => ['quantity' => 1]],
            ['name' => null, 'message' => null, 'email' => 'camille@example.com', 'counterparts' => [7 => 1]],
        );
    }

    // A basket PaymentBundle hands over without the data onBasketValidated() built - an order placed before this hook existed - bumps the run and stops there rather than writing a contributor with no email
    public function testOnBasketPaidWritesNoContributorWithoutCheckoutData(): void
    {
        $counterpart = $this->createCounterpart()->setOrderedQuantity(1);

        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn($counterpart);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $this->createProvider(counterpartService: $counterpartService, entityManager: $entityManager, messageBus: $messageBus)->onBasketPaid(new Basket(), [7 => ['quantity' => 3]], []);

        $this->assertSame(4, $counterpart->getOrderedQuantity());
    }

    // Doctrine assigns the identifier at flush time, and nothing else here does: without it every contributor keeps the null the constructor left, which is exactly what the message refuses
    private function createEntityManagerAssigningIds(?callable $onFlush = null): EntityManagerInterface
    {
        $persisted = [];

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->method('flush')->willReturnCallback(static function () use (&$persisted, $onFlush): void {
            foreach ($persisted as $entity) {
                if ($entity instanceof CrowdfundingContributor && null === $entity->getId()) {
                    new \ReflectionProperty(CrowdfundingContributor::class, 'id')->setValue($entity, 1);
                }
            }

            if (null !== $onFlush) {
                $onFlush();
            }
        });

        return $entityManager;
    }

    private function createCounterpart(string $beginDate = '-10 days', string $endDate = '+10 days'): CrowdfundingCounterpart
    {
        $crowdfunding = new Crowdfunding();
        $crowdfunding->setBeginDate(new \DateTime($beginDate));
        $crowdfunding->setEndDate(new \DateTime($endDate));
        $crowdfunding->setAmountAchieved(0);

        $counterpart = new CrowdfundingCounterpart();
        $counterpart->setCrowdfunding($crowdfunding);
        $counterpart->setPrice(1000);
        $counterpart->setLimitedQuantity(null);

        return $counterpart;
    }

    private function createProvider(
        ?CrowdfundingCounterpartServiceInterface $counterpartService = null,
        ?EntityManagerInterface $entityManager = null,
        ?MessageBusInterface $messageBus = null,
        ?LotteryServiceInterface $lotteryService = null,
    ): CrowdfundingBasketItemProvider {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $bus = $messageBus ?? $this->createStub(MessageBusInterface::class);
        if (null === $messageBus) {
            $bus->method('dispatch')->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));
        }

        return new CrowdfundingBasketItemProvider(
            $counterpartService ?? $this->createStub(CrowdfundingCounterpartServiceInterface::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $bus,
            $translator,
            $lotteryService ?? $this->createStub(LotteryServiceInterface::class),
        );
    }
}
