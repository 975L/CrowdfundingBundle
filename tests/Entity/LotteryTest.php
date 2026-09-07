<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Entity;

use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Entity\LotteryPrize;
use c975L\CrowdfundingBundle\Entity\LotteryTicket;
use c975L\CrowdfundingBundle\Entity\LotteryVideo;
use PHPUnit\Framework\TestCase;

class LotteryTest extends TestCase
{
    // Three collections read by the lottery page before anything is added: left uninitialised, a lottery built with new fatals on the first foreach
    public function testTheThreeCollectionsAreUsableOnALotteryJustBuilt(): void
    {
        $lottery = new Lottery();

        $this->assertCount(0, $lottery->getPrizes());
        $this->assertCount(0, $lottery->getTickets());
        $this->assertCount(0, $lottery->getVideos());
    }

    // A lottery is running by default, the listener setting the same flag on the first save: an admin closes it, nobody has to open it
    public function testALotteryIsRunningByDefault(): void
    {
        $this->assertTrue(new Lottery()->isActive());
        $this->assertFalse(new Lottery()->setIsActive(false)->isActive());
    }

    public function testAddingAPrizeSetsTheBackReference(): void
    {
        $lottery = new Lottery();
        $prize = new LotteryPrize();

        $lottery->addPrize($prize);

        $this->assertSame($lottery, $prize->getLottery());
        $this->assertCount(1, $lottery->getPrizes());
    }

    public function testAddingTheSamePrizeTwiceKeepsOneRow(): void
    {
        $lottery = new Lottery();
        $prize = new LotteryPrize();

        $lottery->addPrize($prize);
        $lottery->addPrize($prize);

        $this->assertCount(1, $lottery->getPrizes());
    }

    public function testRemovingAPrizeClearsTheBackReference(): void
    {
        $lottery = new Lottery();
        $prize = new LotteryPrize();
        $lottery->addPrize($prize);

        $lottery->removePrize($prize);

        $this->assertNull($prize->getLottery());
        $this->assertCount(0, $lottery->getPrizes());
    }

    public function testAddingATicketSetsTheBackReference(): void
    {
        $lottery = new Lottery();
        $ticket = new LotteryTicket();

        $lottery->addTicket($ticket);

        $this->assertSame($lottery, $ticket->getLottery());
    }

    public function testAddingAVideoSetsTheBackReference(): void
    {
        $lottery = new Lottery();
        $video = new LotteryVideo();

        $lottery->addVideo($video);

        $this->assertSame($lottery, $video->getLottery());
    }
}
