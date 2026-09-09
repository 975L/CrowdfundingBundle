<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Controller;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Controller\LotteryController;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Entity\LotteryTicket;
use c975L\CrowdfundingBundle\Service\LotteryServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

class LotteryControllerTest extends TestCase
{
    public function testDisplayRendersTheLotteryPage(): void
    {
        $response = $this->createController()->display(new Lottery());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('@c975LCrowdfunding/lottery/display.html.twig', $response->getContent());
    }

    // The draw is an admin's action on a POST anyone knowing the lottery's url reaches, so its own url would otherwise lead round what the campaign page hides
    public function testADrawOfATrashedCampaignAnswersGone(): void
    {
        $this->expectException(GoneHttpException::class);

        $this->createController()->display(new Lottery()->setCrowdfunding(new Crowdfunding()->setIsDeleted(true)));
    }

    public function testADrawOfACampaignNotOpenedYetAnswersNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController()->display(new Lottery()->setCrowdfunding(new Crowdfunding()));
    }

    public function testDrawingAPrizeIsRefusedToAnybodyButAnAdmin(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->createController(granted: false)->drawPrize(new Lottery(), 1);
    }

    // A lottery an admin has closed is over: its prizes are drawn, or the campaign decided not to
    public function testDrawingOnAClosedLotteryIsRefused(): void
    {
        $response = $this->createController()->drawPrize(new Lottery()->setIsActive(false), 1);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame(['error' => 'Lottery not active'], json_decode($response->getContent(), true));
    }

    // No prize of that rank, or no ticket left to draw from: the screen is told so rather than shown a winner it invented
    public function testDrawingAnswersNotFoundWhenThereIsNothingToDraw(): void
    {
        $lotteryService = $this->createStub(LotteryServiceInterface::class);
        $lotteryService->method('drawWinner')->willReturn(null);

        $response = $this->createController(lotteryService: $lotteryService)->drawPrize(new Lottery(), 1);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    // The number and the holder's name are what the wheel shows once it stops
    public function testDrawingAnswersTheWinningNumberAndItsHolder(): void
    {
        $ticket = new LotteryTicket()
            ->setNumber('AB-1234-CDE')
            ->setContributor(new CrowdfundingContributor()->setName('Camille'))
        ;

        $lotteryService = $this->createStub(LotteryServiceInterface::class);
        $lotteryService->method('drawWinner')->willReturn($ticket);

        $response = $this->createController(lotteryService: $lotteryService)->drawPrize(new Lottery(), 1);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['number' => 'AB-1234-CDE', 'name' => 'Camille'], json_decode($response->getContent(), true));
    }

    // The public url is keyed on the thirteen-character identifier the listener draws, and the draw is a POST on a single-digit rank
    public function testTheRoutesConstrainTheIdentifierAndTheRank(): void
    {
        $routes = [];
        foreach (new \ReflectionClass(LotteryController::class)->getMethods() as $method) {
            foreach ($method->getAttributes(Route::class) as $attribute) {
                $arguments = $attribute->getArguments();
                $routes[$arguments['name']] = $arguments;
            }
        }

        $this->assertSame(['GET'], $routes['lottery_display']['methods']);
        $this->assertSame('^([a-zA-Z0-9\-]{13})', $routes['lottery_display']['requirements']['identifier']);
        $this->assertSame(['POST'], $routes['lottery_draw_prize']['methods']);
        $this->assertSame('^[0-5]$', $routes['lottery_draw_prize']['requirements']['rank']);
    }

    private function createController(?LotteryServiceInterface $lotteryService = null, bool $granted = true): LotteryController
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_ADMIN');

        $controller = new LotteryController(
            $lotteryService ?? $this->createStub(LotteryServiceInterface::class),
            $configService,
        );

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static fn (string $view): string => $view);

        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($granted);

        $container = new Container();
        $container->set('twig', $twig);
        $container->set('security.authorization_checker', $authorizationChecker);
        $controller->setContainer($container);

        return $controller;
    }
}
