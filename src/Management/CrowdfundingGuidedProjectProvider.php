<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Management;

use c975L\ConfigBundle\Management\GuidedProjectProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Controller\Management\CrowdfundingCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// This bundle's guided projects, running the 9000 block GuidedProjectProviderInterface reserves them - the same docblock stating every other bundle's, so a range is read there rather than recopied here. They follow the order a campaign is actually built: the campaign itself, then what illustrates it, then what is offered in return, then the rest of its page, and last the lottery and the video of its draw. Every one of them opens on the same screen, this bundle holding a single CRUD: a campaign carries its media, its counterparts, its lottery and its blocks on its own edit form, so what tells the parcours apart is the fieldset they walk to, not the screen they open
class CrowdfundingGuidedProjectProvider implements GuidedProjectProviderInterface
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getGuidedProjects(): array
    {
        return [
            $this->campaignProject(),
            $this->mediaProject(),
            $this->counterpartProject(),
            $this->blocksProject(),
            $this->lotteryProject(),
            $this->drawVideoProject(),
        ];
    }

    // What everything else hangs from: a campaign is an amount to reach between two dates, and nothing below exists without it
    private function campaignProject(): array
    {
        return [
            'slug' => 'crowdfunding-campaign',
            'label' => 'label.guided_project_crowdfunding_campaign',
            'description' => 'description.guided_project_crowdfunding_campaign',
            'translation_domain' => 'crowdfunding',
            'order' => 9010,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_crowdfunding_campaign_open',
                    'description' => 'description.guided_step_crowdfunding_campaign_open',
                    'narration' => 'narration.guided_step_crowdfunding_campaign_open',
                    'url' => $this->indexUrl(),
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_campaign_new',
                    'description' => 'description.guided_step_crowdfunding_campaign_new',
                    'narration' => 'narration.guided_step_crowdfunding_campaign_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_campaign_title',
                    'description' => 'description.guided_step_crowdfunding_campaign_title',
                    'narration' => 'narration.guided_step_crowdfunding_campaign_title',
                    'highlight' => '#Crowdfunding_title',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_campaign_slug',
                    'description' => 'description.guided_step_crowdfunding_campaign_slug',
                    'narration' => 'narration.guided_step_crowdfunding_campaign_slug',
                    'highlight' => '#Crowdfunding_slug',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_campaign_goal',
                    'description' => 'description.guided_step_crowdfunding_campaign_goal',
                    'narration' => 'narration.guided_step_crowdfunding_campaign_goal',
                    'highlight' => '#Crowdfunding_amountGoal',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_campaign_dates',
                    'description' => 'description.guided_step_crowdfunding_campaign_dates',
                    'narration' => 'narration.guided_step_crowdfunding_campaign_dates',
                    'highlight' => '#Crowdfunding_endDate',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_campaign_description',
                    'description' => 'description.guided_step_crowdfunding_campaign_description',
                    'narration' => 'narration.guided_step_crowdfunding_campaign_description',
                    // The <trix-editor> the field's own textarea is replaced by, not its id: EasyAdmin renders that textarea hidden (see its crud/form_theme.html.twig), so #Crowdfunding_description would point at something nobody sees
                    'highlight' => 'trix-editor[input="Crowdfunding_description"]',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_campaign_author',
                    'description' => 'description.guided_step_crowdfunding_campaign_author',
                    'narration' => 'narration.guided_step_crowdfunding_campaign_author',
                    'highlight' => '#Crowdfunding_authorName',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_campaign_save',
                    'narration' => 'narration.guided_step_crowdfunding_campaign_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_campaign_done',
                    'description' => 'description.guided_step_crowdfunding_campaign_done',
                    'narration' => 'narration.guided_step_crowdfunding_campaign_done',
                ],
            ],
        ];
    }

    // A campaign nobody can picture is a campaign nobody funds: the photographs and the video come before anything is asked for
    private function mediaProject(): array
    {
        return [
            'slug' => 'crowdfunding-media',
            'label' => 'label.guided_project_crowdfunding_media',
            'description' => 'description.guided_project_crowdfunding_media',
            'translation_domain' => 'crowdfunding',
            'order' => 9020,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_crowdfunding_media_open',
                    'description' => 'description.guided_step_crowdfunding_media_open',
                    'narration' => 'narration.guided_step_crowdfunding_media_open',
                    'url' => $this->indexUrl(),
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_media_edit',
                    'description' => 'description.guided_step_crowdfunding_media_edit',
                    'narration' => 'narration.guided_step_crowdfunding_media_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_media_photos',
                    'description' => 'description.guided_step_crowdfunding_media_photos',
                    'narration' => 'narration.guided_step_crowdfunding_media_photos',
                    'highlight' => '[data-crowdfunding-medias]',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_media_videos',
                    'description' => 'description.guided_step_crowdfunding_media_videos',
                    'narration' => 'narration.guided_step_crowdfunding_media_videos',
                    'highlight' => '[data-crowdfunding-videos]',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_media_save',
                    'narration' => 'narration.guided_step_crowdfunding_media_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_media_done',
                    'description' => 'description.guided_step_crowdfunding_media_done',
                    'narration' => 'narration.guided_step_crowdfunding_media_done',
                ],
            ],
        ];
    }

    // The one thing this bundle sells, and the one place a stock is stated: a counterpart is added to the basket like any other item, PaymentBundle taking the money from there
    private function counterpartProject(): array
    {
        return [
            'slug' => 'crowdfunding-counterpart',
            'label' => 'label.guided_project_crowdfunding_counterpart',
            'description' => 'description.guided_project_crowdfunding_counterpart',
            'translation_domain' => 'crowdfunding',
            'order' => 9030,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_crowdfunding_counterpart_open',
                    'description' => 'description.guided_step_crowdfunding_counterpart_open',
                    'narration' => 'narration.guided_step_crowdfunding_counterpart_open',
                    'url' => $this->indexUrl(),
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_counterpart_edit',
                    'description' => 'description.guided_step_crowdfunding_counterpart_edit',
                    'narration' => 'narration.guided_step_crowdfunding_counterpart_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_counterpart_add',
                    'description' => 'description.guided_step_crowdfunding_counterpart_add',
                    'narration' => 'narration.guided_step_crowdfunding_counterpart_add',
                    'highlight' => '[data-crowdfunding-counterparts]',
                ],
                // Same fold as the lottery parcours: the entry is opened before any step points at a field nested in it
                [
                    'label' => 'label.guided_step_crowdfunding_counterpart_expand',
                    'description' => 'description.guided_step_crowdfunding_counterpart_expand',
                    'narration' => 'narration.guided_step_crowdfunding_counterpart_expand',
                    'highlight' => '[data-crowdfunding-counterparts] .accordion-button',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_counterpart_quantity',
                    'description' => 'description.guided_step_crowdfunding_counterpart_quantity',
                    'narration' => 'narration.guided_step_crowdfunding_counterpart_quantity',
                    'highlight' => '[data-counterpart-quantity]',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_counterpart_save',
                    'narration' => 'narration.guided_step_crowdfunding_counterpart_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_counterpart_done',
                    'description' => 'description.guided_step_crowdfunding_counterpart_done',
                    'narration' => 'narration.guided_step_crowdfunding_counterpart_done',
                ],
            ],
        ];
    }

    // Everything the form's own fields cannot say is said in blocks, the same editor the pages of the site are composed with
    private function blocksProject(): array
    {
        return [
            'slug' => 'crowdfunding-blocks',
            'label' => 'label.guided_project_crowdfunding_blocks',
            'description' => 'description.guided_project_crowdfunding_blocks',
            'translation_domain' => 'crowdfunding',
            'order' => 9040,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_crowdfunding_blocks_open',
                    'description' => 'description.guided_step_crowdfunding_blocks_open',
                    'narration' => 'narration.guided_step_crowdfunding_blocks_open',
                    'url' => $this->indexUrl(),
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_blocks_edit',
                    'description' => 'description.guided_step_crowdfunding_blocks_edit',
                    'narration' => 'narration.guided_step_crowdfunding_blocks_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_blocks_add',
                    'description' => 'description.guided_step_crowdfunding_blocks_add',
                    'narration' => 'narration.guided_step_crowdfunding_blocks_add',
                    // The marker UiBundle's own row_attr builder lays on the field, the blocks collection printing no id of its own
                    'highlight' => '[data-ui-sort-group="block"]',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_blocks_save',
                    'narration' => 'narration.guided_step_crowdfunding_blocks_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_blocks_done',
                    'description' => 'description.guided_step_crowdfunding_blocks_done',
                    'narration' => 'narration.guided_step_crowdfunding_blocks_done',
                ],
            ],
        ];
    }

    // A lottery is written here and drawn nowhere near here: the draw itself happens on the public page, which is why the parcours stops at the draw date rather than pretending to walk it
    private function lotteryProject(): array
    {
        return [
            'slug' => 'crowdfunding-lottery',
            'label' => 'label.guided_project_crowdfunding_lottery',
            'description' => 'description.guided_project_crowdfunding_lottery',
            'translation_domain' => 'crowdfunding',
            'order' => 9050,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_crowdfunding_lottery_open',
                    'description' => 'description.guided_step_crowdfunding_lottery_open',
                    'narration' => 'narration.guided_step_crowdfunding_lottery_open',
                    'url' => $this->indexUrl(),
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_lottery_edit',
                    'description' => 'description.guided_step_crowdfunding_lottery_edit',
                    'narration' => 'narration.guided_step_crowdfunding_lottery_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_lottery_add',
                    'description' => 'description.guided_step_crowdfunding_lottery_add',
                    'narration' => 'narration.guided_step_crowdfunding_lottery_add',
                    'highlight' => '[data-crowdfunding-lotteries]',
                ],
                // EasyAdmin renders every entry of a collection as a Bootstrap accordion item, folded onto its title (see its crud/form_theme.html.twig): the fields nested in it are in the page but invisible, so the parcours has the user open the entry before pointing inside it
                [
                    'label' => 'label.guided_step_crowdfunding_lottery_expand',
                    'description' => 'description.guided_step_crowdfunding_lottery_expand',
                    'narration' => 'narration.guided_step_crowdfunding_lottery_expand',
                    'highlight' => '[data-crowdfunding-lotteries] .accordion-button',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_lottery_prizes',
                    'description' => 'description.guided_step_crowdfunding_lottery_prizes',
                    'narration' => 'narration.guided_step_crowdfunding_lottery_prizes',
                    'highlight' => '[data-lottery-prizes]',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_lottery_save',
                    'narration' => 'narration.guided_step_crowdfunding_lottery_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_lottery_done',
                    'description' => 'description.guided_step_crowdfunding_lottery_done',
                    'narration' => 'narration.guided_step_crowdfunding_lottery_done',
                ],
            ],
        ];
    }

    // What is left once the draw has been filmed. The filming itself is a procedure and not a parcours (see config/procedures.json): it happens on the public page, outside any screen a step could highlight, and the recording has to be running before the very click that ends it
    private function drawVideoProject(): array
    {
        return [
            'slug' => 'crowdfunding-draw-video',
            'label' => 'label.guided_project_crowdfunding_draw_video',
            'description' => 'description.guided_project_crowdfunding_draw_video',
            'translation_domain' => 'crowdfunding',
            'order' => 9060,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_crowdfunding_draw_video_open',
                    'description' => 'description.guided_step_crowdfunding_draw_video_open',
                    'narration' => 'narration.guided_step_crowdfunding_draw_video_open',
                    'url' => $this->indexUrl(),
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_draw_video_edit',
                    'description' => 'description.guided_step_crowdfunding_draw_video_edit',
                    'narration' => 'narration.guided_step_crowdfunding_draw_video_edit',
                    'highlight' => '.action-edit',
                ],
                // The same folded accordion item as in the lottery parcours: the video field is nested in it, so the entry is opened before anything inside it is pointed at
                [
                    'label' => 'label.guided_step_crowdfunding_draw_video_lottery',
                    'description' => 'description.guided_step_crowdfunding_draw_video_lottery',
                    'narration' => 'narration.guided_step_crowdfunding_draw_video_lottery',
                    'highlight' => '[data-crowdfunding-lotteries] .accordion-button',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_draw_video_upload',
                    'description' => 'description.guided_step_crowdfunding_draw_video_upload',
                    'narration' => 'narration.guided_step_crowdfunding_draw_video_upload',
                    'highlight' => '[data-lottery-videos]',
                ],
                // The way out for a file too heavy to upload: only a campaign's video carries an address, a lottery's taking a file and nothing else (see CrowdfundingVideo::$youtubeUrl, which LotteryVideo has no equivalent of)
                [
                    'label' => 'label.guided_step_crowdfunding_draw_video_youtube',
                    'description' => 'description.guided_step_crowdfunding_draw_video_youtube',
                    'narration' => 'narration.guided_step_crowdfunding_draw_video_youtube',
                    'highlight' => '[data-crowdfunding-videos]',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_draw_video_save',
                    'narration' => 'narration.guided_step_crowdfunding_draw_video_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_draw_video_done',
                    'description' => 'description.guided_step_crowdfunding_draw_video_done',
                    'narration' => 'narration.guided_step_crowdfunding_draw_video_done',
                ],
            ],
        ];
    }

    // The bar the whole CRUD sits behind, index to delete (see CrowdfundingCrudController::configureActions) - a campaign holds what the site is paid for, so it is not content an editor composes
    private function roleNeeded(): string
    {
        return (string) $this->configService->get('site-role-admin');
    }

    // The one screen this bundle contributes: a campaign carries its media, its counterparts, its lottery and its blocks on its own form
    private function indexUrl(): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController(CrowdfundingCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }
}
