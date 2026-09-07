<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Management;

use c975L\ConfigBundle\Management\BackupPath;
use c975L\ConfigBundle\Management\BackupPathProviderInterface;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpartMedia;
use c975L\CrowdfundingBundle\Entity\CrowdfundingMedia;
use c975L\CrowdfundingBundle\Entity\CrowdfundingVideo;
use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Entity\LotteryVideo;
use c975L\CrowdfundingBundle\Management\CrowdfundingBackupPathProvider;
use PHPUnit\Framework\TestCase;

class CrowdfundingBackupPathProviderTest extends TestCase
{
    public function testTheMediaRootIsMirrored(): void
    {
        $paths = new CrowdfundingBackupPathProvider()->getBackupPaths();

        $this->assertCount(1, $paths);
        $this->assertSame('public/medias/crowdfunding', $paths[0]->path);
        $this->assertSame(BackupPath::MODE_MIRROR, $paths[0]->mode);
    }

    // The declared root is what the four media kinds are actually written under - a file stored elsewhere would be backed up nowhere, and nothing would say so
    public function testTheDeclaredRootCoversEveryMediaKind(): void
    {
        $crowdfunding = new Crowdfunding()->setSlug('sauver-les-chats');

        $stored = [
            new CrowdfundingMedia()->setCrowdfunding($crowdfunding),
            new CrowdfundingVideo()->setCrowdfunding($crowdfunding),
            new CrowdfundingCounterpartMedia()->setCrowdfundingCounterpart(new CrowdfundingCounterpart()->setSlug('le-tote-bag')),
            new LotteryVideo()->setLottery(new Lottery()->setIdentifier('BCD-1A2B-3C4D')),
        ];

        $roots = array_map(static fn (BackupPath $path): string => $path->path, new CrowdfundingBackupPathProvider()->getBackupPaths());

        foreach ($stored as $media) {
            $path = 'public/' . $media->getVichMediaPath();
            $declared = array_filter($roots, static fn (string $root): bool => str_starts_with($path, $root . '/'));

            $this->assertNotEmpty($declared, sprintf('"%s" is backed up nowhere.', $path));
        }
    }

    // The legacy "medias/shop/..." folders of the ShopBundle era are deliberately left out: this bundle uploads nothing there any more
    public function testTheLegacyShopFoldersAreNotDeclared(): void
    {
        foreach (new CrowdfundingBackupPathProvider()->getBackupPaths() as $path) {
            $this->assertStringNotContainsString('medias/shop', $path->path);
        }
    }

    // Found by TaggedInterfacePass through the contract, not by a tag written by hand
    public function testItImplementsTheConfigContract(): void
    {
        $this->assertInstanceOf(BackupPathProviderInterface::class, new CrowdfundingBackupPathProvider());
    }
}
