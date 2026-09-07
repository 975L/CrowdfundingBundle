<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Management;

use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\UiBundle\Contract\BlockOwnerResolverInterface;
use c975L\UiBundle\Contract\HasBlocksInterface;

// Lets BlockMoveController relocate a campaign's Block without depending on the Crowdfunding class
class CrowdfundingBlockOwnerResolver implements BlockOwnerResolverInterface
{
    // Shared with CrowdfundingCrudController's own blockMoveRowAttr() call, so the owner-type string only ever exists in one place
    public const TYPE_CROWDFUNDING = 'crowdfunding';

    public function __construct(
        private readonly CrowdfundingRepository $crowdfundingRepository,
    ) {
    }

    public function supports(string $ownerType): bool
    {
        return self::TYPE_CROWDFUNDING === $ownerType;
    }

    public function find(string $ownerType, int $ownerId): ?HasBlocksInterface
    {
        return $this->supports($ownerType) ? $this->crowdfundingRepository->find($ownerId) : null;
    }
}
