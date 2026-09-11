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
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\CrowdfundingBundle\Controller\LotteryController;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Entity\LotteryTicket;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslatedLocales;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslator;
use c975L\CrowdfundingBundle\Service\LotteryServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

class LotteryControllerTest extends TestCase
{
    public function testDisplayRendersTheLotteryPage(): void
    {
        $response = $this->createController()->display(new Lottery(), new Request());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('@c975LCrowdfunding/lottery/display.html.twig', $response->getContent());
    }

    // The draw is an admin's action on a POST anyone knowing the lottery's url reaches, so its own url would otherwise lead round what the campaign page hides
    public function testADrawOfATrashedCampaignAnswersGone(): void
    {
        $this->expectException(GoneHttpException::class);

        $this->createController()->display(new Lottery()->setCrowdfunding(new Crowdfunding()->setIsDeleted(true)), new Request());
    }

    public function testADrawOfACampaignNotOpenedYetAnswersNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController()->display(new Lottery()->setCrowdfunding(new Crowdfunding()), new Request());
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

    // A site declaring one language, which is every site until it says otherwise: the negotiator then refuses nothing, redirects nowhere and varies on nothing
    private static function createSiteLocales(): SiteLocales
    {
        return new SiteLocales(['fr'], 'fr');
    }

    private function createNegotiator(): LocalizedRouteNegotiator
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $name, array $parameters = []): string => '/' . $name . '?' . http_build_query($parameters)
        );

        return new LocalizedRouteNegotiator(self::createSiteLocales(), new LocaleSwitcher('fr', []), $router);
    }

    private function createController(?LotteryServiceInterface $lotteryService = null, bool $granted = true): LotteryController
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_ADMIN');

        $controller = new LotteryController(
            $lotteryService ?? $this->createStub(LotteryServiceInterface::class),
            $configService,
            $this->createNegotiator(),
            new CrowdfundingTranslatedLocales(self::createSiteLocales(), $this->createStub(CrowdfundingTranslator::class)),
            $this->createStub(CrowdfundingTranslator::class),
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
