<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Entity;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingMedia;
use c975L\UiBundle\Contract\VichImageResizableInterface;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use PHPUnit\Framework\TestCase;

class CrowdfundingMediaTest extends TestCase
{
    // Regression: implementing neither contract made UiMediaNamer throw on any upload
    public function testImplementsTheContractsRequiredByUiMediaNamer(): void
    {
        $media = new CrowdfundingMedia();

        $this->assertInstanceOf(VichMediaNamableInterface::class, $media);
        $this->assertInstanceOf(VichImageResizableInterface::class, $media);
    }

    public function testGetVichMediaPathUsesTheParentCrowdfundingSlug(): void
    {
        $crowdfunding = new Crowdfunding();
        $crowdfunding->setSlug('save-the-cats');

        $media = new CrowdfundingMedia();
        $media->setCrowdfunding($crowdfunding);

        $this->assertSame('medias/crowdfunding/crowdfundings/save-the-cats', $media->getVichMediaPath());
    }

    public function testGetImageWidthReturnsAPositiveInt(): void
    {
        $this->assertGreaterThan(0, new CrowdfundingMedia()->getImageWidth());
    }
}
