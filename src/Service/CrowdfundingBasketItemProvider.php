<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use c975L\PaymentBundle\Entity\Basket;
use c975L\PaymentBundle\Contract\BasketItemProviderInterface;
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributorCounterpart;
use c975L\CrowdfundingBundle\Message\LotteryTicketsMessage;

// Plugs crowdfunding counterparts into PaymentBundle's Basket/checkout engine (see BasketItemProviderInterface) -
// also owns the crowdfunding-specific parts of the checkout flow that used to live in ShopBundle's BasketService
// directly (contributor registration, lottery ticket generation)
class CrowdfundingBasketItemProvider implements BasketItemProviderInterface
{
    public function __construct(
        private readonly CrowdfundingCounterpartServiceInterface $crowdfundingCounterpartService,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly MessageBusInterface $messageBus,
        private readonly TranslatorInterface $translator,
        private readonly LotteryServiceInterface $lotteryService,
    ) {
    }

    public function getKind(): string
    {
        return 'crowdfunding';
    }

    public function findItem(int|string $id): ?object
    {
        return $this->crowdfundingCounterpartService->findOneById((int) $id);
    }

    public function validateAddition(object $item, int $quantity): ?string
    {
        if (0 === $item->getLimitedQuantity()) {
            return $this->translator->trans('label.unavailable', [], 'shop');
        }

        $beginDatetime = new DateTime($item->getCrowdfunding()->getBeginDate()->format('Y-m-d 00:00:00'));
        $endDatetime = new DateTime($item->getCrowdfunding()->getEndDate()->format('Y-m-d 23:59:59'));
        if ($beginDatetime > new DateTime()) {
            return $this->translator->trans('label.crowdfunding_not_started', [], 'shop');
        }
        if (new DateTime() > $endDatetime) {
            return $this->translator->trans('label.crowdfunding_ended', [], 'shop');
        }

        if ($item->getLimitedQuantity() > 0) {
            $alreadyOrdered = $item->getOrderedQuantity() ?? 0;
            $canAdd = $item->getLimitedQuantity() - $alreadyOrdered;
            if ($canAdd <= 0) {
                return $this->translator->trans('label.no_more_items_available', [], 'shop');
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

    // Stashes the contributor's name/message in session - turned into a real Contributor in onBasketPaid(),
    // once payment is actually confirmed (mirrors the former BasketService::defineContributor())
    public function onBasketValidated(Basket $basket, array $itemsOfThisKind, array $requestData): void
    {
        $session = $this->requestStack->getSession();

        $counterpartsArray = [];
        foreach ($itemsOfThisKind as $counterpartData) {
            $counterpart = $this->crowdfundingCounterpartService->findOneById($counterpartData['item']['id']);
            if ($counterpart) {
                $counterpartsArray[$counterpartData['item']['id']] = $counterpartData['quantity'];
            }
        }

        $session->set('contributor', [
            'name' => $requestData['coordinates']['contributorName'] ?? null,
            'message' => $requestData['coordinates']['contributorMessage'] ?? null,
            'email' => $basket->getEmail(),
            'basket_id' => $basket->getId(),
            'counterparts' => $counterpartsArray,
        ]);
    }

    // Registers the contributor + their counterparts, bumps orderedQuantity, generates lottery tickets -
    // mirrors the former BasketService::registerContributor() + the crowdfunding branch of updateOrderedQuantity()
    public function onBasketPaid(Basket $basket, array $itemsOfThisKind): void
    {
        foreach ($itemsOfThisKind as $id => $itemContent) {
            $counterpart = $this->crowdfundingCounterpartService->findOneById($id);
            if (null === $counterpart) {
                continue;
            }
            $counterpart->setOrderedQuantity(($counterpart->getOrderedQuantity() ?? 0) + $itemContent['quantity']);
        }

        $session = $this->requestStack->getSession();
        $contributorData = $session->get('contributor');
        if (null === $contributorData || $contributorData['basket_id'] !== $basket->getId()) {
            return;
        }

        $contributor = new CrowdfundingContributor();
        $contributor->setName(empty($contributorData['name']) ? null : $contributorData['name']);
        $contributor->setMessage(empty($contributorData['message']) ? null : $contributorData['message']);
        $contributor->setEmail($contributorData['email']);
        $contributor->setCreation(new DateTimeImmutable());
        $contributor->setModification(new DateTimeImmutable());
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
                $crowdfunding->setModification(new DateTimeImmutable());
                $crowdfunding->addContributor($contributor);

                $contributor->setCrowdfunding($crowdfunding);

                $this->entityManager->persist($crowdfunding);
            }

            $this->lotteryService->generateTicketsForContributor($contributor, $counterpart, $quantity);
        }

        $this->messageBus->dispatch(new LotteryTicketsMessage($contributor->getId()));

        $this->entityManager->flush();
        $session->remove('contributor');
    }
}
