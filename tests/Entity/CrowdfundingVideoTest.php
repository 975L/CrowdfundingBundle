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
use c975L\CrowdfundingBundle\Entity\CrowdfundingVideo;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use PHPUnit\Framework\TestCase;

class CrowdfundingVideoTest extends TestCase
{
    // Regression: implementing no naming contract made UiMediaNamer throw on any upload
    public function testImplementsVichMediaNamableInterface(): void
    {
        $this->assertInstanceOf(VichMediaNamableInterface::class, new CrowdfundingVideo());
    }

    public function testGetVichMediaPathUsesTheParentCrowdfundingSlugWithAVideoSuffix(): void
    {
        $crowdfunding = new Crowdfunding();
        $crowdfunding->setSlug('save-the-cats');

        $video = new CrowdfundingVideo();
        $video->setCrowdfunding($crowdfunding);

        $this->assertSame('medias/crowdfunding/crowdfundings/save-the-cats-video', $video->getVichMediaPath());
    }
}
