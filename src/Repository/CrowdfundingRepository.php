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

    // Finds all crowfundings sorted - the ones a visitor may read: a hidden campaign is not opened yet and a trashed one is on its way out, neither belonging to the listing nor to the sitemap
    /**
     * @return list<Crowdfunding>
     */
    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c, cm')
            ->leftJoin('c.medias', 'cm')
            ->andWhere('c.hidden = false')
            ->andWhere('c.isDeleted = false')
            ->orderBy('c.position', \SortDirection::Ascending)
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

    // The campaigns owning any of the given Block rows, read in one query - what the front-end "Edit this block" hover button resolves its edit URL from
    /**
     * @param int[] $blockIds
     *
     * @return Crowdfunding[]
     */
    public function findByBlockIds(array $blockIds): array
    {
        if ([] === $blockIds) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->select('c, b')
            ->innerJoin('c.blocks', 'b')
            ->andWhere('b.id IN (:blockIds)')
            ->setParameter('blockIds', $blockIds)
            ->getQuery()
            ->getResult()
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
