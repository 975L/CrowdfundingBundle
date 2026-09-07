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
use c975L\CrowdfundingBundle\Message\LotteryWinningTicketMessage;
use c975L\CrowdfundingBundle\Repository\LotteryPrizeRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class LotteryWinningTicketMessageHandler
{
    public function __construct(
        private readonly LotteryPrizeRepository $lotteryPrizeRepository,
        private readonly CrowdfundingEmailSender $emailSender,
    ) {
    }

    public function __invoke(LotteryWinningTicketMessage $message): void
    {
        $prize = $this->lotteryPrizeRepository->find($message->getPrizeId());
        if (!$prize) {
            return;
        }

        $contributor = $prize->getWinningTicket()?->getContributor();
        if (null === $contributor) {
            return;
        }

        $this->emailSender->send(
            'lottery_ticket_winner',
            'label.winning_ticket',
            (string) $contributor->getEmail(),
            ['prize' => $prize],
            $contributor->getLocale(),
            (string) $prize->getLottery()?->getIdentifier(),
        );
    }
}
