<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\MessageHandler;

use c975L\CrowdfundingBundle\Email\CrowdfundingEmailSender;
use c975L\CrowdfundingBundle\Message\LotteryTicketsMessage;
use c975L\CrowdfundingBundle\Repository\CrowdfundingContributorRepository;
use c975L\CrowdfundingBundle\Repository\LotteryTicketRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class LotteryTicketsMessageHandler
{
    public function __construct(
        private readonly CrowdfundingContributorRepository $crowdfundingContributorRepository,
        private readonly LotteryTicketRepository $lotteryTicketRepository,
        private readonly CrowdfundingEmailSender $emailSender,
    ) {
    }

    public function __invoke(LotteryTicketsMessage $message): void
    {
        $lotteryTickets = $this->lotteryTicketRepository->findBy(['contributor' => $message->getContributorId()]);
        if (!$lotteryTickets) {
            return;
        }

        $contributor = $this->crowdfundingContributorRepository->find($message->getContributorId());
        if (!$contributor) {
            return;
        }

        // Defines tickets
        $tickets = [];
        foreach ($lotteryTickets as $lotteryTicket) {
            $tickets[] = [
                'lotteryIdentifier' => $lotteryTicket->getLottery()->getIdentifier(),
                'number' => $lotteryTicket->getNumber(),
                'drawDate' => $lotteryTicket->getLottery()->getDrawDate(),
                'crowdfunding' => $lotteryTicket->getCounterpart()->getCrowdfunding()->__toString(),
                'slug' => $lotteryTicket->getCounterpart()->getCrowdfunding()->getSlug(),
            ];
        }

        // One email listing every ticket - the handler returned above on a contributor holding none, so there is always at least one here
        $this->emailSender->send(
            'lottery_tickets',
            'label.lottery_tickets',
            (string) $contributor->getEmail(),
            ['tickets' => $tickets],
            $contributor->getLocale(),
            (string) $tickets[0]['lotteryIdentifier'],
        );
    }
}
