<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Service;

use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Repository\CrowdfundingCounterpartRepository;
use c975L\CrowdfundingBundle\Service\CrowdfundingCounterpartService;
use c975L\CrowdfundingBundle\Service\CrowdfundingCounterpartServiceInterface;
use PHPUnit\Framework\TestCase;

// The one lookup the checkout goes through: PaymentBundle resolves every basket line of this bundle's kind here
class CrowdfundingCounterpartServiceTest extends TestCase
{
    public function testItImplementsItsOwnContract(): void
    {
        $this->assertInstanceOf(CrowdfundingCounterpartServiceInterface::class, $this->createService());
    }

    public function testFindOneByIdHandsBackTheCounterpart(): void
    {
        $counterpart = new CrowdfundingCounterpart();

        $repository = $this->createMock(CrowdfundingCounterpartRepository::class);
        $repository->expects($this->once())->method('find')->with(7)->willReturn($counterpart);

        $this->assertSame($counterpart, $this->createService($repository)->findOneById(7));
    }

    // A counterpart deleted while a basket holding it sat in session: the checkout drops the line rather than throwing
    public function testFindOneByIdAnswersNullForAnUnknownCounterpart(): void
    {
        $repository = $this->createStub(CrowdfundingCounterpartRepository::class);
        $repository->method('find')->willReturn(null);

        $this->assertNull($this->createService($repository)->findOneById(7));
    }

    private function createService(?CrowdfundingCounterpartRepository $repository = null): CrowdfundingCounterpartService
    {
        return new CrowdfundingCounterpartService($repository ?? $this->createStub(CrowdfundingCounterpartRepository::class));
    }
}
