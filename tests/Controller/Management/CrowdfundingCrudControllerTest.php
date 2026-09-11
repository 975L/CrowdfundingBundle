<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\Management\ContentLocaleScreen;
use c975L\ConfigBundle\Repository\RedirectRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\CrowdfundingBundle\Controller\Management\CrowdfundingCrudController;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributorCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Entity\LotteryTicket;
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslator;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Guards what the back-office delete actually does to a campaign, and what a campaign removed for good leaves behind at its old url
class CrowdfundingCrudControllerTest extends TestCase
{
    // Deleting only moves the campaign to the recycle bin: its rows, its medias and its files all stay where they are until the second, deliberate deletion
    public function testDeletingACampaignOnlyMovesItToTheRecycleBin(): void
    {
        $crowdfunding = new Crowdfunding()->setHidden(false);
        $persisted = [];
        $removed = [];
        $flushed = 0;

        $this->createController()->deleteEntity($this->recordingEntityManager($persisted, $removed, $flushed), $crowdfunding);

        $this->assertTrue($crowdfunding->isDeleted());
        $this->assertTrue($crowdfunding->isHidden());
        $this->assertSame([], $removed);
        $this->assertSame(1, $flushed);
    }

    // Anything else on that screen is deleted the way EasyAdmin deletes it, the recycle bin being the campaign's own
    public function testAnythingButACampaignIsDeletedAsUsual(): void
    {
        $persisted = [];
        $removed = [];
        $flushed = 0;
        $other = new \stdClass();

        $this->createController()->deleteEntity($this->recordingEntityManager($persisted, $removed, $flushed), $other);

        $this->assertSame([$other], $removed);
    }

    // The 410 the recycle bin served would fall back to a plain 404 once the row is gone - a "gone" Redirect keeps answering it for good
    public function testDeletingACampaignForGoodLeavesA410AtItsUrl(): void
    {
        $persisted = [];
        $removed = [];
        $flushed = 0;

        $this->invokeGoneRedirect($this->createController(), $this->recordingEntityManager($persisted, $removed, $flushed), 'sauver-les-chats');

        $this->assertCount(1, $persisted);
        $this->assertSame('/crowdfunding/sauver-les-chats', $persisted[0]->getFromPath());
        $this->assertTrue($persisted[0]->isGone());
        $this->assertNull($persisted[0]->getToUrl());
    }

    // A redirect an admin set up towards that campaign would otherwise dangle - what it led to is just as removed
    public function testTheRedirectsPointingAtTheCampaignBecomeGoneRows(): void
    {
        $persisted = [];
        $removed = [];
        $flushed = 0;
        $dangling = new Redirect()->setFromPath('/chats')->setToUrl('/crowdfunding/sauver-les-chats');

        $controller = $this->createController($this->redirectRepository([], ['/crowdfunding/sauver-les-chats' => [$dangling]]));
        $this->invokeGoneRedirect($controller, $this->recordingEntityManager($persisted, $removed, $flushed), 'sauver-les-chats');

        $this->assertTrue($dangling->isGone());
        $this->assertNull($dangling->getToUrl());
    }

    // A path an admin already covered says more than a dead end, so it is left alone
    public function testAPathAlreadyRedirectedKeepsItsOwnTarget(): void
    {
        $persisted = [];
        $removed = [];
        $flushed = 0;
        $existing = new Redirect()->setFromPath('/crowdfunding/sauver-les-chats')->setToUrl('/crowdfunding/sauver-les-chatons');

        $controller = $this->createController($this->redirectRepository(['/crowdfunding/sauver-les-chats' => $existing]));
        $this->invokeGoneRedirect($controller, $this->recordingEntityManager($persisted, $removed, $flushed), 'sauver-les-chats');

        $this->assertSame([], $persisted);
        $this->assertFalse($existing->isGone());
        $this->assertSame('/crowdfunding/sauver-les-chatons', $existing->getToUrl());
    }

    // The one flag the whole screen reads, from the fields to the query: the recycle bin is a view of the index rather than a screen of its own
    public function testTheRecycleBinIsReadOffTheQueryString(): void
    {
        $this->assertTrue($this->isTrash($this->createController(request: new Request(['trash' => 1]))));
        $this->assertFalse($this->isTrash($this->createController(request: new Request())));
    }

    // Both trash actions are reached by a GET carrying its token: without a valid one, nothing is acted on
    public function testATrashActionIsRefusedWithoutAValidCsrfToken(): void
    {
        $crowdfunding = new Crowdfunding()->setIsDeleted(true);
        $controller = $this->createController(
            request: new Request(['entityId' => 12, 'token' => 'forged']),
            crowdfundingRepository: $this->crowdfundingRepository([12 => $crowdfunding]),
            validToken: 'expected',
        );

        $this->assertNull($this->trashedCampaign($controller));
    }

    // The two actions belong to the recycle bin: a campaign that is not in it is not theirs to act on, whatever the url says
    public function testATrashActionRefusesACampaignThatIsNotInTheBin(): void
    {
        $controller = $this->createController(
            request: new Request(['entityId' => 12, 'token' => 'expected']),
            crowdfundingRepository: $this->crowdfundingRepository([12 => new Crowdfunding()]),
            validToken: 'expected',
        );

        $this->assertNull($this->trashedCampaign($controller));
    }

    // The row the action was clicked on, once the role, the token and the recycle bin have all been read
    public function testATrashActionActsOnACampaignSittingInTheBin(): void
    {
        $crowdfunding = new Crowdfunding()->setIsDeleted(true);
        $controller = $this->createController(
            request: new Request(['entityId' => 12, 'token' => 'expected']),
            crowdfundingRepository: $this->crowdfundingRepository([12 => $crowdfunding]),
            validToken: 'expected',
        );

        $this->assertSame($crowdfunding, $this->trashedCampaign($controller));
    }

    private function isTrash(CrowdfundingCrudController $controller): bool
    {
        return new \ReflectionMethod(CrowdfundingCrudController::class, 'isTrash')->invoke($controller);
    }

    private function trashedCampaign(CrowdfundingCrudController $controller): ?Crowdfunding
    {
        return new \ReflectionMethod(CrowdfundingCrudController::class, 'trashedCampaign')
            ->invokeArgs($controller, [
                $this->request,
                $this->crowdfundingRepositoryOfController,
                CrowdfundingCrudController::RESTORE_CSRF_TOKEN,
            ]);
    }

    private function crowdfundingRepository(array $byId): CrowdfundingRepository
    {
        $repository = $this->createStub(CrowdfundingRepository::class);
        $repository->method('find')->willReturnCallback(static fn (mixed $id): ?Crowdfunding => $byId[$id] ?? null);

        return $repository;
    }

    private function invokeGoneRedirect(CrowdfundingCrudController $controller, EntityManagerInterface $entityManager, string $slug): void
    {
        new \ReflectionMethod(CrowdfundingCrudController::class, 'writeGoneRedirect')
            ->invokeArgs($controller, [$entityManager, new Crowdfunding()->setSlug($slug)]);
    }

    // A counterpart someone has paid for is put back rather than deleted: $counterparts carries "orphanRemoval", so its removal would reach the database as a DELETE that the contributors' own rows refuse
    public function testASubscribedCounterpartRemovedFromTheFormIsPutBack(): void
    {
        $subscribed = new CrowdfundingCounterpart()->setTitle('Le livre dédicacé');
        $this->subscribe($subscribed);
        $free = new CrowdfundingCounterpart()->setTitle('Le marque-page');

        $crowdfunding = $this->campaignWithCounterpartsRemoved($subscribed, $free);
        $this->updateEntity($crowdfunding);

        $this->assertSame(['Le livre dédicacé'], array_values(array_map(
            static fn (CrowdfundingCounterpart $counterpart): ?string => $counterpart->getTitle(),
            $crowdfunding->getCounterparts()->toArray()
        )));
        // Back in the collection is not enough: the removal nulled the owning side, and the save would write that null back rather than the campaign
        $this->assertSame($crowdfunding, $subscribed->getCrowdfunding());
    }

    // The one nobody subscribed to goes, which is what the editor asked for - the guard puts back what would fail, not everything
    public function testACounterpartNobodySubscribedToIsRemovedAsAsked(): void
    {
        $free = new CrowdfundingCounterpart()->setTitle('Le marque-page');

        $crowdfunding = $this->campaignWithCounterpartsRemoved($free);
        $this->updateEntity($crowdfunding);

        $this->assertCount(0, $crowdfunding->getCounterparts());
    }

    // A campaign built out of the ORM carries a plain ArrayCollection, which has no removal to read - the save goes through untouched rather than on an error
    public function testACampaignOutsideTheOrmIsSavedUntouched(): void
    {
        $crowdfunding = new Crowdfunding();

        $this->updateEntity($crowdfunding);

        $this->assertCount(0, $crowdfunding->getCounterparts());
    }

    // The url names the campaign the code leads to: with an entityId pointing at nothing - a campaign deleted since the screen was drawn - the slug would be read off a null
    public function testTheQrCodeOfACampaignThatIsNotThereAnswersNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController()->qrcode($this->adminContext(null));
    }

    // The code itself is drawn off the site's own address, not off the request's host
    public function testTheQrCodeIsDrawnForACampaignThatIsThere(): void
    {
        $response = $this->createController()->qrcode($this->adminContext(new Crowdfunding()->setSlug('sauver-les-chats')));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringStartsWith('image/', (string) $response->headers->get('Content-Type'));
    }

    // The context EasyAdmin hands the action, holding the campaign the url named - or nothing, which is the case above. Built through the framework's own testing factory, AdminContext being final
    private function adminContext(?Crowdfunding $crowdfunding): AdminContext
    {
        $entityDto = new EntityDto(Crowdfunding::class, new ClassMetadata(Crowdfunding::class), null, $crowdfunding);

        return AdminContext::forTesting(crudContext: CrudContext::forTesting(entityDto: $entityDto));
    }

    // A draw refuses nothing on its own - it cascades its removal to its tickets - so the guard is what keeps a sold ticket and the winner drawn on it
    public function testADrawnLotteryRemovedFromTheFormIsPutBack(): void
    {
        $drawn = new Lottery()->setIdentifier('0123456789abc');
        $drawn->addTicket(new LotteryTicket());

        $crowdfunding = $this->campaignWithLotteriesRemoved($drawn);
        $this->updateEntity($crowdfunding);

        $this->assertCount(1, $crowdfunding->getLotteries());
        $this->assertSame($crowdfunding, $drawn->getCrowdfunding());
    }

    // A draw nobody bought a ticket on goes, which is what the editor asked for
    public function testALotteryWithoutATicketIsRemovedAsAsked(): void
    {
        $crowdfunding = $this->campaignWithLotteriesRemoved(new Lottery()->setIdentifier('0123456789abc'));
        $this->updateEntity($crowdfunding);

        $this->assertCount(0, $crowdfunding->getLotteries());
    }

    // The campaign as the form hands it over, its draws loaded from the database then taken off the collection
    private function campaignWithLotteriesRemoved(Lottery ...$removed): Crowdfunding
    {
        $crowdfunding = new Crowdfunding();

        new \ReflectionProperty(Crowdfunding::class, 'lotteries')->setValue($crowdfunding, $this->emptiedSnapshot($removed));

        return $crowdfunding;
    }

    // The campaign as the form hands it over: its counterparts loaded from the database, then taken off the collection
    private function campaignWithCounterpartsRemoved(CrowdfundingCounterpart ...$removed): Crowdfunding
    {
        $crowdfunding = new Crowdfunding();

        new \ReflectionProperty(Crowdfunding::class, 'counterparts')->setValue($crowdfunding, $this->emptiedSnapshot($removed));

        return $crowdfunding;
    }

    // A collection the ORM loaded and the form then emptied: the snapshot is what getDeleteDiff() reads the removals from
    private function emptiedSnapshot(array $entries): PersistentCollection
    {
        $collection = new PersistentCollection(null, null, new ArrayCollection($entries));
        $collection->setInitialized(true);
        $collection->takeSnapshot();

        foreach ($entries as $entry) {
            $collection->removeElement($entry);
        }

        return $collection;
    }

    // The inverse side carries no adder of its own, the rows being written from the contributor
    private function subscribe(CrowdfundingCounterpart $counterpart): void
    {
        $relation = new CrowdfundingContributorCounterpart()->setContributor(new CrowdfundingContributor())->setCounterpart($counterpart);

        new \ReflectionProperty(CrowdfundingCounterpart::class, 'contributorCounterparts')->setValue($counterpart, new ArrayCollection([$relation]));
    }

    private function updateEntity(Crowdfunding $crowdfunding): void
    {
        $this->createController()->updateEntity($this->createStub(EntityManagerInterface::class), $crowdfunding);
    }

    // The language screen opens straight from the list, as a product's and a page's do - named "translate", the class EasyAdmin renders and the guided project points at, behind the editor's own role
    public function testTheListOpensTheLanguageScreen(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): string => 'site-role-admin' === $key ? 'ROLE_ADMIN' : 'ROLE_EDITOR');

        // A real one: the action it builds is EasyAdmin's final Action, which no double can hand back
        $contentLocaleScreen = new ContentLocaleScreen(new RequestStack(), $this->createStub(AdminUrlGeneratorInterface::class), new SiteLocales(['fr', 'en'], 'fr'));

        // A real EasyAdmin runtime pre-populates the default actions, which update() and reorder() assume
        $actions = $this->createController(configService: $configService, contentLocaleScreen: $contentLocaleScreen)->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        )->getAsDto(Crud::PAGE_INDEX);

        $this->assertNotNull($actions->getAction(Crud::PAGE_INDEX, 'translate'));
        $this->assertSame('ROLE_EDITOR', $actions->getActionPermissions()['translate']);
    }

    // A language screen offers what a language may change and nothing else: the campaign's three texts, holding what the translator prompts, then the collections carrying texts of their own - never a goal, a date or a slug
    public function testTheLanguageScreenOffersTheTextsAlone(): void
    {
        $crowdfundingTranslator = $this->createStub(CrowdfundingTranslator::class);
        $crowdfundingTranslator->method('promptValues')->willReturn(['title' => '[Le toit]', 'description' => null, 'authorPresentation' => null]);

        $fields = new \ReflectionMethod(CrowdfundingCrudController::class, 'translationFields')
            ->invoke($this->createController(crowdfundingTranslator: $crowdfundingTranslator), new Crowdfunding(), 'en');

        $byProperty = [];
        foreach ($fields as $field) {
            $byProperty[$field->getAsDto()->getProperty()] = $field->getAsDto();
        }

        foreach (['title', 'description', 'authorPresentation', 'counterparts', 'news', 'lotteries'] as $property) {
            $this->assertArrayHasKey($property, $byProperty);
        }
        $this->assertArrayNotHasKey('amountGoal', $byProperty);
        $this->assertArrayNotHasKey('slug', $byProperty);
        $this->assertSame('[Le toit]', $byProperty['title']->getFormTypeOption('data'));
        $this->assertFalse($byProperty['title']->getFormTypeOption('mapped'));
    }

    private ?Request $request = null;

    private ?CrowdfundingRepository $crowdfundingRepositoryOfController = null;

    private function createController(
        ?RedirectRepository $redirectRepository = null,
        ?Request $request = null,
        ?CrowdfundingRepository $crowdfundingRepository = null,
        string $validToken = 'expected',
        ?ConfigServiceInterface $configService = null,
        ?ContentLocaleScreen $contentLocaleScreen = null,
        ?CrowdfundingTranslator $crowdfundingTranslator = null,
    ): CrowdfundingCrudController {
        $this->request = $request ?? new Request();
        // A flash goes on the session, which a bare request does not carry
        $this->request->setSession(new Session(new MockArraySessionStorage()));
        $this->crowdfundingRepositoryOfController = $crowdfundingRepository ?? $this->createStub(CrowdfundingRepository::class);

        $requestStack = new RequestStack([$this->request]);

        // The collaborators a test hands over, laid over the doubles every other test is content with - read off an array rather than a "??" each, which lizard counts as two branches apiece
        $collaborators = [
            'redirectRepository' => $this->createStub(RedirectRepository::class),
            'configService' => $this->createStub(ConfigServiceInterface::class),
            'contentLocaleScreen' => $this->createStub(ContentLocaleScreen::class),
            'crowdfundingTranslator' => $this->createStub(CrowdfundingTranslator::class),
            ...array_filter(compact('redirectRepository', 'configService', 'contentLocaleScreen', 'crowdfundingTranslator')),
        ];

        $controller = new CrowdfundingCrudController(
            $this->createStub(BlockMoveRowAttrBuilder::class),
            $collaborators['configService'],
            $this->createStub(TranslatorInterface::class),
            $this->createStub(AdminUrlGeneratorInterface::class),
            $this->createStub(CsrfTokenManagerInterface::class),
            $requestStack,
            $collaborators['redirectRepository'],
            $collaborators['contentLocaleScreen'],
            $collaborators['crowdfundingTranslator'],
        );
        $controller->setContainer($this->container($validToken, $requestStack));

        return $controller;
    }

    // The services AbstractController reaches for: the role is granted, the token is the one the action was linked with, and the stack is what addFlash() puts its message on
    private function container(string $validToken, RequestStack $requestStack): ContainerInterface
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturnCallback(static fn (CsrfToken $token): bool => $validToken === $token->getValue());

        $services = [
            'security.authorization_checker' => $authorizationChecker,
            'security.csrf.token_manager' => $csrfTokenManager,
            'request_stack' => $requestStack,
        ];

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => isset($services[$id]));
        $container->method('get')->willReturnCallback(static fn (string $id): ?object => $services[$id] ?? null);

        return $container;
    }

    private function redirectRepository(array $byFromPath = [], array $byToUrl = []): RedirectRepository
    {
        $repository = $this->createStub(RedirectRepository::class);
        $repository->method('findOneByFromPath')->willReturnCallback(static fn (string $path): ?Redirect => $byFromPath[$path] ?? null);
        $repository->method('findByToUrl')->willReturnCallback(static fn (string $url): array => $byToUrl[$url] ?? []);

        return $repository;
    }

    // The entity manager as the controller uses it, keeping what each call was handed
    private function recordingEntityManager(array &$persisted, array &$removed, int &$flushed): EntityManagerInterface
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->method('remove')->willReturnCallback(static function (object $entity) use (&$removed): void {
            $removed[] = $entity;
        });
        $entityManager->method('flush')->willReturnCallback(static function () use (&$flushed): void {
            ++$flushed;
        });

        return $entityManager;
    }
}
