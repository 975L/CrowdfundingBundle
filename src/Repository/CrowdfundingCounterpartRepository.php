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
}
