<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Controller;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\CrowdfundingBundle\Service\CrowdfundingServiceInterface;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslatedLocales;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslator;
use c975L\UiBundle\Service\BlockRenderContext;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;

class CrowdfundingController extends AbstractController
{
    public function __construct(
        private readonly CrowdfundingServiceInterface $crowdfundingService,
        private readonly ConfigServiceInterface $configService,
        private readonly BlockRenderContext $blockRenderContext,
        private readonly LocalizedRouteNegotiator $negotiator,
        private readonly CrowdfundingTranslatedLocales $translatedLocales,
        private readonly CrowdfundingTranslator $crowdfundingTranslator,
    ) {
    }

    // INDEX - the same index, in another language: the writing language keeps "/crowdfunding" byte for byte, the others go through "/{_locale}/crowdfunding". The pattern holds the languages the site declares beside the one it is written in, and matches nothing while there are none (see ConfigBundle's c975LConfigBundle::declareLocalesPattern()), so a single-language site only ever answers on the second
    #[Route(
        '/{_locale}/crowdfunding',
        name: 'crowdfunding_index_localized',
        requirements: ['_locale' => '%c975l_config.locales_pattern%'],
        methods: ['GET']
    )]
    #[Route(
        '/crowdfunding',
        name: 'crowdfunding_index',
        methods: ['GET']
    )]
    public function index(Request $request): Response
    {
        $askedLanguage = $this->negotiator->redirectToAskedLanguage($request, $this->translatedLocales->forIndex(), 'crowdfunding_index');
        if (null !== $askedLanguage) {
            return $this->negotiator->vary($request, $askedLanguage);
        }

        $crowdfundings = $this->crowdfundingService->findAllSorted();

        // The language being read laid over the titles, for this render and no longer: called here rather than on postLoad, the back office having to go on showing the text a row was written in (see CrowdfundingTranslator::apply)
        $this->crowdfundingTranslator->apply($crowdfundings);

        return $this->negotiator->vary($request, $this->render(
            '@c975LCrowdfunding/crowdfunding/index.html.twig',
            [
                'crowdfundings' => $crowdfundings,
            ]
        ));
    }

    // DISPLAY - the same campaign, in another language, answering in every language the site declares whether or not the campaign is translated (see CrowdfundingTranslatedLocales::forCrowdfunding)
    #[Route(
        '/{_locale}/crowdfunding/{slug}',
        name: 'crowdfunding_display_localized',
        requirements: [
            '_locale' => '%c975l_config.locales_pattern%',
            'slug' => '^([a-zA-Z0-9\-]*)',
        ],
        methods: ['GET', 'POST']
    )]
    #[Route(
        '/crowdfunding/{slug}',
        name: 'crowdfunding_display',
        requirements: ['slug' => '^([a-zA-Z0-9\-]*)'],
        methods: ['GET', 'POST']
    )]
    public function display(
        Request $request,
        #[MapEntity(expr: 'repository.findOneBySlug(slug)')]
        Crowdfunding $crowdfunding,
    ): Response {
        // A trashed campaign is gone rather than missing, and a search engine acts on a 410 far faster than on the 404 the same url would otherwise answer - for as long as the campaign can still be restored, a Redirect taking over once it is deleted for good (see CrowdfundingCrudController::deletePermanently())
        if ($crowdfunding->isDeleted()) {
            throw new GoneHttpException();
        }

        // A campaign not opened yet has nothing to say beyond that this url leads nowhere for now - 404 and not the 410 of the recycle bin, nothing having been taken away
        if ($crowdfunding->isHidden()) {
            throw $this->createNotFoundException();
        }

        $locales = $this->translatedLocales->forCrowdfunding($crowdfunding);

        // A localised url answers for every language the site declares (see CrowdfundingTranslatedLocales); the guard stays as the one place that would refuse one, and is said before the news form is handled - a POST to a url answering 404 has no business writing a row
        if (!$this->negotiator->isTranslated($request, $locales)) {
            throw $this->createNotFoundException();
        }

        $askedLanguage = $this->negotiator->redirectToAskedLanguage($request, $locales, 'crowdfunding_display', ['slug' => $crowdfunding->getSlug()]);
        if (null !== $askedLanguage) {
            return $this->negotiator->vary($request, $askedLanguage);
        }

        // Defines form
        $form = null;
        $user = $this->getUser();
        if ($user instanceof UserInterface && ($user->getId() === $crowdfunding->getUser()?->getId() || $this->isGranted($this->configService->get('site-role-editor')))) {
            $news = new CrowdfundingNews();
            $form = $this->crowdfundingService->createForm('news', $news);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $this->crowdfundingService->addNews($crowdfunding, $news);

                // Back to the url the editor was reading, bare or localised: naming crowdfunding_display would send one writing from "/en/crowdfunding/x" back into the writing language the day that url answers
                return $this->redirectToRoute(
                    (string) $request->attributes->get('_route'),
                    (array) $request->attributes->get('_route_params') + ['_fragment' => 'news']
                );
            }
        }

        // The campaign, its tiers, its follow-ups and the prizes of its draws in the language being read (see CrowdfundingTranslator::apply) - the prizes are listed on this page too, not only on the draw's own
        $this->crowdfundingTranslator->apply([$crowdfunding]);
        $this->crowdfundingTranslator->apply($crowdfunding->getCounterparts());
        $this->crowdfundingTranslator->apply($crowdfunding->getNews());
        foreach ($crowdfunding->getLotteries() as $lottery) {
            $this->crowdfundingTranslator->apply($lottery->getPrizes());
        }

        return $this->negotiator->vary($request, $this->render(
            '@c975LCrowdfunding/crowdfunding/display.html.twig',
            [
                'crowdfunding' => $crowdfunding,
                'form' => $form?->createView(),
            ]
        ));
    }

    // PREVIEW
    #[Route(
        '/crowdfunding/{slug}/preview',
        name: 'crowdfunding_preview',
        requirements: ['slug' => '^([a-zA-Z0-9\-]*)'],
        methods: ['GET'],
        priority: 1
    )]
    public function preview(
        #[MapEntity(expr: 'repository.findOneBySlug(slug)')]
        Crowdfunding $crowdfunding,
    ): Response {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        // A preview shows what was just saved, and its html is not the public one - said before anything is rendered, the page being composed of cacheable blocks
        $this->blockRenderContext->disableCache();

        if ($crowdfunding->isDeleted()) {
            throw $this->createNotFoundException();
        }

        return $this->render(
            '@c975LCrowdfunding/crowdfunding/display.html.twig',
            [
                'crowdfunding' => $crowdfunding,
                'form' => null,
                'isPreview' => true,
            ]
        )->setPrivate();
    }
}
