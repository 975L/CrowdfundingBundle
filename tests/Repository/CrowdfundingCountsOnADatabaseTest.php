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
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Repository\CrowdfundingCounterpartRepository;
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

// The run's and the campaign's statements executed on a real database, on tables holding the columns the entities really map to: a column named wrong fails here and nowhere else
#[RequiresPhpExtension('pdo_sqlite')]
class CrowdfundingCountsOnADatabaseTest extends TestCase
{
    private EntityManager $entityManager;

    private CrowdfundingCounterpartRepository $counterparts;

    private CrowdfundingRepository $campaigns;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2) . '/src/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(\CASE_LOWER, true));
        $config->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        $connection = $this->entityManager->getConnection();

        $counterpart = $this->entityManager->getClassMetadata(CrowdfundingCounterpart::class);
        $connection->executeStatement(\sprintf('CREATE TABLE %s (%s INTEGER PRIMARY KEY, %s VARCHAR(50), %s SMALLINT, %s SMALLINT)', $counterpart->getTableName(), $counterpart->getColumnName('id'), $counterpart->getColumnName('title'), $counterpart->getColumnName('limitedQuantity'), $counterpart->getColumnName('orderedQuantity')));

        $campaign = $this->entityManager->getClassMetadata(Crowdfunding::class);
        $connection->executeStatement(\sprintf('CREATE TABLE %s (%s INTEGER PRIMARY KEY, %s INTEGER)', $campaign->getTableName(), $campaign->getColumnName('id'), $campaign->getColumnName('amountAchieved')));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->counterparts = new CrowdfundingCounterpartRepository($registry);
        $this->campaigns = new CrowdfundingRepository($registry);
    }

    public function testTheRunDecidesAndTheCountStaysTrue(): void
    {
        $counterpart = $this->storedCounterpart(5, limited: 3, ordered: 2);

        $this->assertTrue($this->counterparts->claimOrdered($counterpart, 1));
        $this->assertFalse($this->counterparts->claimOrdered($counterpart, 1));
        $this->assertSame(3, $this->ordered());

        $this->counterparts->addOrdered($counterpart, 1);
        $this->assertSame(4, $this->ordered());
    }

    public function testNoRunRefusesNothingAndANullCountStartsAtZero(): void
    {
        $counterpart = $this->storedCounterpart(5, limited: null, ordered: null);

        $this->assertTrue($this->counterparts->claimOrdered($counterpart, 2));
        $this->assertSame(2, $this->ordered());
    }

    public function testTheOversoldTitlesReadOnlyARunPastItsCap(): void
    {
        $this->storedCounterpart(1, limited: 3, ordered: 4);
        $this->storedCounterpart(2, limited: 3, ordered: 3);
        $this->storedCounterpart(3, limited: null, ordered: 9);
        $this->storedCounterpart(4, limited: 0, ordered: 9);

        $this->assertSame(['Tier 1'], $this->counterparts->findOversoldTitles());
    }

    public function testTwoContributionsBothReachTheCampaignsTotal(): void
    {
        $this->entityManager->getConnection()->insert('crowdfunding_crowdfunding', ['id' => 7, 'amount_achieved' => null]);
        $campaign = new Crowdfunding();
        new \ReflectionProperty(Crowdfunding::class, 'id')->setValue($campaign, 7);

        $this->campaigns->addAchieved($campaign, 1500);
        $this->campaigns->addAchieved($campaign, 500);

        $this->assertSame(2000, (int) $this->entityManager->getConnection()->fetchOne('SELECT amount_achieved FROM crowdfunding_crowdfunding'));
    }

    // A refund takes its amount back off the total, never below zero
    public function testARefundTakesTheAmountBackNeverBelowZero(): void
    {
        $this->entityManager->getConnection()->insert('crowdfunding_crowdfunding', ['id' => 7, 'amount_achieved' => 2000]);
        $campaign = new Crowdfunding();
        new \ReflectionProperty(Crowdfunding::class, 'id')->setValue($campaign, 7);

        $this->campaigns->releaseAchieved($campaign, 1500);
        $this->assertSame(500, (int) $this->entityManager->getConnection()->fetchOne('SELECT amount_achieved FROM crowdfunding_crowdfunding'));

        $this->campaigns->releaseAchieved($campaign, 1500);
        $this->assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT amount_achieved FROM crowdfunding_crowdfunding'));
    }

    // A refund gives its quantity back to the run, never below zero
    public function testARefundGivesTheRunBackNeverBelowZero(): void
    {
        $counterpart = $this->storedCounterpart(5, limited: 3, ordered: 3);

        $this->counterparts->releaseOrdered($counterpart, 2);
        $this->assertSame(1, $this->ordered());

        $this->counterparts->releaseOrdered($counterpart, 2);
        $this->assertSame(0, $this->ordered());
    }

    private function storedCounterpart(int $id, ?int $limited, ?int $ordered): CrowdfundingCounterpart
    {
        $this->entityManager->getConnection()->insert('crowdfunding_counterpart', ['id' => $id, 'title' => 'Tier ' . $id, 'limited_quantity' => $limited, 'ordered_quantity' => $ordered]);

        $counterpart = new CrowdfundingCounterpart();
        new \ReflectionProperty(CrowdfundingCounterpart::class, 'id')->setValue($counterpart, $id);

        return $counterpart;
    }

    private function ordered(): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne('SELECT ordered_quantity FROM crowdfunding_counterpart');
    }
}
