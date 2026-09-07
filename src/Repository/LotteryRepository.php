<?php

namespace c975L\CrowdfundingBundle\Repository;

use c975L\CrowdfundingBundle\Entity\Lottery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\c975L\CrowdfundingBundle\Entity\Lottery>
 */
class LotteryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lottery::class);
    }
}
