<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Repository;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

// The three methods are read for the joins they carry rather than for the rows they bring back: each page they serve draws the campaign's medias, counterparts, news and contributors, and a join dropped here turns one page into one query per collection and per campaign
class CrowdfundingRepositoryTest extends TestCase
{
    // The index draws every campaign with its cover, in the order the admin arranged
    public function testFindAllSortedFetchesTheMediasAndOrdersOnThePosition(): void
    {
        $repository = $this->createRepository();

        $repository->findAllSorted();

        $this->assertStringContainsString('LEFT JOIN c.medias cm', $repository->dql);
        $this->assertStringContainsString('ORDER BY c.position ASC', $repository->dql);
    }

    // Seven collections on one page: without them the campaign page issued a query per counterpart, per news and per contributor
    public function testFindOneBySlugFetchesEverythingTheCampaignPageDraws(): void
    {
        $repository = $this->createRepository();

        $repository->findOneBySlug('sauver-les-chats');

        foreach (['c.medias cm', 'c.counterparts cc', 'cc.media ccm', 'c.videos v', 'c.news cn', 'c.contributors cct', 'c.lotteries cl'] as $join) {
            $this->assertStringContainsString('LEFT JOIN ' . $join, $repository->dql, sprintf('The campaign page draws %s, which is no longer fetched with it.', $join));
        }

        $this->assertStringContainsString('WHERE c.slug = :slug', $repository->dql);
        $this->assertSame('sauver-les-chats', $repository->parameter('slug'));
    }

    // The back office reaches a campaign by its id, and draws the same page
    public function testFindOneByIdLooksUpTheIdentifier(): void
    {
        $repository = $this->createRepository();

        $repository->findOneById(7);

        $this->assertStringContainsString('WHERE c.id = :id', $repository->dql);
        $this->assertSame(7, $repository->parameter('id'));
    }

    // A slug nobody carries answers null rather than throwing, the route resolving the campaign from the url
    public function testFindOneBySlugAnswersNullForAnUnknownSlug(): void
    {
        $this->assertNull($this->createRepository()->findOneBySlug('inconnu'));
    }

    // The front-end "Edit this block" button asks for every block of a page at once: one query, joined on the blocks, rather than one per hovered block
    public function testFindByBlockIdsJoinsTheBlocksAndFiltersOnThem(): void
    {
        $repository = $this->createRepository();

        $repository->findByBlockIds([12, 13]);

        $this->assertStringContainsString('INNER JOIN c.blocks b', $repository->dql);
        $this->assertStringContainsString('WHERE b.id IN (:blockIds)', $repository->dql);
        $this->assertSame([12, 13], $repository->parameter('blockIds'));
    }

    // A page whose blocks were all just created carries no id to look up, and the query is never assembled
    public function testFindByBlockIdsQueriesNothingForAnEmptyList(): void
    {
        $repository = $this->createRepository();

        $this->assertSame([], $repository->findByBlockIds([]));
        $this->assertSame('', $repository->dql);
    }

    // Doctrine's own QueryBuilder, assembling the real DQL on an EntityManager that answers nothing: getQuery() hands the string it built to createQuery(), which is where it is read back
    private function createRepository(): CrowdfundingRepositoryDqlFixture
    {
        $repository = new CrowdfundingRepositoryDqlFixture();

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('createQuery')->willReturnCallback(static function (string $dql) use ($repository, $entityManager): Query {
            $repository->dql = $dql;

            return new QueryAnsweringNothing($entityManager);
        });

        $repository->useEntityManager($entityManager);

        return $repository;
    }
}

// The parent's constructor is never invoked, ServiceEntityRepository asking a ManagerRegistry for metadata this has no use for
class CrowdfundingRepositoryDqlFixture extends CrowdfundingRepository
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

    #[\Override]
    public function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
    {
        $this->queryBuilder = new QueryBuilder($this->stubbedEntityManager)->select($alias)->from(Crowdfunding::class, $alias, $indexBy);

        return $this->queryBuilder;
    }
}

// Both terminal calls answer an empty result: everything this test reads has already been assembled by the time the query is run
class QueryAnsweringNothing extends Query
{
    #[\Override]
    public function getResult($hydrationMode = self::HYDRATE_OBJECT): array
    {
        return [];
    }

    #[\Override]
    public function getOneOrNullResult($hydrationMode = null): mixed
    {
        return null;
    }
}
