<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use c975L\CrowdfundingBundle\Entity\LotteryPrize;
use c975L\PaymentBundle\Entity\Basket;

interface EmailServiceInterface
{
    public function crowdfundingContribution(Basket $basket, array $counterparts): void;

    public function lotteryTickets(string $emailAddress, array $tickets): void;

    public function lotteryWinningTicket(LotteryPrize $prize): void;
}
