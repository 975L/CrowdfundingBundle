<?php

namespace c975L\CrowdfundingBundle\Service;

use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Entity\LotteryTicket;

interface LotteryServiceInterface
{
    // Creates and persists $quantity tickets for that contributor, one per unit of the counterpart they took.
    public function generateTicketsForContributor(CrowdfundingContributor $contributor, CrowdfundingCounterpart $counterpart, int $quantity): void;

    /**
     * @return string a "XX-9999-XXX" number, retried until unused - the letter alphabet excludes the
     *                characters that read alike (A, E, I, O, U) so a number can be read out loud without ambiguity
     */
    public function generateTicketNumber(): string;

    /**
     * Draws the winning ticket for one of the lottery's prizes.
     *
     * @param mixed $prizeRank the rank of the prize being drawn, matched against LotteryPrize::getRank()
     *
     * @return LotteryTicket|null null when the lottery has no prize of that rank, or no ticket to draw from
     */
    public function drawWinner(Lottery $lottery, $prizeRank): ?LotteryTicket;
}
