<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Entity;

use c975L\CrowdfundingBundle\Entity\LotteryPrize;
use c975L\CrowdfundingBundle\Entity\LotteryTicket;
use PHPUnit\Framework\TestCase;

class LotteryPrizeTest extends TestCase
{
    // The rank is what the drawing url names, and the route only accepts a single digit: the setter holds the same range whatever reaches it - a fixture, an import or a hand-written query
    public function testTheRankIsHeldBetweenOneAndFive(): void
    {
        $this->assertSame(1, new LotteryPrize()->setRank(0)->getRank());
        $this->assertSame(1, new LotteryPrize()->setRank(-2)->getRank());
        $this->assertSame(5, new LotteryPrize()->setRank(9)->getRank());
        $this->assertSame(3, new LotteryPrize()->setRank(3)->getRank());
    }

    // A prize not yet drawn holds neither winner nor date: that is exactly what the draw reads to know it has not run
    public function testAPrizeStartsUndrawn(): void
    {
        $prize = new LotteryPrize();

        $this->assertNull($prize->getWinningTicket());
        $this->assertNull($prize->getDrawDate());
    }

    public function testAPrizeHoldsTheTicketDrawnForIt(): void
    {
        $ticket = new LotteryTicket()->setNumber('AB-1234-CDE');

        $this->assertSame($ticket, new LotteryPrize()->setWinningTicket($ticket)->getWinningTicket());
    }

    // A language laid over the row is what the page reads, the row keeping the text it was written with
    public function testATranslationIsReadOverTheTextThePrizeWasWrittenWith(): void
    {
        $prize = new LotteryPrize()->setTitle('Un vélo')->setDescription('Rouge');
        $prize->setTranslated(['title' => 'A bicycle']);

        $this->assertSame('A bicycle', $prize->getTitle());
        $this->assertSame('Rouge', $prize->getDescription());
        $this->assertSame('Un vélo', $prize->getUntranslated('title'));
        $this->assertNull($prize->getUntranslated('rank'));
    }
}
