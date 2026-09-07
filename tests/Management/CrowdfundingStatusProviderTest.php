<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Management;

use c975L\ConfigBundle\Management\StatusProviderInterface;
use c975L\CrowdfundingBundle\Management\CrowdfundingStatusProvider;
use c975L\CrowdfundingBundle\Repository\LotteryPrizeRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

// The report is read across a dozen sites at once: what it holds has to be a number somebody acts on the same morning
class CrowdfundingStatusProviderTest extends TestCase
{
    public function testItReportsUnderItsOwnKey(): void
    {
        $this->assertSame('crowdfunding', $this->createProvider()->getStatusKey());
    }

    // A prize of an open lottery whose draw date has passed and which holds no winner is somebody waiting on a click nobody made
    public function testItCountsTheDrawsNobodyRan(): void
    {
        $data = $this->createProvider(count: 3, oldest: '2026-08-01 18:00:00')->getStatusData();

        $this->assertSame(['drawsAwaiting' => 3, 'oldestDrawAwaiting' => '2026-08-01'], $data);
    }

    // Nothing awaiting: the key is still reported, an absent one reading as "this site does not run lotteries" rather than "everything is drawn"
    public function testItReportsZeroRatherThanNothing(): void
    {
        $this->assertSame(['drawsAwaiting' => 0, 'oldestDrawAwaiting' => null], $this->createProvider()->getStatusData());
    }

    // The count of campaigns, of counterparts or of contributors is deliberately absent: a number nothing is done about is read once and buries what matters
    public function testItReportsNothingNobodyActsOn(): void
    {
        $this->assertSame(['drawsAwaiting', 'oldestDrawAwaiting'], array_keys($this->createProvider()->getStatusData()));
    }

    // Only counts and a date: the report leaves the site, and a receiver has no way to know a key is confidential
    public function testEveryValueItReportsIsACountOrADate(): void
    {
        foreach ($this->createProvider(count: 2, oldest: '2026-08-01 18:00:00')->getStatusData() as $value) {
            $this->assertTrue(is_int($value) || is_string($value) || null === $value);
        }
    }

    // Found by TaggedInterfacePass through the contract, not by a tag written by hand
    public function testItImplementsTheConfigContract(): void
    {
        $this->assertInstanceOf(StatusProviderInterface::class, $this->createProvider());
    }

    // Two aggregate queries on the same builder, answered here rather than run: what they select is what the assertions above read back
    private function createProvider(int $count = 0, ?string $oldest = null): CrowdfundingStatusProvider
    {
        // QueryBuilder::getQuery() declares Doctrine's own Query, which a stub of its parent cannot stand in for
        $query = $this->createStub(Query::class);
        $query->method('getSingleScalarResult')->willReturnCallback(static function () use (&$selected, $count, $oldest): mixed {
            // Read at call time, not at definition: the same builder answers the count then the date, and it is its select() that says which
            return str_contains((string) $selected, 'COUNT') ? $count : $oldest;
        });

        $queryBuilder = $this->createStub(QueryBuilder::class);
        foreach (['join', 'where', 'andWhere', 'setParameter'] as $method) {
            $queryBuilder->method($method)->willReturnSelf();
        }
        $queryBuilder->method('select')->willReturnCallback(static function (string $expression) use ($queryBuilder, &$selected): QueryBuilder {
            $selected = $expression;

            return $queryBuilder;
        });
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->createStub(LotteryPrizeRepository::class);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        return new CrowdfundingStatusProvider($repository);
    }
}
