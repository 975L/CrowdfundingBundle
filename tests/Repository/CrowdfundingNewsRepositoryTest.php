<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Repository;

use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\CrowdfundingBundle\Repository\CrowdfundingNewsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class CrowdfundingNewsRepositoryTest extends TestCase
{
    // What a post's news is chosen among: published within the window and not after today, of a campaign open today, not posted yet, the most recent first - the campaign joined so it is not read again
    public function testFindFreshLatestKeepsTheFreshNewsOfTheRunningCampaigns(): void
    {
        $repository = $this->createRepository();
        $since = new \DateTime('-30 days');

        $repository->findFreshLatest(['7'], $since, 48);

        foreach (['SELECT n, c', 'INNER JOIN n.crowdfunding c', 'c.hidden = false', 'c.isDeleted = false', 'c.beginDate <= :today', 'c.endDate >= :today', 'n.publishedDate >= :since', 'n.publishedDate <= :today', 'n.id NOT IN (:excluded)', 'ORDER BY n.publishedDate DESC, n.id DESC'] as $part) {
            $this->assertStringContainsString($part, $repository->dql);
        }

        $this->assertSame($since, $repository->parameter('since'));
        $this->assertSame([7], $repository->parameter('excluded'));
        $this->assertSame(48, $repository->maxResults());
    }

    // Nothing posted yet: no exclusion at all rather than an empty IN, which some databases refuse
    public function testFindFreshLatestExcludesNothingWhenNothingWasPosted(): void
    {
        $repository = $this->createRepository();

        $repository->findFreshLatest([], new \DateTime('-30 days'), 48);

        $this->assertStringNotContainsString('NOT IN', $repository->dql);
    }

    // Doctrine's own QueryBuilder, assembling the real DQL on an EntityManager that answers nothing: getQuery() hands the string it built to createQuery(), which is where it is read back
    private function createRepository(): CrowdfundingNewsRepositoryDqlFixture
    {
        $repository = new CrowdfundingNewsRepositoryDqlFixture();

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('createQuery')->willReturnCallback(static function (string $dql) use ($repository, $entityManager): Query {
            $repository->dql = $dql;

            return new NewsQueryAnsweringNothing($entityManager);
        });

        $repository->useEntityManager($entityManager);

        return $repository;
    }
}

// The parent's constructor is never invoked, ServiceEntityRepository asking a ManagerRegistry for metadata this has no use for
class CrowdfundingNewsRepositoryDqlFixture extends CrowdfundingNewsRepository
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
        $this->queryBuilder = new QueryBuilder($this->stubbedEntityManager)->select($alias)->from(CrowdfundingNews::class, $alias, $indexBy);

        return $this->queryBuilder;
    }
}

// An empty result: everything this test reads has already been assembled by the time the query is run
class NewsQueryAnsweringNothing extends Query
{
    #[\Override]
    public function getResult($hydrationMode = self::HYDRATE_OBJECT): array
    {
        return [];
    }
}
