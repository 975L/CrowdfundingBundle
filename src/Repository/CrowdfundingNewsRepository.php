<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Repository;

use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CrowdfundingNews>
 */
class CrowdfundingNewsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CrowdfundingNews::class);
    }

    // The news published since the given day of the campaigns open today, not posted yet, the most recent first - what a post's news is chosen among
    /**
     * @param list<string> $excludedIds
     *
     * @return list<CrowdfundingNews>
     */
    public function findFreshLatest(array $excludedIds, \DateTimeInterface $since, int $limit): array
    {
        $qb = CrowdfundingRepository::addRunningCriteria($this->createQueryBuilder('n')->innerJoin('n.crowdfunding', 'c')->addSelect('c'), 'c')
            ->andWhere('n.publishedDate >= :since')
            ->andWhere('n.publishedDate <= :today')
            ->setParameter('since', $since, Types::DATE_MUTABLE)
            ->orderBy('n.publishedDate', \SortDirection::Descending)
            ->addOrderBy('n.id', \SortDirection::Descending)
            ->setMaxResults($limit)
        ;

        if ([] !== $excludedIds) {
            $qb->andWhere('n.id NOT IN (:excluded)')->setParameter('excluded', array_map(intval(...), $excludedIds));
        }

        return $qb->getQuery()->getResult();
    }
}
