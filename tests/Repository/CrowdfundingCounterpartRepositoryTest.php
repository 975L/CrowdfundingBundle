<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Repository;

use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Repository\CrowdfundingCounterpartRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

// What PaymentBundle is told about parcels comes from this one query: a filter dropped here warns a site with only digital counterparts about an empty delivery grid again
class CrowdfundingCounterpartRepositoryTest extends TestCase
{
    private string $sql = '';

    // Only a posted counterpart of a campaign that is visible, not trashed and not over yet counts
    public function testHasShippedCounterpartFiltersOnARunningVisibleCampaign(): void
    {
        $repository = $this->createRepository([]);

        $repository->hasShippedCounterpart();

        $this->assertStringContainsString('INNER JOIN c.crowdfunding cf', $repository->dql);
        $this->assertStringContainsString('c.requiresShipping = true', $repository->dql);
        $this->assertStringContainsString('cf.hidden = false', $repository->dql);
        $this->assertStringContainsString('cf.isDeleted = false', $repository->dql);
        $this->assertStringContainsString('cf.endDate IS NULL OR cf.endDate >= :today', $repository->dql);
        $this->assertEquals(new \DateTime('today'), $repository->parameter('today'));
    }

    // One row is enough to answer
    public function testHasShippedCounterpartReadsOneRowAtMost(): void
    {
        $repository = $this->createRepository([]);

        $repository->hasShippedCounterpart();

        $this->assertSame(1, $repository->maxResults());
    }

    public function testHasShippedCounterpartAnswersFromTheRowFound(): void
    {
        $this->assertTrue($this->createRepository([['id' => 3]])->hasShippedCounterpart());
        $this->assertFalse($this->createRepository([])->hasShippedCounterpart());
    }

    // Two payments at the same second both read the same count: the run itself is the condition of its own count, a null or zero cap being no run to overflow
    public function testTheRunIsTheConditionOfItsOwnCount(): void
    {
        $this->assertTrue($this->repositoryCatchingStatements(1)->claimOrdered(new CrowdfundingCounterpart(), 2));

        $this->assertStringContainsString('ordered_quantity = COALESCE(ordered_quantity, 0) + :quantity', $this->sql);
        $this->assertStringContainsString('COALESCE(ordered_quantity, 0) + :quantity <= limited_quantity', $this->sql);
        $this->assertStringContainsString('limited_quantity IS NULL OR limited_quantity <= 0', $this->sql);
    }

    public function testAClaimTheRunRefusesAnswersFalse(): void
    {
        $this->assertFalse($this->repositoryCatchingStatements(0)->claimOrdered(new CrowdfundingCounterpart(), 2));
    }

    // Past the run, the count is still what was paid for
    public function testWhatTheRunCouldNotHoldIsCountedWithoutCondition(): void
    {
        $this->repositoryCatchingStatements(1)->addOrdered(new CrowdfundingCounterpart(), 2);

        $this->assertStringNotContainsString('limited_quantity', $this->sql);
    }

    private function repositoryCatchingStatements(int $affectedRows): CrowdfundingCounterpartRepository
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(function (string $sql) use ($affectedRows): int {
            $this->sql = $sql;

            return $affectedRows;
        });

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return new class ($entityManager) extends CrowdfundingCounterpartRepository {
            public function __construct(private readonly EntityManagerInterface $entityManager)
            {
            }

            protected function getEntityManager(): EntityManagerInterface
            {
                return $this->entityManager;
            }
        };
    }

    // Doctrine's own QueryBuilder on an EntityManager that answers the given rows: getQuery() hands the DQL it built to createQuery(), which is where it is read back
    private function createRepository(array $rows): CrowdfundingCounterpartRepositoryDqlFixture
    {
        $repository = new CrowdfundingCounterpartRepositoryDqlFixture();

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('createQuery')->willReturnCallback(static function (string $dql) use ($repository, $entityManager, $rows): Query {
            $repository->dql = $dql;

            return new ScalarQueryAnswering($entityManager, $rows);
        });

        $repository->useEntityManager($entityManager);

        return $repository;
    }
}

// The parent's constructor is never invoked, ServiceEntityRepository asking a ManagerRegistry for metadata this has no use for
class CrowdfundingCounterpartRepositoryDqlFixture extends CrowdfundingCounterpartRepository
{
    public string $dql = '';

    private ?QueryBuilder $queryBuilder = null;

    private EntityManagerInterface $stubbedEntityManager;

    public function __construct()
    {
    }

    public function useEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->stubbedEntityManager = $entityManager;
    }

    // The value bound to a named placeholder, read off the builder the last call used
    public function parameter(string $name): mixed
    {
        return $this->queryBuilder?->getParameter($name)?->getValue();
    }

    public function maxResults(): ?int
    {
        return $this->queryBuilder?->getMaxResults();
    }

    #[\Override]
    public function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
    {
        $this->queryBuilder = new QueryBuilder($this->stubbedEntityManager)->select($alias)->from(CrowdfundingCounterpart::class, $alias, $indexBy);

        return $this->queryBuilder;
    }
}

// Answers the rows it was given, everything this test reads having been assembled by the time the query is run
class ScalarQueryAnswering extends Query
{
    public function __construct(EntityManagerInterface $entityManager, private readonly array $rows)
    {
        parent::__construct($entityManager);
    }

    #[\Override]
    public function getScalarResult(): array
    {
        return $this->rows;
    }
}
