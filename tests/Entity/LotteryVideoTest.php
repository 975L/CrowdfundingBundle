<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Entity;

use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Entity\LotteryVideo;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use PHPUnit\Framework\TestCase;

class LotteryVideoTest extends TestCase
{
    // Regression: implementing no naming contract made UiMediaNamer throw on any upload
    public function testImplementsVichMediaNamableInterface(): void
    {
        $this->assertInstanceOf(VichMediaNamableInterface::class, new LotteryVideo());
    }

    public function testGetVichMediaPathUsesTheParentLotteryIdentifierWithAVideoSuffix(): void
    {
        $lottery = new Lottery();
        $lottery->setIdentifier('lottery-2026-001');

        $video = new LotteryVideo();
        $video->setLottery($lottery);

        $this->assertSame('medias/crowdfunding/crowdfundings/lottery-2026-001-video', $video->getVichMediaPath());
    }
}
