<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Twig;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Twig\Extension\CrowdfundingFundingExtension;
use PHPUnit\Framework\TestCase;

class CrowdfundingFundingExtensionTest extends TestCase
{
    private function createCampaign(string $begin, string $end, int $achieved = 0, int $goal = 100000): Crowdfunding
    {
        return new Crowdfunding()
            ->setBeginDate(new \DateTime($begin))
            ->setEndDate(new \DateTime($end))
            ->setAmountAchieved($achieved)
            ->setAmountGoal($goal)
        ;
    }

    public function testACampaignWhoseFirstDayHasNotComeIsNotStarted(): void
    {
        $state = new CrowdfundingFundingExtension()->getState($this->createCampaign('+10 days', '+40 days'));

        $this->assertFalse($state['isStarted']);
        $this->assertFalse($state['isEnded']);
    }

    public function testACampaignRunningIsStartedAndNotEnded(): void
    {
        $state = new CrowdfundingFundingExtension()->getState($this->createCampaign('-10 days', '+10 days'));

        $this->assertTrue($state['isStarted']);
        $this->assertFalse($state['isEnded']);
    }

    // The end of the last day, not its beginning: a campaign closing today is still open all day
    public function testACampaignClosingTodayIsNotEndedYet(): void
    {
        $state = new CrowdfundingFundingExtension()->getState($this->createCampaign('-10 days', 'now'));

        $this->assertFalse($state['isEnded']);
        $this->assertTrue($state['isLastDay']);
        $this->assertSame(1, $state['daysLeft']);
    }

    public function testACampaignPastItsLastDayIsEnded(): void
    {
        $state = new CrowdfundingFundingExtension()->getState($this->createCampaign('-40 days', '-1 day'));

        $this->assertTrue($state['isEnded']);
        $this->assertSame(0, $state['daysLeft']);
        $this->assertFalse($state['isLastDay']);
    }

    // Whole days, rounded up, so the hours left of a day still read as a day rather than as none
    public function testTheDaysLeftAreRoundedUp(): void
    {
        $state = new CrowdfundingFundingExtension()->getState($this->createCampaign('-10 days', '+2 days'));

        $this->assertSame(3, $state['daysLeft']);
        $this->assertFalse($state['isLastDay']);
    }

    public function testTheProgressIsTheWholePercentReached(): void
    {
        $state = new CrowdfundingFundingExtension()->getState($this->createCampaign('-10 days', '+10 days', 27000, 400000));

        $this->assertSame(7, $state['progressPercent']);
    }

    // A goal nobody set answers zero rather than dividing by it
    public function testAGoalLeftAtZeroReportsNoProgress(): void
    {
        $state = new CrowdfundingFundingExtension()->getState($this->createCampaign('-10 days', '+10 days', 27000, 0));

        $this->assertSame(0, $state['progressPercent']);
    }

    // A campaign still being written carries no date: it is read as not open rather than as running against a date nobody set
    public function testACampaignWithoutDatesIsNotStarted(): void
    {
        $campaign = new Crowdfunding()->setAmountAchieved(5000)->setAmountGoal(10000);

        $state = new CrowdfundingFundingExtension()->getState($campaign);

        $this->assertFalse($state['isStarted']);
        $this->assertFalse($state['isEnded']);
        $this->assertSame(50, $state['progressPercent']);
    }
}
