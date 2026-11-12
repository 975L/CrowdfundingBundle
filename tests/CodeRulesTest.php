<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests;

use c975L\ConfigBundle\Test\CodeRulesTestCase;

// The audit's rules (ConfigBundle) replayed on this bundle's code and templates
class CodeRulesTest extends CodeRulesTestCase
{
    // Where the bundle's classes live
    protected function sourceDirectory(): string
    {
        return __DIR__ . '/../src';
    }

    // The namespace those classes are declared in
    protected function sourceNamespace(): string
    {
        return 'c975L\CrowdfundingBundle';
    }

    // The counterparts' runs are read on pages that are never cached (the counterparts and campaigns kinds are cacheable: false), so their counts have no fragment to drop
    protected function allowed(): array
    {
        $reason = 'A run is read on uncached renders only: no cache fragment holds a counterpart';

        return [
            self::BULK_QUERY => [
                'c975L\CrowdfundingBundle\Repository\CrowdfundingCounterpartRepository::claimOrdered' => $reason,
                'c975L\CrowdfundingBundle\Repository\CrowdfundingCounterpartRepository::addOrdered' => $reason,
                'c975L\CrowdfundingBundle\Repository\CrowdfundingCounterpartRepository::releaseOrdered' => $reason,
            ],
        ];
    }
}
