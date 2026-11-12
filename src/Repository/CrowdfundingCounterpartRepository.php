<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Repository;

use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CrowdfundingCounterpart>
 */
class CrowdfundingCounterpartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CrowdfundingCounterpart::class);
    }

    // Whether a visible campaign not over yet offers a counterpart that is posted, one row read at most
    public function hasShippedCounterpart(): bool
    {
        return [] !== $this->createQueryBuilder('c')
            ->select('c.id')
            ->innerJoin('c.crowdfunding', 'cf')
            ->andWhere('c.requiresShipping = true')
            ->andWhere('cf.hidden = false')
            ->andWhere('cf.isDeleted = false')
            ->andWhere('cf.endDate IS NULL OR cf.endDate >= :today')
            ->setParameter('today', new \DateTime('today'))
            ->setMaxResults(1)
            ->getQuery()
            ->getScalarResult();
    }

    // Counts an order against the run, and says whether the run still held it: a single statement, for two payments at the same second both read the same count. A null cap is no run, and one at 0 a withdrawal this order was validated before
    public function claimOrdered(CrowdfundingCounterpart $counterpart, int $quantity): bool
    {
        return 1 === (int) $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE crowdfunding_counterpart SET ordered_quantity = COALESCE(ordered_quantity, 0) + :quantity'
                . ' WHERE id = :id AND (limited_quantity IS NULL OR limited_quantity <= 0 OR COALESCE(ordered_quantity, 0) + :quantity <= limited_quantity)',
            ['id' => $counterpart->getId(), 'quantity' => $quantity]
        );
    }

    // Counts an order the run could not hold, the customer having paid for it: the count stays true, and past its cap it is what the dashboard reads
    public function addOrdered(CrowdfundingCounterpart $counterpart, int $quantity): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE crowdfunding_counterpart SET ordered_quantity = COALESCE(ordered_quantity, 0) + :quantity WHERE id = :id',
            ['id' => $counterpart->getId(), 'quantity' => $quantity]
        );
    }

    // Gives a refunded order back to the run in one statement, never below zero
    public function releaseOrdered(CrowdfundingCounterpart $counterpart, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE crowdfunding_counterpart SET ordered_quantity = CASE WHEN COALESCE(ordered_quantity, 0) > :quantity THEN ordered_quantity - :quantity ELSE 0 END WHERE id = :id',
            ['id' => $counterpart->getId(), 'quantity' => $quantity],
            // Bound as integers: compared, not only added, a value bound as text never reads greater than a number
            ['id' => ParameterType::INTEGER, 'quantity' => ParameterType::INTEGER]
        );
    }

    // The titles of the counterparts sold past their run (see CrowdfundingAlertProvider)
    /** @return list<string> */
    public function findOversoldTitles(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.title')
            ->andWhere('c.limitedQuantity > 0')
            ->andWhere('c.orderedQuantity > c.limitedQuantity')
            ->orderBy('c.title')
            ->getQuery()
            ->getSingleColumnResult();
    }
}
