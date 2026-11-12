<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributorCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Message\CrowdfundingContributionMessage;
use c975L\CrowdfundingBundle\Message\LotteryTicketsMessage;
use c975L\CrowdfundingBundle\Repository\CrowdfundingContributorRepository;
use c975L\CrowdfundingBundle\Repository\CrowdfundingCounterpartRepository;
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\CrowdfundingBundle\Repository\LotteryPrizeRepository;
use c975L\CrowdfundingBundle\Repository\LotteryTicketRepository;
use c975L\PaymentBundle\Contract\RefundableBasketItemProviderInterface;
use c975L\PaymentBundle\Contract\ShippingBasketItemProviderInterface;
use c975L\PaymentBundle\Entity\Basket;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Plugs counterparts into PaymentBundle's checkout, and owns the crowdfunding-specific parts of that flow
class CrowdfundingBasketItemProvider implements RefundableBasketItemProviderInterface, ShippingBasketItemProviderInterface
{
    public function __construct(
        private readonly CrowdfundingCounterpartServiceInterface $crowdfundingCounterpartService,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly TranslatorInterface $translator,
        private readonly LotteryServiceInterface $lotteryService,
        private readonly CrowdfundingCounterpartRepository $crowdfundingCounterpartRepository,
        private readonly CrowdfundingRepository $crowdfundingRepository,
        private readonly LoggerInterface $logger,
        private readonly CrowdfundingContributorRepository $crowdfundingContributorRepository,
        private readonly LotteryTicketRepository $lotteryTicketRepository,
        private readonly LotteryPrizeRepository $lotteryPrizeRepository,
    ) {
    }

    public function getKind(): string
    {
        return 'crowdfunding';
    }

    public function findItem(int | string $id): ?object
    {
        return $this->crowdfundingCounterpartService->findOneById((int) $id);
    }

    public function validateAddition(object $item, int $quantity): ?string
    {
        if (0 === $item->getLimitedQuantity()) {
            return $this->translator->trans('label.unavailable', [], 'crowdfunding');
        }

        // A campaign not opened yet, or one sitting in the recycle bin, is not contributed to - its page is a 404 or a 410, and a tier already in a basket would otherwise still go through checkout
        if ($item->getCrowdfunding()->isHidden() || $item->getCrowdfunding()->isDeleted()) {
            return $this->translator->trans('label.unavailable', [], 'crowdfunding');
        }

        // A campaign without dates is not open for contributions
        if (null === $item->getCrowdfunding()->getBeginDate() || null === $item->getCrowdfunding()->getEndDate()) {
            return $this->translator->trans('label.unavailable', [], 'crowdfunding');
        }

        $beginDatetime = new \DateTime($item->getCrowdfunding()->getBeginDate()->format('Y-m-d 00:00:00'));
        $endDatetime = new \DateTime($item->getCrowdfunding()->getEndDate()->format('Y-m-d 23:59:59'));
        if ($beginDatetime > new \DateTime()) {
            return $this->translator->trans('label.crowdfunding_not_started', [], 'crowdfunding');
        }
        if (new \DateTime() > $endDatetime) {
            return $this->translator->trans('label.crowdfunding_ended', [], 'crowdfunding');
        }

        if ($item->getLimitedQuantity() > 0) {
            $alreadyOrdered = $item->getOrderedQuantity() ?? 0;
            $canAdd = $item->getLimitedQuantity() - $alreadyOrdered;
            if ($canAdd <= 0) {
                return $this->translator->trans('label.no_more_items_available', [], 'crowdfunding');
            }
        }

        return null;
    }

    public function toBasketData(object $item, int $quantity): array
    {
        $itemData = $item->toArray();
        unset($itemData['creation'], $itemData['modification'], $itemData['user']);
        $itemData['media'] = $item->getMedia() ? $item->getMedia()->getName() : null;

        $crowdfunding = $item->getCrowdfunding();

        return [
            'item' => $itemData,
            'parent' => [
                'title' => $crowdfunding->getTitle(),
                'slug' => $crowdfunding->getSlug(),
                'image' => $crowdfunding->getMedias()->isEmpty() ? null : $crowdfunding->getMedias()[0]->getName(),
            ],
            'type' => 'crowdfunding',
            'quantity' => $quantity,
            'totalVat' => 0,
            'total' => $quantity * $item->getPrice(),
        ];
    }

    public function getContentFlags(array $itemData): int
    {
        return ($itemData['item']['requiresShipping'] ?? false)
            ? Basket::CONTENT_FLAG_CF_SHIPPING
            : Basket::CONTENT_FLAG_CF_DIGITAL;
    }

    // Whether a running campaign offers a counterpart that is posted, so a site with only digital counterparts is not told to fill a delivery grid - see ShippingBasketItemProviderInterface
    public function shipsParcels(): bool
    {
        return $this->crowdfundingCounterpartRepository->hasShippedCounterpart();
    }

    // The only check standing between filling a basket and paying for it: a basket sits for days, and in between a campaign ends, a counterpart runs out or is withdrawn
    public function validateCheckout(Basket $basket, array $itemsOfThisKind): ?string
    {
        foreach ($itemsOfThisKind as $id => $itemContent) {
            $counterpart = $this->crowdfundingCounterpartService->findOneById((int) $id);

            // Deleted outright while the basket held it: there is nothing left to give and nothing left to name
            if (null === $counterpart) {
                return $this->translator->trans('label.unavailable', [], 'crowdfunding');
            }

            $error = $this->validateAddition($counterpart, (int) $itemContent['quantity']);
            if (null !== $error) {
                return $error;
            }

            // What validateAddition() cannot ask, having only ever seen one click at a time: the whole basket quantity against what the run has left
            if ($counterpart->getLimitedQuantity() > 0 && (int) $itemContent['quantity'] > $counterpart->getLimitedQuantity() - ($counterpart->getOrderedQuantity() ?? 0)) {
                return $this->translator->trans('label.no_more_items_available', [], 'crowdfunding');
            }
        }

        return null;
    }

    // Handed over to PaymentBundle, which keeps it on the basket and gives it back once the payment is confirmed - turned into a real Contributor only then. Nothing goes in the session: the payment provider confirms on a request of its own, carrying no session of this contributor
    public function onBasketValidated(Basket $basket, array $itemsOfThisKind, array $requestData): array
    {
        $counterpartsArray = [];
        foreach ($itemsOfThisKind as $counterpartData) {
            $counterpart = $this->crowdfundingCounterpartService->findOneById($counterpartData['item']['id']);
            if ($counterpart) {
                $counterpartsArray[$counterpartData['item']['id']] = $counterpartData['quantity'];
            }
        }

        // Cut to the columns' lengths here, a value too long otherwise failing the delivery of a paid order
        return [
            'name' => self::cut($requestData['coordinates']['contributorName'] ?? null, 50),
            'message' => self::cut($requestData['coordinates']['contributorMessage'] ?? null, 255),
            'email' => $basket->getEmail(),
            'counterparts' => $counterpartsArray,
        ];
    }

    // Counts the runs and the campaigns' totals, registers one contributor per campaign with its counterparts and tickets, then thanks the contributor - a test basket counting nothing real
    public function onBasketPaid(Basket $basket, array $itemsOfThisKind, array $checkoutData): void
    {
        if ($basket->isTestMode()) {
            return;
        }

        $contributors = [];
        foreach ($this->groupByCampaign($basket, $itemsOfThisKind) as $group) {
            if ($group['amount'] > 0) {
                $this->crowdfundingRepository->addAchieved($group['crowdfunding'], $group['amount']);
                $group['crowdfunding']->setModification(new \DateTime());
            }

            // What onBasketValidated() handed over, kept on the basket by PaymentBundle - an order placed before it existed, or one given for nothing, writes no public contributor
            if ([] !== $checkoutData && $group['amount'] > 0) {
                $contributors[] = $this->registerContributor($basket, $group, $checkoutData);
            }
        }

        $this->entityManager->flush();

        // After the flush, and only then: the contributor's id is assigned by the database, and the message declares it an int
        foreach ($contributors as $contributor) {
            $this->messageBus->dispatch(new LotteryTicketsMessage((int) $contributor->getId()));
        }
        if ([] !== $itemsOfThisKind) {
            $this->messageBus->dispatch(new CrowdfundingContributionMessage((int) $basket->getId()));
        }
    }

    // A full refund takes back what the payment handed over: the campaigns' totals and the runs it counted, the contributors it listed and their undrawn tickets - a test basket having counted none
    public function onBasketRefunded(Basket $basket, array $itemsOfThisKind): void
    {
        if ($basket->isTestMode()) {
            return;
        }

        foreach ($this->groupByCampaign($basket, $itemsOfThisKind, false) as $group) {
            if ($group['amount'] > 0) {
                $this->crowdfundingRepository->releaseAchieved($group['crowdfunding'], $group['amount']);
                $group['crowdfunding']->setModification(new \DateTime());
            }
            foreach ($group['lines'] as [$counterpart, $quantity]) {
                $this->crowdfundingCounterpartRepository->releaseOrdered($counterpart, $quantity);
                $counterpart->setModification(new \DateTime());
            }
        }

        foreach ($this->crowdfundingContributorRepository->findBy(['basket' => $basket]) as $contributor) {
            $this->removeContributor($basket, $contributor);
        }

        $this->entityManager->flush();
    }

    // The basket's lines gathered by campaign, each with what it brought - a counterpart deleted since counting nothing. Counted against its run on payment only
    /** @return array<int, array{crowdfunding: Crowdfunding, amount: int, lines: list<array{CrowdfundingCounterpart, int, int}>}> */
    private function groupByCampaign(Basket $basket, array $itemsOfThisKind, bool $countRuns = true): array
    {
        $groups = [];
        foreach ($itemsOfThisKind as $id => $line) {
            $counterpart = $this->crowdfundingCounterpartService->findOneById((int) $id);
            $crowdfunding = $counterpart?->getCrowdfunding();
            if (null === $crowdfunding) {
                continue;
            }

            $quantity = (int) ($line['quantity'] ?? 0);
            if ($countRuns) {
                $this->countOrdered($basket, $counterpart, $quantity);
            }

            // A line frozen before the basket stored its total is read at the price it was sold
            $line['total'] ??= (int) $counterpart->getPrice() * $quantity;
            $amount = CrowdfundingContributionAmount::ofLine($basket, $line);
            $key = spl_object_id($crowdfunding);
            $groups[$key] ??= ['crowdfunding' => $crowdfunding, 'amount' => 0, 'lines' => []];
            $groups[$key]['amount'] += $amount;
            $groups[$key]['lines'][] = [$counterpart, $quantity, $amount];
        }

        return $groups;
    }

    // One contributor for one campaign, listing its counterparts - tickets drawn for the lines actually paid for
    /** @param array{crowdfunding: Crowdfunding, amount: int, lines: list<array{CrowdfundingCounterpart, int, int}>} $group */
    private function registerContributor(Basket $basket, array $group, array $checkoutData): CrowdfundingContributor
    {
        $contributor = new CrowdfundingContributor();
        $contributor->setName(self::cut($checkoutData['name'] ?? null, 50));
        $contributor->setMessage(self::cut($checkoutData['message'] ?? null, 255));
        $contributor->setEmail((string) ($checkoutData['email'] ?? $basket->getEmail()));
        // The language the order was placed in, which is the only thing that will still say it when the lottery is drawn months later
        $contributor->setLocale($basket->getLocale());
        $contributor->setCreation(new \DateTime());
        $contributor->setModification(new \DateTime());
        $contributor->setBasket($basket);
        $group['crowdfunding']->addContributor($contributor);
        $this->entityManager->persist($contributor);

        foreach ($group['lines'] as [$counterpart, $quantity, $amount]) {
            $contributorCounterpart = new CrowdfundingContributorCounterpart();
            $contributorCounterpart->setContributor($contributor);
            $contributorCounterpart->setCounterpart($counterpart);
            $contributorCounterpart->setQuantity($quantity);
            $this->entityManager->persist($contributorCounterpart);

            if ($amount > 0) {
                $this->lotteryService->generateTicketsForContributor($contributor, $counterpart, $quantity);
            }
        }

        return $contributor;
    }

    // A refunded contributor leaves the list with its undrawn tickets; one holding a ticket already drawn keeps that ticket, so the row stays out of the list and the shop is told
    private function removeContributor(Basket $basket, CrowdfundingContributor $contributor): void
    {
        $tickets = $this->lotteryTicketRepository->findBy(['contributor' => $contributor]);
        $won = [] === $tickets ? [] : array_map(static fn ($prize) => $prize->getWinningTicket(), $this->lotteryPrizeRepository->findBy(['winningTicket' => $tickets]));

        foreach ($tickets as $ticket) {
            if (!\in_array($ticket, $won, true)) {
                $this->entityManager->remove($ticket);
            }
        }

        $contributor->getCrowdfunding()?->removeContributor($contributor);
        if ([] === $won) {
            $this->entityManager->remove($contributor);

            return;
        }

        $this->logger->warning('Basket {number} refunded: contributor {id} holds a ticket already drawn, kept out of the list', ['number' => $basket->getNumber(), 'id' => $contributor->getId()]);
    }

    // A text the contributor typed, trimmed and cut to its column, null when empty or not a text
    private static function cut(mixed $value, int $length): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = mb_substr(trim($value), 0, $length);

        return '' === $value ? null : $value;
    }

    // The database says whether the run still held this order, two payments at the same second both reading the same count. The customer has paid, so a refusal takes nothing back: the count goes past the run and stays true, which is what the dashboard reads (see CrowdfundingAlertProvider), and the shop is told. Dated so a run counted here still invalidates the pages showing it
    private function countOrdered(Basket $basket, CrowdfundingCounterpart $counterpart, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $counterpart->setModification(new \DateTime());

        if ($this->crowdfundingCounterpartRepository->claimOrdered($counterpart, $quantity)) {
            return;
        }

        $this->crowdfundingCounterpartRepository->addOrdered($counterpart, $quantity);
        $this->logger->critical('Basket {number}: counterpart {id} oversold, {quantity} paid for past its run', ['number' => $basket->getNumber(), 'id' => $counterpart->getId(), 'quantity' => $quantity]);
    }
}
