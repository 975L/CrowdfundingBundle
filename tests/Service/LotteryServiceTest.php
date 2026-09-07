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
use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Entity\LotteryPrize;
use c975L\CrowdfundingBundle\Entity\LotteryTicket;
use c975L\CrowdfundingBundle\Message\LotteryWinningTicketMessage;
use c975L\CrowdfundingBundle\Repository\LotteryTicketRepository;
use c975L\CrowdfundingBundle\Service\LotteryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class LotteryServiceTest extends TestCase
{
    // "XX-9999-XXX", the letters taken from an alphabet without A, E, I, O or U so a number can be read out loud without ambiguity
    public function testGenerateTicketNumberFollowsTheReadableFormat(): void
    {
        $number = $this->createService()->generateTicketNumber();

        $this->assertMatchesRegularExpression('/^[BCDFGHJKLMNPQRSTVWXYZ]{2}-\d{4}-[BCDFGHJKLMNPQRSTVWXYZ]{3}$/', $number);
    }

    // The column is unique: a number already taken is drawn again rather than handed back to be refused at flush
    public function testGenerateTicketNumberDrawsAgainOnATakenNumber(): void
    {
        $repository = new LotteryTicketRepositoryFixture(refusals: 3);

        $this->createService(ticketRepository: $repository)->generateTicketNumber();

        $this->assertSame(4, $repository->lookups);
    }

    // Twenty draws and it gives up: the alternative is a request looping forever on a table whose numbers are all taken
    public function testGenerateTicketNumberGivesUpAfterTwentyAttempts(): void
    {
        $repository = new LotteryTicketRepositoryFixture(refusals: \PHP_INT_MAX);

        $this->createService(ticketRepository: $repository)->generateTicketNumber();

        $this->assertSame(20, $repository->lookups);
    }

    // One ticket per unit taken, times what the counterpart entitles to, and once for each lottery the campaign runs
    public function testGenerateTicketsForContributorPersistsOneTicketPerUnitAndPerLottery(): void
    {
        $contributor = $this->createContributor(lotteries: 2);
        $counterpart = new CrowdfundingCounterpart()->setLotteryTickets(3);

        $persisted = [];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $this->createService(entityManager: $entityManager)->generateTicketsForContributor($contributor, $counterpart, 2);

        $this->assertCount(12, $persisted);
        $this->assertContainsOnlyInstancesOf(LotteryTicket::class, $persisted);
    }

    // A counterpart entitling to none is simply not a lottery counterpart
    public function testGenerateTicketsForContributorWritesNothingForACounterpartWithoutTickets(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $this->createService(entityManager: $entityManager)->generateTicketsForContributor(
            $this->createContributor(),
            new CrowdfundingCounterpart()->setLotteryTickets(0),
            2,
        );
    }

    // Each ticket carries who took it, under which counterpart and for which lottery: that is what the winner's email is built from
    public function testGenerateTicketsForContributorTiesEachTicketToItsLotteryAndItsBuyer(): void
    {
        $contributor = $this->createContributor();
        $counterpart = new CrowdfundingCounterpart()->setLotteryTickets(1);

        $persisted = [];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $this->createService(entityManager: $entityManager)->generateTicketsForContributor($contributor, $counterpart, 1);

        $ticket = $persisted[0];
        $this->assertSame($contributor, $ticket->getContributor());
        $this->assertSame($counterpart, $ticket->getCounterpart());
        $this->assertSame($contributor->getCrowdfunding()->getLotteries()[0], $ticket->getLottery());
        $this->assertInstanceOf(\DateTime::class, $ticket->getCreation());
    }

    // The rank comes off the url, where nothing says the lottery has a prize of that rank: first() answers false on an empty collection, and reading the prize on it was a fatal error on the drawing endpoint
    public function testDrawWinnerAnswersNullForARankTheLotteryHasNoPrizeFor(): void
    {
        $lottery = $this->createLottery(prizeRanks: [1]);

        $this->assertNull($this->createService()->drawWinner($lottery, 4));
    }

    public function testDrawWinnerAnswersNullWhenNoTicketWasSold(): void
    {
        $lottery = $this->createLottery(prizeRanks: [1]);

        $this->assertNull($this->createService(ticketRepository: new LotteryTicketRepositoryFixture(tickets: []))->drawWinner($lottery, 1));
    }

    // A prize already drawn hands back its own winner: the endpoint is a POST an admin can send twice
    public function testDrawWinnerNeverRedrawsAPrizeAlreadyDrawn(): void
    {
        $lottery = $this->createLottery(prizeRanks: [1]);
        $winner = $this->createTicket('AB-1234-CDE');
        $lottery->getPrizes()[0]->setWinningTicket($winner);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $this->assertSame($winner, $this->createService(messageBus: $messageBus)->drawWinner($lottery, 1));
    }

    public function testDrawWinnerHoldsTheDrawnTicketOnThePrizeAndDatesIt(): void
    {
        $lottery = $this->createLottery(prizeRanks: [1]);
        $ticket = $this->createTicket('AB-1234-CDE');

        $winner = $this->createService(ticketRepository: new LotteryTicketRepositoryFixture(tickets: [$ticket]))->drawWinner($lottery, 1);

        $this->assertSame($ticket, $winner);
        $this->assertSame($ticket, $lottery->getPrizes()[0]->getWinningTicket());
        $this->assertInstanceOf(\DateTime::class, $lottery->getPrizes()[0]->getDrawDate());
    }

    // The winner is written before the email is asked for, and the message carries the prize the mail is built from
    public function testDrawWinnerAnnouncesTheWinnerOnce(): void
    {
        $lottery = $this->createLottery(prizeRanks: [1]);

        $dispatched = [];
        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
            $dispatched[] = $message;

            return new Envelope($message);
        });

        $this->createService(ticketRepository: new LotteryTicketRepositoryFixture(tickets: [$this->createTicket('AB-1234-CDE')]), messageBus: $messageBus)->drawWinner($lottery, 1);

        $this->assertCount(1, $dispatched);
        $this->assertInstanceOf(LotteryWinningTicketMessage::class, $dispatched[0]);
    }

    // One ticket never wins twice in the same lottery
    public function testDrawWinnerLeavesOutATicketThatAlreadyWonAnotherPrize(): void
    {
        $lottery = $this->createLottery(prizeRanks: [1, 2]);
        $first = $this->createTicket('AB-1234-CDE');
        $second = $this->createTicket('FG-5678-HJK');
        $lottery->getPrizes()[0]->setWinningTicket($first);

        $winner = $this->createService(ticketRepository: new LotteryTicketRepositoryFixture(tickets: [$first, $second]))->drawWinner($lottery, 2);

        $this->assertSame($second, $winner);
    }

    // Every ticket having already won, there is nothing left to draw - which used to call itself again on the very same list, until the stack ran out
    public function testDrawWinnerAnswersNullWhenEveryTicketHasAlreadyWon(): void
    {
        $lottery = $this->createLottery(prizeRanks: [1, 2]);
        $ticket = $this->createTicket('AB-1234-CDE');
        $lottery->getPrizes()[0]->setWinningTicket($ticket);

        $this->assertNull($this->createService(ticketRepository: new LotteryTicketRepositoryFixture(tickets: [$ticket]))->drawWinner($lottery, 2));
    }

    private function createTicket(string $number): LotteryTicket
    {
        return new LotteryTicket()->setNumber($number);
    }

    /**
     * A prize being drawn has been through the database, and its id is what the winner's email is asked for - assigned here as Doctrine would, the setter having none.
     *
     * @param list<int> $prizeRanks
     */
    private function createLottery(array $prizeRanks = []): Lottery
    {
        $lottery = new Lottery();

        foreach ($prizeRanks as $rank) {
            $prize = new LotteryPrize()->setRank($rank)->setTitle('Lot ' . $rank);
            new \ReflectionProperty(LotteryPrize::class, 'id')->setValue($prize, $rank);
            $lottery->addPrize($prize);
        }

        return $lottery;
    }

    private function createContributor(int $lotteries = 1): CrowdfundingContributor
    {
        $crowdfunding = new Crowdfunding();
        for ($i = 0; $i < $lotteries; ++$i) {
            $crowdfunding->addLottery(new Lottery());
        }

        return new CrowdfundingContributor()->setCrowdfunding($crowdfunding);
    }

    private function createService(
        ?EntityManagerInterface $entityManager = null,
        ?LotteryTicketRepository $ticketRepository = null,
        ?MessageBusInterface $messageBus = null,
    ): LotteryService {
        $bus = $messageBus ?? $this->createStub(MessageBusInterface::class);
        if (null === $messageBus) {
            $bus->method('dispatch')->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));
        }

        return new LotteryService(
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $ticketRepository ?? new LotteryTicketRepositoryFixture(),
            $bus,
        );
    }
}

// findOneBy() and findBy() are resolved by Doctrine's own EntityRepository internals, which need a real EntityManager - overridden here instead, parent constructor never invoked, as GalleryBundle does for its own repositories
class LotteryTicketRepositoryFixture extends LotteryTicketRepository
{
    public int $lookups = 0;

    /** @param list<LotteryTicket> $tickets */
    public function __construct(
        private readonly array $tickets = [],
        private readonly int $refusals = 0,
    ) {
    }

    // Answers "this number is taken" for the first $refusals lookups, so the retry loop is exercised without a database
    #[\Override]
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        ++$this->lookups;

        return $this->lookups <= $this->refusals ? new LotteryTicket() : null;
    }

    /** @return list<LotteryTicket> */
    #[\Override]
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return $this->tickets;
    }
}
