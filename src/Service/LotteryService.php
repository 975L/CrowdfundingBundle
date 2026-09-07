<?php

namespace c975L\CrowdfundingBundle\Service;

use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Entity\LotteryTicket;
use c975L\CrowdfundingBundle\Message\LotteryWinningTicketMessage;
use c975L\CrowdfundingBundle\Repository\LotteryTicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\String\ByteString;

class LotteryService implements LotteryServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LotteryTicketRepository $ticketRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    // Generates tickets for a contributor based on their purchase
    public function generateTicketsForContributor(CrowdfundingContributor $contributor, CrowdfundingCounterpart $counterpart, int $quantity): void
    {
        $tickets = [];
        $crowdfunding = $contributor->getCrowdfunding();
        // Generates tickets
        if ($counterpart->getLotteryTickets() > 0) {
            $lotteries = $crowdfunding->getLotteries();
            foreach ($lotteries as $lottery) {
                $ticketsToGenerate = $counterpart->getLotteryTickets() * $quantity;
                for ($i = 0; $i < $ticketsToGenerate; ++$i) {
                    $ticket = new LotteryTicket();
                    $ticket->setLottery($lottery);
                    $ticket->setContributor($contributor);
                    $ticket->setCounterpart($counterpart);
                    $ticket->setNumber($this->generateTicketNumber());
                    $ticket->setCreation(new \DateTime());

                    $this->entityManager->persist($ticket);
                    $tickets[] = $ticket;
                }
            }
        }

        $this->entityManager->flush();
    }

    // Generates a unique ticket number - Format: XX-YYYY-ZZZ (2 letters, 4 numbers, 3 letters)
    public function generateTicketNumber(): string
    {
        $attempts = 0;
        $maxAttempts = 20;

        do {
            // Generate random parts
            $part1 = strtoupper(ByteString::fromRandom(2, 'BCDFGHJKLMNPQRSTVWXYZ')->toString());
            $part2 = sprintf('%04d', random_int(1, 9999));
            $part3 = strtoupper(ByteString::fromRandom(3, 'BCDFGHJKLMNPQRSTVWXYZ')->toString());

            $number = $part1 . '-' . $part2 . '-' . $part3;

            // Check if ticket number already exists
            $exists = $this->ticketRepository->findOneBy(['number' => $number]);

            ++$attempts;
        } while ($exists && $attempts < $maxAttempts);

        return $number;
    }

    // Draws a random winner for a prize
    public function drawWinner(Lottery $lottery, $prizeRank): ?LotteryTicket
    {
        // Finds the prize
        $prize = $lottery->getPrizes()
            ->filter(fn ($p) => $p->getRank() === $prizeRank)
            ->first()
        ;

        // The lottery holds no prize of that rank: first() answers false on an empty collection, and everything below reads the prize
        if (false === $prize) {
            return null;
        }

        // Already drawn: the winner is the one the prize holds, a second call never redraws it
        $winningTicket = $prize->getWinningTicket();
        if (null !== $winningTicket) {
            return $winningTicket;
        }

        // A ticket already holding one of this lottery's prizes is taken out of the draw here, rather than drawn and then redrawn until another comes up: once every ticket had won, that recursion never ended
        // Compared by identity rather than by id: a prize and a ticket read in the same unit of work are the same instance, while two tickets not yet flushed both carry a null id and would match each other
        $alreadyWon = [];
        foreach ($lottery->getPrizes() as $existingPrize) {
            if (null !== $existingPrize->getWinningTicket()) {
                $alreadyWon[] = $existingPrize->getWinningTicket();
            }
        }

        $tickets = array_values(array_filter(
            $this->ticketRepository->findBy(['lottery' => $lottery]),
            static fn (LotteryTicket $ticket): bool => !\in_array($ticket, $alreadyWon, true),
        ));

        if ([] === $tickets) {
            return null;
        }

        // Shuffles randomly the tickets
        $shuffles = random_int(3, 10);
        for ($i = 0; $i < $shuffles; ++$i) {
            shuffle($tickets);
        }

        // Selects a random ticket
        $winningTicket = $tickets[array_rand($tickets)];

        // Defines the winning ticket for the prize
        $prize->setWinningTicket($winningTicket);
        $prize->setDrawDate(new \DateTime());
        $this->entityManager->flush();

        // Sends email to the winner
        $this->messageBus->dispatch(new LotteryWinningTicketMessage($prize->getId()));

        return $winningTicket;
    }
}
