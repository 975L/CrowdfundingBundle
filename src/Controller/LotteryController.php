<?php

namespace c975L\CrowdfundingBundle\Controller;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Service\LotteryServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LotteryController extends AbstractController
{
    public function __construct(
        private readonly LotteryServiceInterface $lotteryService,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // DISPLAY
    #[Route(
        '/shop/lottery/{identifier:lottery}',
        name: 'lottery_display',
        requirements: ['identifier' => '^([a-zA-Z0-9\-]{13})'],
        methods: ['GET']
    )]
    public function display(Lottery $lottery): Response
    {
        return $this->render('@c975LCrowdfunding/lottery/display.html.twig', [
            'lottery' => $lottery,
        ]);
    }

    // API endpoint to draw a winner for a specific prize
    #[Route(
        '/shop/lottery/{identifier:lottery}/draw/{rank}',
        name: 'lottery_draw_prize',
        requirements: [
            'identifier' => '^([a-zA-Z0-9\-]{13})',
            'rank' => '^[0-5]$',
        ],
        methods: ['POST']
    )]
    public function drawPrize(Lottery $lottery, int $rank): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        if (false === $lottery->isActive()) {
            return new JsonResponse(['error' => 'Lottery not active'], Response::HTTP_BAD_REQUEST);
        }

        // Defines winning ticket for specified rank
        $winningTicket = $this->lotteryService->drawWinner($lottery, $rank);
        if (!$winningTicket) {
            return new JsonResponse(['error' => 'No eligible tickets found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'number' => $winningTicket->getNumber(),
            'name' => $winningTicket->getContributor()->getName(),
        ]);
    }
}
