<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use c975L\PaymentBundle\Entity\Basket;

// What a basket line brought to its campaign, read the same way at payment and at refund so a refund takes back exactly what was counted
final class CrowdfundingContributionAmount
{
    // The line total the basket froze, minus its share of a promotional code - a gift card being a real payment, it takes nothing off
    /** @param array<string, mixed> $line */
    public static function ofLine(Basket $basket, array $line): int
    {
        $total = max(0, (int) ($line['total'] ?? 0));
        $basketTotal = (int) $basket->getTotal();
        if (0 === $total || Basket::CODE_KIND_DISCOUNT !== $basket->getDiscountKind() || $basketTotal <= 0) {
            return $total;
        }

        // Prorated on the whole basket, the share never more than the line itself
        $share = intdiv($total * $basket->getDiscountAmount(), $basketTotal);

        return max(0, $total - min($share, $total));
    }
}
