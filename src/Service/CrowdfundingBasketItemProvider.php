<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributorCounterpart;
use c975L\CrowdfundingBundle\Message\LotteryTicketsMessage;
use c975L\PaymentBundle\Contract\BasketItemProviderInterface;
use c975L\PaymentBundle\Entity\Basket;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Plugs counterparts into PaymentBundle's checkout, and owns the crowdfunding-specific parts of that flow
class CrowdfundingBasketItemProvider implements BasketItemProviderInterface
{
    public function __construct(
        private readonly CrowdfundingCounterpartServiceInterface $crowdfundingCounterpartService,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly TranslatorInterface $translator,
        private readonly LotteryServiceInterface $lotteryService,
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
        return ($itemData['item']['requiresShipping'] ?? true)
            ? Basket::CONTENT_FLAG_CF_SHIPPING
            : Basket::CONTENT_FLAG_CF_DIGITAL;
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

        return [
            'name' => $requestData['coordinates']['contributorName'] ?? null,
            'message' => $requestData['coordinates']['contributorMessage'] ?? null,
            'email' => $basket->getEmail(),
            'counterparts' => $counterpartsArray,
        ];
    }

    // Registers the contributor and counterparts, bumps orderedQuantity and generates lottery tickets
    public function onBasketPaid(Basket $basket, array $itemsOfThisKind, array $checkoutData): void
    {
        foreach ($itemsOfThisKind as $id => $itemContent) {
            $counterpart = $this->crowdfundingCounterpartService->findOneById($id);
            if (null === $counterpart) {
                continue;
            }
            $counterpart->setOrderedQuantity(($counterpart->getOrderedQuantity() ?? 0) + $itemContent['quantity']);
        }

        // What onBasketValidated() handed over, kept on the basket by PaymentBundle - already this basket's own, so there is nothing to match it against
        $contributorData = $checkoutData;
        if ([] === $contributorData) {
            return;
        }

        $contributor = new CrowdfundingContributor();
        $contributor->setName(empty($contributorData['name']) ? null : $contributorData['name']);
        $contributor->setMessage(empty($contributorData['message']) ? null : $contributorData['message']);
        $contributor->setEmail($contributorData['email']);
        // The language the order was placed in, which is the only thing that will still say it when the lottery is drawn months later
        $contributor->setLocale($basket->getLocale());
        $contributor->setCreation(new \DateTime());
        $contributor->setModification(new \DateTime());
        $contributor->setBasket($basket);

        $this->entityManager->persist($contributor);

        foreach ($contributorData['counterparts'] as $id => $quantity) {
            $counterpart = $this->crowdfundingCounterpartService->findOneById($id);
            if (!$counterpart) {
                continue;
            }

            $contributorCounterpart = new CrowdfundingContributorCounterpart();
            $contributorCounterpart->setContributor($contributor);
            $contributorCounterpart->setCounterpart($counterpart);
            $contributorCounterpart->setQuantity($quantity);

            $this->entityManager->persist($contributorCounterpart);

            $crowdfunding = $counterpart->getCrowdfunding();
            if ($crowdfunding) {
                $amount = $counterpart->getPrice() * $quantity;
                $crowdfunding->setAmountAchieved($crowdfunding->getAmountAchieved() + $amount);
                $crowdfunding->setModification(new \DateTime());
                $crowdfunding->addContributor($contributor);

                $contributor->setCrowdfunding($crowdfunding);

                $this->entityManager->persist($crowdfunding);
            }

            $this->lotteryService->generateTicketsForContributor($contributor, $counterpart, $quantity);
        }

        $this->entityManager->flush();

        // After the flush, and only then: the contributor's id is assigned by the database, and the message declares it an int - dispatched before, a basket holding no counterpart at all handed the bus a null
        $this->messageBus->dispatch(new LotteryTicketsMessage($contributor->getId()));
    }
}
