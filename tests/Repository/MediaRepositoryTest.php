<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Repository;

use c975L\CrowdfundingBundle\Entity\Media;
use c975L\CrowdfundingBundle\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

// The single-table base the declared-files health check walks: a row naming no file has nothing to check against the disk, and reporting it would flood the dashboard
class MediaRepositoryTest extends TestCase
{
    public function testFindWithFilenameLeavesOutTheRowsNamingNoFile(): void
    {
        $repository = $this->createRepository();

        $repository->findWithFilename();

        $this->assertStringContainsString('m.name IS NOT NULL', $repository->dql);
        $this->assertStringContainsString('m.name != :empty', $repository->dql);
        $this->assertSame('', $repository->parameter('empty'));
    }

    // Read in the order the dashboard prints them, so the same run twice lists the same rows in the same place
    public function testFindWithFilenameOrdersOnTheFilename(): void
    {
        $repository = $this->createRepository();

        $repository->findWithFilename();

        $this->assertStringContainsString('ORDER BY m.name ASC', $repository->dql);
    }

    // Doctrine's own QueryBuilder on an EntityManager answering nothing: the DQL it assembled is read back at createQuery()
    private function createRepository(): MediaRepositoryDqlFixture
    {
        $repository = new MediaRepositoryDqlFixture();

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('createQuery')->willReturnCallback(static function (string $dql) use ($repository, $entityManager): Query {
            $repository->dql = $dql;

            return new MediaQueryAnsweringNothing($entityManager);
        });

        $repository->useEntityManager($entityManager);

        return $repository;
    }
}

// The parent's constructor is never invoked, ServiceEntityRepository asking a ManagerRegistry for metadata this has no use for
class MediaRepositoryDqlFixture extends MediaRepository
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
        $this->queryBuilder = new QueryBuilder($this->stubbedEntityManager)->select($alias)->from(Media::class, $alias, $indexBy);

        return $this->queryBuilder;
    }
}

// The only terminal call this repository makes, answering the empty result everything read here was assembled before
class MediaQueryAnsweringNothing extends Query
{
    #[\Override]
    public function getResult($hydrationMode = self::HYDRATE_OBJECT): array
    {
        return [];
    }
}
