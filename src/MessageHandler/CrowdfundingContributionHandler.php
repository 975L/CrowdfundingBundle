<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\MessageHandler;

use c975L\CrowdfundingBundle\Email\CrowdfundingEmailSender;
use c975L\CrowdfundingBundle\Message\CrowdfundingContributionMessage;
use c975L\PaymentBundle\Repository\BasketRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CrowdfundingContributionHandler
{
    public function __construct(
        private readonly BasketRepository $basketRepository,
        private readonly CrowdfundingEmailSender $emailSender,
    ) {
    }

    public function __invoke(CrowdfundingContributionMessage $message): void
    {
        $basket = $this->basketRepository->find($message->getBasketId());

        if (!$basket) {
            return;
        }

        // A basket may hold products, gift cards and counterparts at once: only the lines filed under this bundle's own kind belong in this email
        $counterparts = $basket->getItems()['crowdfunding'] ?? [];

        if (empty($counterparts)) {
            return;
        }

        $this->emailSender->send(
            'crowdfunding_contribution',
            'label.crowdfunding_contribution',
            (string) $basket->getEmail(),
            ['basket' => $basket, 'counterparts' => $counterparts],
            $basket->getLocale(),
            $this->campaignTitle($counterparts),
        );
    }

    // The campaign the counterparts belong to, read off the copy the basket froze: a contribution never spans two campaigns
    private function campaignTitle(array $counterparts): string
    {
        foreach ($counterparts as $counterpart) {
            return (string) ($counterpart['parent']['title'] ?? '');
        }

        return '';
    }
}
