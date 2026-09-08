<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Twig\Extension;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use Twig\Attribute\AsTwigFunction;

// Reads a campaign's funding the way every part of its page needs it - the sticky bar at the top, the rail beside the story and the buttons on each tier all say the same state, so it is worked out once here rather than three times over in Twig
class CrowdfundingFundingExtension
{
    // Where a campaign stands right now: whether it has opened, whether it has closed, how far it got and how long it still has
    /** @return array{isStarted: bool, isEnded: bool, progressPercent: int, daysLeft: int, isLastDay: bool} */
    #[AsTwigFunction('crowdfunding_funding_state')]
    public function getState(Crowdfunding $crowdfunding): array
    {
        $beginDate = $crowdfunding->getBeginDate();
        $endDate = $crowdfunding->getEndDate();

        // A campaign still missing one of its two dates is not open yet: it is read as not started rather than as running against a date nobody set
        if (null === $beginDate || null === $endDate) {
            return [
                'isStarted' => false,
                'isEnded' => false,
                'progressPercent' => $this->progressPercent($crowdfunding),
                'daysLeft' => 0,
                'isLastDay' => false,
            ];
        }

        // The two states read the way the page has always read them: the beginning of the first day, the end of the last
        $now = new \DateTimeImmutable();
        $begin = \DateTimeImmutable::createFromInterface($beginDate)->setTime(0, 0);
        $end = \DateTimeImmutable::createFromInterface($endDate)->setTime(23, 59, 59);

        return [
            'isStarted' => $now > $begin,
            'isEnded' => $now > $end,
            'progressPercent' => $this->progressPercent($crowdfunding),
        ] + $this->timeLeft($now, $end);
    }

    // How far the campaign got, as the whole percent the gauge is drawn with - a goal left at zero answers zero rather than dividing by it
    private function progressPercent(Crowdfunding $crowdfunding): int
    {
        $goal = (int) $crowdfunding->getAmountGoal();
        if ($goal <= 0) {
            return 0;
        }

        return (int) round((int) $crowdfunding->getAmountAchieved() / $goal * 100);
    }

    // The days a running campaign still has, rounded up so the last twenty-three hours read "1 day left" rather than "0"
    /** @return array{daysLeft: int, isLastDay: bool} */
    private function timeLeft(\DateTimeImmutable $now, \DateTimeImmutable $end): array
    {
        if ($now > $end) {
            return ['daysLeft' => 0, 'isLastDay' => false];
        }

        $hours = (int) floor(($end->getTimestamp() - $now->getTimestamp()) / 3600);

        return [
            'daysLeft' => (int) ceil($hours / 24),
            'isLastDay' => $hours < 24,
        ];
    }
}
