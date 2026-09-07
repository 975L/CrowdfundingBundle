<?php

namespace c975L\CrowdfundingBundle\Repository;

use c975L\CrowdfundingBundle\Entity\LotteryTicket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\c975L\CrowdfundingBundle\Entity\LotteryTicket>
 */
class LotteryTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LotteryTicket::class);
    }
}
