<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Entity;

use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpartMedia;
use c975L\UiBundle\Contract\VichImageResizableInterface;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use PHPUnit\Framework\TestCase;

class CrowdfundingCounterpartMediaTest extends TestCase
{
    // Regression: implementing neither contract made UiMediaNamer throw on any upload
    public function testImplementsTheContractsRequiredByUiMediaNamer(): void
    {
        $media = new CrowdfundingCounterpartMedia();

        $this->assertInstanceOf(VichMediaNamableInterface::class, $media);
        $this->assertInstanceOf(VichImageResizableInterface::class, $media);
    }

    public function testGetVichMediaPathUsesTheParentCounterpartSlug(): void
    {
        $counterpart = new CrowdfundingCounterpart();
        $counterpart->setSlug('early-bird-tshirt');

        $media = new CrowdfundingCounterpartMedia();
        $media->setCrowdfundingCounterpart($counterpart);

        $this->assertSame('medias/crowdfunding/counterparts/early-bird-tshirt', $media->getVichMediaPath());
    }

    public function testGetImageWidthReturnsAPositiveInt(): void
    {
        $this->assertGreaterThan(0, new CrowdfundingCounterpartMedia()->getImageWidth());
    }
}
