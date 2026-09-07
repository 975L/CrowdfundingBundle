<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;

interface CrowdfundingCounterpartServiceInterface
{
    /**
     * @return CrowdfundingCounterpart|null null when no counterpart carries that id
     */
    public function findOneById(int $id): ?CrowdfundingCounterpart;
}
