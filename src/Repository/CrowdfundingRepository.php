<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Repository;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Crowdfunding>
 */
class CrowdfundingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Crowdfunding::class);
    }

    // Finds all crowfundings sorted
    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c, cm')
            ->leftJoin('c.medias', 'cm')
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    // Finds a crowdfunding by id with joined data
    public function findOneById(int $id): ?Crowdfunding
    {
        return $this->createQueryBuilder('c')
            ->select('c, cm, cc, ccm, v, cn, cct')
            ->leftJoin('c.medias', 'cm')
            ->leftJoin('c.counterparts', 'cc')
            ->leftJoin('cc.media', 'ccm')
            ->leftJoin('c.videos', 'v')
            ->leftJoin('c.news', 'cn')
            ->leftJoin('c.contributors', 'cct')
            ->leftJoin('c.lotteries', 'cl')
            ->where('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    // Finds a crowdfunding by slug with joined data
    public function findOneBySlug(string $slug): ?Crowdfunding
    {
        return $this->createQueryBuilder('c')
            ->select('c, cm, cc, ccm, v, cn, cct')
            ->leftJoin('c.medias', 'cm')
            ->leftJoin('c.counterparts', 'cc')
            ->leftJoin('cc.media', 'ccm')
            ->leftJoin('c.videos', 'v')
            ->leftJoin('c.news', 'cn')
            ->leftJoin('c.contributors', 'cct')
            ->leftJoin('c.lotteries', 'cl')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
