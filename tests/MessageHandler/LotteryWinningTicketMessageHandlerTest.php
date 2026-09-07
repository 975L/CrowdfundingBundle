<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\MessageHandler;

use c975L\CrowdfundingBundle\Email\CrowdfundingEmailSender;
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Entity\LotteryPrize;
use c975L\CrowdfundingBundle\Entity\LotteryTicket;
use c975L\CrowdfundingBundle\Message\LotteryWinningTicketMessage;
use c975L\CrowdfundingBundle\MessageHandler\LotteryWinningTicketMessageHandler;
use c975L\CrowdfundingBundle\Repository\LotteryPrizeRepository;
use PHPUnit\Framework\TestCase;

class LotteryWinningTicketMessageHandlerTest extends TestCase
{
    // The draw writes the winner then hands the prize's id to the bus: the email is built from the row, not from what the drawing request held - and it is written in the language the contribution was made in, months after that request is gone
    public function testItTellsTheHolderOfTheDrawnTicketTheyWon(): void
    {
        $prize = $this->createDrawnPrize('gagnant@example.com', 'es');

        $sender = $this->createMock(CrowdfundingEmailSender::class);
        $sender->expects($this->once())->method('send')->with(
            'lottery_ticket_winner',
            'label.winning_ticket',
            'gagnant@example.com',
            ['prize' => $prize],
            'es',
            'BCD-1A2B-3C4D',
        );

        $this->createHandler($prize, $sender)(new LotteryWinningTicketMessage(1));
    }

    // A prize deleted between the draw and the worker picking the message up leaves the queue rather than throwing
    public function testItSendsNothingWhenThePrizeIsGone(): void
    {
        $sender = $this->createMock(CrowdfundingEmailSender::class);
        $sender->expects($this->never())->method('send');

        $this->createHandler(null, $sender)(new LotteryWinningTicketMessage(1));
    }

    // A prize whose ticket or contributor was deleted has nobody to tell, and an email to an empty address is refused by the mail server rather than by us
    public function testItSendsNothingWhenNobodyHoldsTheDrawnTicket(): void
    {
        $sender = $this->createMock(CrowdfundingEmailSender::class);
        $sender->expects($this->never())->method('send');

        $this->createHandler(new LotteryPrize(), $sender)(new LotteryWinningTicketMessage(1));
    }

    private function createDrawnPrize(string $email, ?string $locale): LotteryPrize
    {
        $prize = new LotteryPrize()->setTitle('Le grand lot');
        $prize->setWinningTicket(new LotteryTicket()->setContributor(new CrowdfundingContributor()->setEmail($email)->setLocale($locale)));
        new Lottery()->setIdentifier('BCD-1A2B-3C4D')->addPrize($prize);

        return $prize;
    }

    private function createHandler(?LotteryPrize $prize, CrowdfundingEmailSender $sender): LotteryWinningTicketMessageHandler
    {
        $repository = $this->createStub(LotteryPrizeRepository::class);
        $repository->method('find')->willReturn($prize);

        return new LotteryWinningTicketMessageHandler($repository, $sender);
    }
}
