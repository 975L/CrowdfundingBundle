<?php

namespace c975L\CrowdfundingBundle\Service;

use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\LotteryTicket;
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;

interface LotteryServiceInterface
{
    public function generateTicketsForContributor(CrowdfundingContributor $contributor, CrowdfundingCounterpart $counterpart, int $quantity): void;

    public function generateTicketNumber(): string;

    public function drawWinner(Lottery $lottery, $prizeRank): ?LotteryTicket;
}