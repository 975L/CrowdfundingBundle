<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Repository;

use c975L\CrowdfundingBundle\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Media>
 */
class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

    // The rows naming a stored file, whatever the owner they hang off - what the declared-files health check walks (see UiBundle's AbstractDeclaredFilesHealthCheckProvider)
    /** @return list<Media> */
    public function findWithFilename(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.name IS NOT NULL AND m.name != :empty')
            ->setParameter('empty', '')
            ->orderBy('m.name', \SortDirection::Ascending)
            ->getQuery()
            ->getResult()
        ;
    }
}
