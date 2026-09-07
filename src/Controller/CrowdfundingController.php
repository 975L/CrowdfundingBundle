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
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\CrowdfundingBundle\Service\CrowdfundingServiceInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CrowdfundingController extends AbstractController
{
    public function __construct(
        private readonly CrowdfundingServiceInterface $crowdfundingService,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // INDEX
    #[Route(
        '/crowdfunding',
        name: 'crowdfunding_index',
        methods: ['GET']
    )]
    public function index(): Response
    {
        return $this->render(
            '@c975LCrowdfunding/crowdfunding/index.html.twig',
            [
                'crowdfundings' => $this->crowdfundingService->findAllSorted(),
            ]
        );
    }

    // DISPLAY
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
        // Defines form
        $form = null;
        $user = $this->getUser();
        if ($user instanceof UserInterface && ($user->getId() === $crowdfunding->getUser()->getId() || $this->isGranted($this->configService->get('site-role-admin')))) {
            $news = new CrowdfundingNews();
            $form = $this->crowdfundingService->createForm('news', $news);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $this->crowdfundingService->addNews($crowdfunding, $news);

                return $this->redirectToRoute('crowdfunding_display', [
                    'slug' => $crowdfunding->getSlug(),
                    '_fragment' => 'news',
                ]);
            }
        }

        return $this->render(
            '@c975LCrowdfunding/crowdfunding/display.html.twig',
            [
                'crowdfunding' => $crowdfunding,
                'form' => $form?->createView(),
            ]
        );
    }
}
