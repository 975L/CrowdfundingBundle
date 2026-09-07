<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Management;

use c975L\ConfigBundle\Management\BackupPath;
use c975L\ConfigBundle\Management\BackupPathProviderInterface;

// Where the campaign and counterpart uploads land, the only content of this bundle a git clone and a database dump cannot bring back - ConfigBundle backs up nothing it was not declared, so a bundle staying silent here is a campaign backed up nowhere
class CrowdfundingBackupPathProvider implements BackupPathProviderInterface
{
    // One root rather than four: "crowdfundings" and "counterparts" both sit under it (see each Media's getVichMediaPath), and the collector drops a path nested inside another already declared
    public function getBackupPaths(): array
    {
        return [
            // Mirrored rather than archived: a presentation video runs to hundreds of megabytes, bzip2 gains about nothing on a webp or an mp4, and an upload needs a copy rather than a history
            new BackupPath('public/medias/crowdfunding', BackupPath::MODE_MIRROR),
        ];
    }
}
