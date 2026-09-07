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
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Entity\LotteryTicket;
use c975L\CrowdfundingBundle\Message\LotteryTicketsMessage;
use c975L\CrowdfundingBundle\MessageHandler\LotteryTicketsMessageHandler;
use c975L\CrowdfundingBundle\Repository\CrowdfundingContributorRepository;
use c975L\CrowdfundingBundle\Repository\LotteryTicketRepository;
use PHPUnit\Framework\TestCase;

class LotteryTicketsMessageHandlerTest extends TestCase
{
    // One email listing every ticket, rather than one email per ticket: a counterpart entitling to ten of them would otherwise fill the contributor's inbox
    public function testItSendsEveryTicketOfTheContributorInOneEmail(): void
    {
        $contributor = new CrowdfundingContributor()->setEmail('camille@example.com')->setLocale('fr');
        $drawDate = new \DateTime('2026-12-24 18:00:00');

        $tickets = [
            $this->createTicket('AB-1234-CDE', 'BCD-1A2B-3C4D', $drawDate, 'Sauver les chats', 'sauver-les-chats'),
            $this->createTicket('FG-5678-HJK', 'BCD-1A2B-3C4D', $drawDate, 'Sauver les chats', 'sauver-les-chats'),
        ];

        $sent = null;
        $sender = $this->createMock(CrowdfundingEmailSender::class);
        $sender->expects($this->once())->method('send')->with(
            'lottery_tickets',
            'label.lottery_tickets',
            'camille@example.com',
            $this->callback(function (array $context) use (&$sent): bool {
                $sent = $context['tickets'];

                return true;
            }),
            'fr',
            'BCD-1A2B-3C4D',
        );

        $this->createHandler($tickets, $contributor, $sender)(new LotteryTicketsMessage(1));

        $this->assertCount(2, $sent);
        $this->assertSame([
            'lotteryIdentifier' => 'BCD-1A2B-3C4D',
            'number' => 'AB-1234-CDE',
            'drawDate' => $drawDate,
            'crowdfunding' => 'Sauver les chats',
            'slug' => 'sauver-les-chats',
        ], $sent[0]);
    }

    // A contributor who took no lottery counterpart gets no email at all, rather than an empty list
    public function testItSendsNothingToAContributorHoldingNoTicket(): void
    {
        $sender = $this->createMock(CrowdfundingEmailSender::class);
        $sender->expects($this->never())->method('send');

        $this->createHandler([], new CrowdfundingContributor(), $sender)(new LotteryTicketsMessage(1));
    }

    // The message outlives the row it names: a contributor deleted between the payment and the worker picking the message up leaves the queue rather than throwing
    public function testItSendsNothingWhenTheContributorIsGone(): void
    {
        $sender = $this->createMock(CrowdfundingEmailSender::class);
        $sender->expects($this->never())->method('send');

        $this->createHandler([$this->createTicket('AB-1234-CDE', 'BCD-1A2B-3C4D', null, 'Sauver les chats', 'sauver-les-chats')], null, $sender)(new LotteryTicketsMessage(1));
    }

    private function createTicket(string $number, string $identifier, ?\DateTimeInterface $drawDate, string $title, string $slug): LotteryTicket
    {
        $crowdfunding = new Crowdfunding()->setTitle($title)->setSlug($slug);

        $lottery = new Lottery()->setIdentifier($identifier);
        if (null !== $drawDate) {
            $lottery->setDrawDate($drawDate);
        }

        return new LotteryTicket()
            ->setNumber($number)
            ->setLottery($lottery)
            ->setCounterpart(new CrowdfundingCounterpart()->setCrowdfunding($crowdfunding))
        ;
    }

    /** @param list<LotteryTicket> $tickets */
    private function createHandler(array $tickets, ?CrowdfundingContributor $contributor, CrowdfundingEmailSender $sender): LotteryTicketsMessageHandler
    {
        $ticketRepository = $this->createStub(LotteryTicketRepository::class);
        $ticketRepository->method('findBy')->willReturn($tickets);

        $contributorRepository = $this->createStub(CrowdfundingContributorRepository::class);
        $contributorRepository->method('find')->willReturn($contributor);

        return new LotteryTicketsMessageHandler($contributorRepository, $ticketRepository, $sender);
    }
}
