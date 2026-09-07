<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Entity;

use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use PHPUnit\Framework\TestCase;

class CrowdfundingContributorTest extends TestCase
{
    // The relation collection is read by the campaign page before anything is added to it
    public function testTheRelationCollectionIsUsableOnAContributorJustBuilt(): void
    {
        $this->assertCount(0, new CrowdfundingContributor()->getContributorCounterparts());
    }

    // A contributor is identified by their email: the name is optional, an anonymous contribution carrying none
    public function testAContributorIsPrintedAsTheirEmail(): void
    {
        $this->assertSame('camille@example.com', (string) new CrowdfundingContributor()->setEmail('camille@example.com'));
    }

    // The quantity lives on the join row rather than on either side of it: the same contributor can take three of one counterpart and one of another
    public function testAddCounterpartWritesTheJoinRowWithItsQuantity(): void
    {
        $contributor = new CrowdfundingContributor();
        $counterpart = new CrowdfundingCounterpart();

        $contributor->addCounterpart($counterpart, 3);

        $relation = $contributor->getContributorCounterparts()[0];
        $this->assertSame($contributor, $relation->getContributor());
        $this->assertSame($counterpart, $relation->getCounterpart());
        $this->assertSame(3, $relation->getQuantity());
    }

    // The thank-you page prints whatever the contributor typed, and prints nothing where they typed nothing
    public function testAContributorMayGiveNeitherNameNorMessage(): void
    {
        $contributor = new CrowdfundingContributor()->setEmail('camille@example.com');

        $this->assertNull($contributor->getName());
        $this->assertNull($contributor->getMessage());
    }
}
