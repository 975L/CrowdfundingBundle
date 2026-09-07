<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Management;

use c975L\ConfigBundle\Management\StatusProviderInterface;
use c975L\CrowdfundingBundle\Repository\LotteryPrizeRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * The two numbers a maintainer does something about the morning they read them, across every site at once.
 *
 * Deliberately not the count of campaigns, of counterparts or of contributors: a number nothing is done about is
 * read once, decides nothing and buries what matters (see ECOSYSTEM.md §20). A prize whose draw date has passed and
 * which holds no winner is somebody waiting; a campaign's own progress is on its page, where its author reads it.
 */
class CrowdfundingStatusProvider implements StatusProviderInterface
{
    public function __construct(
        private readonly LotteryPrizeRepository $lotteryPrizeRepository,
    ) {
    }

    public function getStatusKey(): string
    {
        return 'crowdfunding';
    }

    public function getStatusData(): array
    {
        return [
            'drawsAwaiting' => $this->drawsAwaiting(),
            'oldestDrawAwaiting' => $this->oldestDrawAwaiting(),
        ];
    }

    // A prize of an open lottery whose draw date has passed and which holds no winning ticket: the draw is a click nobody made, and every ticket holder is waiting on it
    private function drawsAwaiting(): int
    {
        return (int) $this->awaitingQuery()
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    // The date of the oldest one, which is what says whether a backlog is this morning's or last month's
    private function oldestDrawAwaiting(): ?string
    {
        $oldest = $this->awaitingQuery()
            ->select('MIN(l.drawDate)')
            ->getQuery()
            ->getSingleScalarResult();

        if (null === $oldest) {
            return null;
        }

        // Built on its own line rather than chained off the "new": PDepend, which phpmd runs on, stops parsing a file at that token and then analyses nothing in it at all
        $date = new \DateTime((string) $oldest);

        return $date->format('Y-m-d');
    }

    private function awaitingQuery(): QueryBuilder
    {
        return $this->lotteryPrizeRepository->createQueryBuilder('p')
            ->join('p.lottery', 'l')
            ->where('p.winningTicket IS NULL')
            ->andWhere('l.isActive = true')
            ->andWhere('l.drawDate IS NOT NULL')
            ->andWhere('l.drawDate < :now')
            ->setParameter('now', new \DateTime());
    }
}
