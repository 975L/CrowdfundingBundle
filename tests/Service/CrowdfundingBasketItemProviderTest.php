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
use c975L\CrowdfundingBundle\Entity\LotteryPrize;
use c975L\CrowdfundingBundle\Entity\LotteryTicket;
use c975L\CrowdfundingBundle\Message\CrowdfundingContributionMessage;
use c975L\CrowdfundingBundle\Message\LotteryTicketsMessage;
use c975L\CrowdfundingBundle\Repository\CrowdfundingContributorRepository;
use c975L\CrowdfundingBundle\Repository\CrowdfundingCounterpartRepository;
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\CrowdfundingBundle\Repository\LotteryPrizeRepository;
use c975L\CrowdfundingBundle\Repository\LotteryTicketRepository;
use c975L\CrowdfundingBundle\Service\CrowdfundingBasketItemProvider;
use c975L\CrowdfundingBundle\Service\CrowdfundingCounterpartServiceInterface;
use c975L\CrowdfundingBundle\Service\LotteryServiceInterface;
use c975L\PaymentBundle\Contract\BasketItemProviderInterface;
use c975L\PaymentBundle\Contract\RefundableBasketItemProviderInterface;
use c975L\PaymentBundle\Contract\ShippingBasketItemProviderInterface;
use c975L\PaymentBundle\Entity\Basket;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The checkout path itself: what PaymentBundle asks this bundle at each step of a basket holding counterparts
class CrowdfundingBasketItemProviderTest extends TestCase
{
    /** @var list<array{int, int}> */
    private array $counted = [];

    /** @var list<array{int, int}> */
    private array $forced = [];

    /** @var list<int> */
    private array $credited = [];

    /** @var list<string> */
    private array $logged = [];

    /** @var list<int> */
    private array $released = [];

    /** @var list<array{int, int}> */
    private array $returned = [];

    /** @var list<CrowdfundingContributor> */
    private array $basketContributors = [];

    /** @var list<LotteryTicket> */
    private array $contributorTickets = [];

    /** @var list<LotteryPrize> */
    private array $wonPrizes = [];

    private bool $claimAnswers = true;

    public function testGetKindIsTheKeyPaymentBundleFilesTheseLinesUnder(): void
    {
        $this->assertSame('crowdfunding', $this->createProvider()->getKind());
    }

    public function testItImplementsThePaymentContract(): void
    {
        $this->assertInstanceOf(BasketItemProviderInterface::class, $this->createProvider());
        $this->assertInstanceOf(RefundableBasketItemProviderInterface::class, $this->createProvider());
    }

    // A running campaign offering a posted counterpart keeps PaymentBundle warning about an empty delivery grid
    public function testItDeclaresParcelsToShipWhenACounterpartIsPosted(): void
    {
        $provider = $this->createProvider(hasShippedCounterpart: true);

        $this->assertInstanceOf(ShippingBasketItemProviderInterface::class, $provider);
        $this->assertTrue($provider->shipsParcels());
    }

    // A site whose counterparts are all digital is not told to fill a delivery grid
    public function testItDeclaresNoParcelWhenEveryCounterpartIsDigital(): void
    {
        $this->assertFalse($this->createProvider(hasShippedCounterpart: false)->shipsParcels());
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

    // Both columns are nullable and a campaign reaching the shop without them used to reach ->format() on null: the button reads as open, the click is a 500
    public function testValidateAdditionRefusesACampaignWithNoDates(): void
    {
        $counterpart = $this->createCounterpart();
        $counterpart->getCrowdfunding()->setBeginDate(null);
        $counterpart->getCrowdfunding()->setEndDate(null);

        $this->assertSame('label.unavailable', $this->createProvider()->validateAddition($counterpart, 1));
    }

    public function testValidateAdditionRefusesACampaignThatHasNotStarted(): void
    {
        $counterpart = $this->createCounterpart(beginDate: '+2 days', endDate: '+30 days');

        $this->assertSame('label.crowdfunding_not_started', $this->createProvider()->validateAddition($counterpart, 1));
    }

    // A campaign not opened yet is not contributed to: its page answers 404, and a tier reached from anywhere else must not go into a basket either
    public function testValidateAdditionRefusesAHiddenCampaign(): void
    {
        $counterpart = $this->createCounterpart();
        $counterpart->getCrowdfunding()->setHidden(true);

        $this->assertSame('label.unavailable', $this->createProvider()->validateAddition($counterpart, 1));
    }

    // Same for a campaign sitting in the recycle bin, whose page answers 410
    public function testValidateAdditionRefusesATrashedCampaign(): void
    {
        $counterpart = $this->createCounterpart();
        $counterpart->getCrowdfunding()->setIsDeleted(true);

        $this->assertSame('label.unavailable', $this->createProvider()->validateAddition($counterpart, 1));
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

    // A line carrying no flag reads as the entity's default: a counterpart is not posted unless flagged so
    public function testGetContentFlagsFollowsTheEntityDefaultWhenTheLineSaysNothing(): void
    {
        $this->assertSame(Basket::CONTENT_FLAG_CF_DIGITAL, $this->createProvider()->getContentFlags(['item' => []]));
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

        $this->assertSame([[7, 2]], $this->counted);
        $this->assertSame([5000], $this->credited);
        $this->assertCount(1, $crowdfunding->getContributors());
    }

    // Two payments at the same second both read the same count: the database is what says whether the run still held this one
    public function testACounterpartIsCountedByTheDatabase(): void
    {
        $this->assertTrue($this->paidOnce(claimAnswers: true));
        $this->assertSame([], $this->forced);
        $this->assertSame([], $this->logged);
    }

    // The run went under this order while it was being paid: the customer has paid, so the contribution stands and the count tells the truth, and the shop is told in red
    public function testAnOversoldCounterpartIsCountedAllTheSameAndFlagged(): void
    {
        $this->assertTrue($this->paidOnce(claimAnswers: false));
        $this->assertSame([[7, 2]], $this->forced);
        $this->assertCount(1, $this->logged);
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

        $dispatched = [];
        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$flushed, &$dispatched): Envelope {
                $this->assertTrue($flushed, 'A message was sent before the flush, so the contributor had no id yet.');
                $dispatched[] = $message::class;

                return new Envelope($message);
            });

        $this->createProvider(counterpartService: $counterpartService, entityManager: $entityManager, messageBus: $messageBus)->onBasketPaid(
            new Basket(),
            [7 => ['quantity' => 1]],
            ['name' => null, 'message' => null, 'email' => 'camille@example.com', 'counterparts' => [7 => 1]],
        );

        $this->assertSame([LotteryTicketsMessage::class, CrowdfundingContributionMessage::class], $dispatched);
    }

    // A basket PaymentBundle hands over without the data onBasketValidated() built - an order placed before this hook existed - counts the run and the campaign, writes no contributor, and still thanks the buyer
    public function testOnBasketPaidWritesNoContributorWithoutCheckoutData(): void
    {
        $counterpart = $this->createCounterpart()->setOrderedQuantity(1);

        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn($counterpart);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())->method('dispatch')->with($this->isInstanceOf(CrowdfundingContributionMessage::class))->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $this->createProvider(counterpartService: $counterpartService, entityManager: $entityManager, messageBus: $messageBus)->onBasketPaid(new Basket(), [7 => ['quantity' => 3]], []);

        $this->assertSame([[7, 3]], $this->counted);
        $this->assertSame([3000], $this->credited);
    }

    // A basket paid in test mode counts nothing real: no run, no total, no contributor, no ticket, no e-mail
    public function testATestBasketCountsNothing(): void
    {
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn($this->createCounterpart());

        $lotteryService = $this->createMock(LotteryServiceInterface::class);
        $lotteryService->expects($this->never())->method('generateTicketsForContributor');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $this->createProvider(counterpartService: $counterpartService, messageBus: $messageBus, lotteryService: $lotteryService)->onBasketPaid(
            new Basket()->setTestMode(true),
            [7 => ['quantity' => 1, 'total' => 1000]],
            ['name' => null, 'message' => null, 'email' => 'item-1@example.com', 'counterparts' => [7 => 1]],
        );

        $this->assertSame([], $this->counted);
        $this->assertSame([], $this->credited);
    }

    // The campaign counts the line total the basket froze, minus its share of a promotional code - never the price of the day
    public function testTheAmountCountedIsTheFrozenTotalMinusTheDiscountShare(): void
    {
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn($this->createCounterpart()->setPrice(9999));

        $basket = new Basket()->setTotal(10000)->setDiscountKind(Basket::CODE_KIND_DISCOUNT)->setDiscountAmount(5000);
        $this->createProvider(counterpartService: $counterpartService, entityManager: $this->createEntityManagerAssigningIds())->onBasketPaid($basket, [7 => ['quantity' => 2, 'total' => 4000]], []);

        $this->assertSame([2000], $this->credited);
    }

    // A gift card pays the order, it does not discount it: the campaign counts the whole line
    public function testAGiftCardTakesNothingOffTheAmountCounted(): void
    {
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn($this->createCounterpart());

        $basket = new Basket()->setTotal(10000)->setDiscountKind(Basket::CODE_KIND_GIFT_CARD)->setDiscountAmount(5000);
        $this->createProvider(counterpartService: $counterpartService, entityManager: $this->createEntityManagerAssigningIds())->onBasketPaid($basket, [7 => ['quantity' => 2, 'total' => 4000]], []);

        $this->assertSame([4000], $this->credited);
    }

    // A counterpart given for nothing writes no public contributor, no message and no ticket - the thank-you e-mail still goes
    public function testAFreeContributionWritesNoContributorNorTicket(): void
    {
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn($this->createCounterpart()->setPrice(0));

        $lotteryService = $this->createMock(LotteryServiceInterface::class);
        $lotteryService->expects($this->never())->method('generateTicketsForContributor');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $this->createProvider(counterpartService: $counterpartService, entityManager: $entityManager, lotteryService: $lotteryService)->onBasketPaid(
            new Basket(),
            [7 => ['quantity' => 5, 'total' => 0]],
            ['name' => 'item-1', 'message' => 'spam', 'email' => 'item-1@example.com', 'counterparts' => [7 => 5]],
        );

        $this->assertSame([[7, 5]], $this->counted);
        $this->assertSame([], $this->credited);
    }

    // A basket holding two campaigns writes one contributor in each, each credited with its own lines
    public function testEachCampaignOfTheBasketGetsItsOwnContributor(): void
    {
        $first = $this->createCounterpart();
        $second = $this->createCounterpart();
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturnMap([[7, $first], [8, $second]]);

        $this->createProvider(counterpartService: $counterpartService, entityManager: $this->createEntityManagerAssigningIds())->onBasketPaid(
            new Basket(),
            [7 => ['quantity' => 1, 'total' => 1000], 8 => ['quantity' => 2, 'total' => 2000]],
            ['name' => null, 'message' => null, 'email' => 'item-1@example.com', 'counterparts' => [7 => 1, 8 => 2]],
        );

        $this->assertSame([1000, 2000], $this->credited);
        $this->assertCount(1, $first->getCrowdfunding()->getContributors());
        $this->assertCount(1, $second->getCrowdfunding()->getContributors());
        $this->assertNotSame($first->getCrowdfunding()->getContributors()->first(), $second->getCrowdfunding()->getContributors()->first());
    }

    // A signature or a message longer than its column is cut, so the paid order is still delivered
    public function testOnBasketValidatedCutsWhatIsTooLongForItsColumn(): void
    {
        $checkoutData = $this->createProvider()->onBasketValidated(new Basket(), [], ['coordinates' => ['contributorName' => '  ' . str_repeat('a', 60), 'contributorMessage' => str_repeat('é', 300)]]);

        $this->assertSame(str_repeat('a', 50), $checkoutData['name']);
        $this->assertSame(255, mb_strlen($checkoutData['message']));
    }

    // A value that is not a text is ignored rather than cast
    public function testOnBasketValidatedIgnoresAValueThatIsNotAText(): void
    {
        $checkoutData = $this->createProvider()->onBasketValidated(new Basket(), [], ['coordinates' => ['contributorName' => ['item-1'], 'contributorMessage' => '   ']]);

        $this->assertNull($checkoutData['name']);
        $this->assertNull($checkoutData['message']);
    }

    // A refund takes back exactly what the payment counted, gives the run back and removes the contributor with its tickets
    public function testARefundTakesBackWhatThePaymentCounted(): void
    {
        $counterpart = $this->createCounterpart();
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn($counterpart);
        $contributor = new CrowdfundingContributor();
        $counterpart->getCrowdfunding()->addContributor($contributor);
        $this->basketContributors = [$contributor];
        $this->contributorTickets = [new LotteryTicket(), new LotteryTicket()];

        $removed = [];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('remove')->willReturnCallback(static function (object $entity) use (&$removed): void {
            $removed[] = $entity::class;
        });

        $basket = new Basket()->setTotal(10000)->setDiscountKind(Basket::CODE_KIND_DISCOUNT)->setDiscountAmount(5000);
        $this->createProvider(counterpartService: $counterpartService, entityManager: $entityManager)->onBasketRefunded($basket, [7 => ['quantity' => 2, 'total' => 4000]]);

        $this->assertSame([2000], $this->released);
        $this->assertSame([[7, 2]], $this->returned);
        $this->assertSame([LotteryTicket::class, LotteryTicket::class, CrowdfundingContributor::class], $removed);
        $this->assertCount(0, $counterpart->getCrowdfunding()->getContributors());
    }

    // A ticket already drawn stays with its prize, so its contributor leaves the list but keeps its row, and the shop is told
    public function testARefundKeepsATicketAlreadyDrawn(): void
    {
        $counterpart = $this->createCounterpart();
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn($counterpart);
        $contributor = new CrowdfundingContributor();
        $counterpart->getCrowdfunding()->addContributor($contributor);
        $winning = new LotteryTicket();
        $this->basketContributors = [$contributor];
        $this->contributorTickets = [$winning, new LotteryTicket()];
        $this->wonPrizes = [new LotteryPrize()->setWinningTicket($winning)];

        $removed = [];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('remove')->willReturnCallback(static function (object $entity) use (&$removed): void {
            $removed[] = $entity;
        });

        $this->createProvider(counterpartService: $counterpartService, entityManager: $entityManager)->onBasketRefunded(new Basket(), [7 => ['quantity' => 1, 'total' => 1000]]);

        $this->assertCount(1, $removed);
        $this->assertNotSame($winning, $removed[0]);
        $this->assertCount(0, $counterpart->getCrowdfunding()->getContributors());
        $this->assertCount(1, $this->logged);
    }

    // A test basket counted nothing, so its refund takes nothing back
    public function testARefundedTestBasketTakesNothingBack(): void
    {
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn($this->createCounterpart());

        $this->createProvider(counterpartService: $counterpartService)->onBasketRefunded(new Basket()->setTestMode(true), [7 => ['quantity' => 1, 'total' => 1000]]);

        $this->assertSame([], $this->released);
        $this->assertSame([], $this->returned);
    }

    // Doctrine assigns the identifier at flush time, and nothing else here does: without it every contributor keeps the null the constructor left, which is exactly what the message refuses
    // One counterpart paid for by a contributor, the database answering whether its run still held them
    private function paidOnce(bool $claimAnswers): bool
    {
        $counterpart = $this->createCounterpart()->setPrice(2500);
        $counterpartService = $this->createStub(CrowdfundingCounterpartServiceInterface::class);
        $counterpartService->method('findOneById')->willReturn($counterpart);
        $this->claimAnswers = $claimAnswers;

        $this->createProvider(counterpartService: $counterpartService, entityManager: $this->createEntityManagerAssigningIds())->onBasketPaid(
            new Basket(),
            [7 => ['quantity' => 2]],
            ['name' => 'Camille', 'message' => null, 'email' => 'camille@example.com', 'counterparts' => [7 => 2]],
        );

        return [] !== $this->counted;
    }

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
        $crowdfunding->setHidden(false);
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
        bool $hasShippedCounterpart = false,
    ): CrowdfundingBasketItemProvider {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $counterpartRepository = $this->createStub(CrowdfundingCounterpartRepository::class);
        $counterpartRepository->method('hasShippedCounterpart')->willReturn($hasShippedCounterpart);
        $counterpartRepository->method('claimOrdered')->willReturnCallback(function (CrowdfundingCounterpart $counterpart, int $quantity): bool {
            $this->counted[] = [7, $quantity];

            return $this->claimAnswers;
        });
        $counterpartRepository->method('addOrdered')->willReturnCallback(function (CrowdfundingCounterpart $counterpart, int $quantity): void {
            $this->forced[] = [7, $quantity];
        });

        $crowdfundingRepository = $this->createStub(CrowdfundingRepository::class);
        $crowdfundingRepository->method('addAchieved')->willReturnCallback(function (Crowdfunding $crowdfunding, int $amount): void {
            $this->credited[] = $amount;
        });

        $crowdfundingRepository->method('releaseAchieved')->willReturnCallback(function (Crowdfunding $crowdfunding, int $amount): void {
            $this->released[] = $amount;
        });
        $counterpartRepository->method('releaseOrdered')->willReturnCallback(function (CrowdfundingCounterpart $counterpart, int $quantity): void {
            $this->returned[] = [7, $quantity];
        });

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('critical')->willReturnCallback(function (string $message): void {
            $this->logged[] = $message;
        });
        $logger->method('warning')->willReturnCallback(function (string $message): void {
            $this->logged[] = $message;
        });

        $contributorRepository = $this->createStub(CrowdfundingContributorRepository::class);
        $contributorRepository->method('findBy')->willReturnCallback(fn (): array => $this->basketContributors);
        $ticketRepository = $this->createStub(LotteryTicketRepository::class);
        $ticketRepository->method('findBy')->willReturnCallback(fn (): array => $this->contributorTickets);
        $prizeRepository = $this->createStub(LotteryPrizeRepository::class);
        $prizeRepository->method('findBy')->willReturnCallback(fn (): array => $this->wonPrizes);

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
            $counterpartRepository,
            $crowdfundingRepository,
            $logger,
            $contributorRepository,
            $ticketRepository,
            $prizeRepository,
        );
    }
}
