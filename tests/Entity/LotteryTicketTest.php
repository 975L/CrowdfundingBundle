<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Entity;

use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Entity\LotteryTicket;
use PHPUnit\Framework\TestCase;

class LotteryTicketTest extends TestCase
{
    // The number is what the contributor reads on their email and what a winner is announced by: it is the ticket, as far as anyone outside the database is concerned
    public function testATicketIsPrintedAsItsNumber(): void
    {
        $this->assertSame('AB-1234-CDE', (string) new LotteryTicket()->setNumber('AB-1234-CDE'));
    }

    public function testATicketWithoutANumberIsPrintedAsAnEmptyString(): void
    {
        $this->assertSame('', (string) new LotteryTicket());
    }

    // Three relations, each read by an email: the lottery names the draw, the contributor is who it is sent to, the counterpart says which campaign earned it
    public function testATicketNamesItsLotteryItsBuyerAndTheCounterpartThatEarnedIt(): void
    {
        $lottery = new Lottery();
        $contributor = new CrowdfundingContributor();
        $counterpart = new CrowdfundingCounterpart();

        $ticket = new LotteryTicket()
            ->setLottery($lottery)
            ->setContributor($contributor)
            ->setCounterpart($counterpart)
        ;

        $this->assertSame($lottery, $ticket->getLottery());
        $this->assertSame($contributor, $ticket->getContributor());
        $this->assertSame($counterpart, $ticket->getCounterpart());
    }
}
