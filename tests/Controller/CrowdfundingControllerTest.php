<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Controller;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Controller\CrowdfundingController;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Service\CrowdfundingServiceInterface;
use c975L\UiBundle\Service\BlockRenderContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

// Only "twig" and "security.token_storage" are ever fetched, so a bare Container is enough and no kernel is booted
class CrowdfundingControllerTest extends TestCase
{
    public function testIndexRendersEveryCampaignInTheOrderTheAdminArranged(): void
    {
        $crowdfundings = [new Crowdfunding(), new Crowdfunding()];

        $service = $this->createMock(CrowdfundingServiceInterface::class);
        $service->expects($this->once())->method('findAllSorted')->willReturn($crowdfundings);

        $response = $this->createController($service)->index();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('@c975LCrowdfunding/crowdfunding/index.html.twig', $response->getContent());
    }

    // The two public pages carried an hour of max-age, which froze a contribution, a threshold reached and a news published for that whole hour. What they hold is cached by fragment by the blocks, which invalidates itself when the content changes
    public function testTheIndexIsNotFrozenForAnHour(): void
    {
        $this->assertNull($this->createController()->index()->getMaxAge());
    }

    public function testTheCampaignPageIsNotFrozenForAnHour(): void
    {
        $response = $this->createController()->display(new Request(), $this->createOpenCampaign());

        $this->assertNull($response->getMaxAge());
    }

    // A visitor is offered no form: the follow-up is written by the campaign's author, or by an admin
    public function testTheCampaignPageOffersNoNewsFormToAVisitor(): void
    {
        $response = $this->createController()->display(new Request(), $this->createOpenCampaign());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('@c975LCrowdfunding/crowdfunding/display.html.twig', $response->getContent());
    }

    // A campaign taken over from a legacy row or made in CLI carries no user, and reading its owner without a nullsafe was fatal for every logged-in visitor while anonymous ones were served
    public function testTheCampaignPageIsServedToALoggedInVisitorWhenTheCampaignHasNoOwner(): void
    {
        $response = $this->createController(user: $this->createUserWithAnId())->display(new Request(), $this->createOpenCampaign());

        $this->assertSame(200, $response->getStatusCode());
    }

    // A trashed campaign is gone rather than missing: a search engine acts on a 410 far faster than on the 404 the same url would otherwise answer
    public function testATrashedCampaignAnswersGone(): void
    {
        $this->expectException(GoneHttpException::class);

        $this->createController()->display(new Request(), new Crowdfunding()->setIsDeleted(true));
    }

    // A campaign not opened yet says nothing more than that this url leads nowhere for now - 404 and not the 410 of the recycle bin, nothing having been taken away
    public function testAHiddenCampaignAnswersNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController()->display(new Request(), new Crowdfunding());
    }

    // The preview reads that very campaign, and is the one screen a hidden campaign is readable on
    public function testThePreviewRendersAHiddenCampaignAsAPreview(): void
    {
        $response = $this->createController(granted: true)->preview(new Crowdfunding());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('@c975LCrowdfunding/crowdfunding/display.html.twig', $response->getContent());
    }

    // A preview is one editor's own read of what was just saved: never stored by a shared cache, and never written into the block cache either
    public function testThePreviewIsPrivateAndWritesNothingIntoTheBlockCache(): void
    {
        $blockRenderContext = new BlockRenderContext();

        $response = $this->createController(blockRenderContext: $blockRenderContext, granted: true)->preview(new Crowdfunding());

        $this->assertTrue($response->headers->getCacheControlDirective('private'));
        $this->assertTrue($blockRenderContext->isCacheDisabled());
    }

    // A campaign on its way out is not previewed either: the recycle bin restores it or removes it, and nothing in between
    public function testThePreviewRefusesATrashedCampaign(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(granted: true)->preview(new Crowdfunding()->setIsDeleted(true));
    }

    // The preview is an editor's screen: a visitor reaching that url is refused rather than served the campaign the public route hides
    public function testThePreviewIsRefusedToAVisitor(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->createController()->preview(new Crowdfunding());
    }

    // Both are GET routes a visitor reaches by their own address, and the campaign is resolved by its slug rather than by its id
    public function testTheTwoPublicRoutesAreDeclaredWithTheirNames(): void
    {
        $routes = [];
        foreach (new \ReflectionClass(CrowdfundingController::class)->getMethods() as $method) {
            foreach ($method->getAttributes(Route::class) as $attribute) {
                $arguments = $attribute->getArguments();
                $routes[$arguments['name']] = $arguments;
            }
        }

        $this->assertSame('/crowdfunding', $routes['crowdfunding_index'][0]);
        $this->assertSame(['GET'], $routes['crowdfunding_index']['methods']);
        $this->assertSame('/crowdfunding/{slug}', $routes['crowdfunding_display'][0]);
        $this->assertSame(['GET', 'POST'], $routes['crowdfunding_display']['methods']);

        // Declared before the campaign's own route, which "{slug}" would otherwise swallow whole
        $this->assertSame('/crowdfunding/{slug}/preview', $routes['crowdfunding_preview'][0]);
        $this->assertSame(['GET'], $routes['crowdfunding_preview']['methods']);
        $this->assertSame(1, $routes['crowdfunding_preview']['priority']);
    }

    // The user the page reads is ConfigBundle's contract, resolved onto the application's own entity: the double answers an id as that entity does
    private function createUserWithAnId(): UserInterface
    {
        return new class implements UserInterface {
            public function getId(): int | string | null
            {
                return 7;
            }

            /** @return list<string> */
            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'contributor';
            }
        };
    }

    // A campaign a visitor may read: the property starts hidden, so a campaign is opened before its page is served
    private function createOpenCampaign(): Crowdfunding
    {
        return new Crowdfunding()->setHidden(false);
    }

    private function createController(?CrowdfundingServiceInterface $service = null, ?UserInterface $user = null, ?BlockRenderContext $blockRenderContext = null, bool $granted = false): CrowdfundingController
    {
        $controller = new CrowdfundingController(
            $service ?? $this->createStub(CrowdfundingServiceInterface::class),
            $this->createStub(ConfigServiceInterface::class),
            $blockRenderContext ?? new BlockRenderContext(),
        );

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static fn (string $view): string => $view);

        // The campaign page asks who is looking before deciding whether to offer the follow-up form
        $token = null;
        if (null !== $user) {
            $token = $this->createStub(TokenInterface::class);
            $token->method('getUser')->willReturn($user);
        }

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($granted);

        $container = new Container();
        $container->set('twig', $twig);
        $container->set('security.token_storage', $tokenStorage);
        $container->set('security.authorization_checker', $authorizationChecker);
        $controller->setContainer($container);

        return $controller;
    }
}
