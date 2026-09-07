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
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributorCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use PHPUnit\Framework\TestCase;

// The join row between a contributor and a counterpart, and the only place the quantity taken can live: the same counterpart is taken by many contributors, each for a number of their own
class CrowdfundingContributorCounterpartTest extends TestCase
{
    public function testItTiesAContributorToACounterpartForAQuantity(): void
    {
        $contributor = new CrowdfundingContributor();
        $counterpart = new CrowdfundingCounterpart();

        $relation = new CrowdfundingContributorCounterpart()
            ->setContributor($contributor)
            ->setCounterpart($counterpart)
            ->setQuantity(2)
        ;

        $this->assertSame($contributor, $relation->getContributor());
        $this->assertSame($counterpart, $relation->getCounterpart());
        $this->assertSame(2, $relation->getQuantity());
    }

    // The column is NOT NULL: the quantity is declared an int rather than a nullable one, so a row cannot be written without it
    public function testTheQuantityIsNotNullable(): void
    {
        $this->assertSame('int', (string) new \ReflectionMethod(CrowdfundingContributorCounterpart::class, 'setQuantity')->getParameters()[0]->getType());
    }
}
