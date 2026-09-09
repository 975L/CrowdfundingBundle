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

// This bundle's guided projects, running the 9000 block GuidedProjectProviderInterface reserves them - the same docblock stating every other bundle's, so a range is read there rather than recopied here. They follow the order a campaign is actually lived: the campaign itself, then the opening of it to the public, then what illustrates it, then what is offered in return, then the rest of its page, then its chronicle, then the lottery and the video of its draw, and last the two gestures that remove it. Every one of them opens on the same screen, this bundle holding a single CRUD: a campaign carries its media, its counterparts, its news, its lottery and its blocks on its own edit form, so what tells the parcours apart is the fieldset they walk to, not the screen they open
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
            $this->publishProject(),
            $this->mediaProject(),
            $this->counterpartProject(),
            $this->blocksProject(),
            $this->newsProject(),
            $this->lotteryProject(),
            $this->drawVideoProject(),
            $this->trashProject(),
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

    // A campaign is written masked and stays out of everything until someone says otherwise: the switch is the last gesture of its preparation, and the preview is what one looks at before making it
    private function publishProject(): array
    {
        return [
            'slug' => 'crowdfunding-publish',
            'label' => 'label.guided_project_crowdfunding_publish',
            'description' => 'description.guided_project_crowdfunding_publish',
            'translation_domain' => 'crowdfunding',
            'order' => 9015,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_crowdfunding_publish_open',
                    'description' => 'description.guided_step_crowdfunding_publish_open',
                    'narration' => 'narration.guided_step_crowdfunding_publish_open',
                    'url' => $this->indexUrl(),
                ],
                // Only shown on a campaign still masked (see CrowdfundingCrudController::configureActions), which is exactly the one this parcours is about to open
                [
                    'label' => 'label.guided_step_crowdfunding_publish_preview',
                    'description' => 'description.guided_step_crowdfunding_publish_preview',
                    'narration' => 'narration.guided_step_crowdfunding_publish_preview',
                    'highlight' => '.action-preview',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_publish_edit',
                    'description' => 'description.guided_step_crowdfunding_publish_edit',
                    'narration' => 'narration.guided_step_crowdfunding_publish_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_publish_hidden',
                    'description' => 'description.guided_step_crowdfunding_publish_hidden',
                    'narration' => 'narration.guided_step_crowdfunding_publish_hidden',
                    'highlight' => '#Crowdfunding_hidden',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_publish_save',
                    'narration' => 'narration.guided_step_crowdfunding_publish_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                // The other half of the pair: the button that was a preview while the campaign was masked is the site's own page once it is not
                [
                    'label' => 'label.guided_step_crowdfunding_publish_visit',
                    'description' => 'description.guided_step_crowdfunding_publish_visit',
                    'narration' => 'narration.guided_step_crowdfunding_publish_visit',
                    'highlight' => '.action-viewOnSite',
                ],
                // Back onto the campaign's own form: the save two steps above returned to the listing, and the code is only drawn on the edit screen (see CrowdfundingCrudController::configureFields), so the step below would otherwise open on a screen its marker is not on
                [
                    'label' => 'label.guided_step_crowdfunding_publish_reopen',
                    'description' => 'description.guided_step_crowdfunding_publish_reopen',
                    'narration' => 'narration.guided_step_crowdfunding_publish_reopen',
                    'highlight' => '.action-edit',
                ],
                // Last, and only here: the code is drawn from the campaign's public address, which is worth nothing while the campaign is still masked
                [
                    'label' => 'label.guided_step_crowdfunding_publish_qrcode',
                    'description' => 'description.guided_step_crowdfunding_publish_qrcode',
                    'narration' => 'narration.guided_step_crowdfunding_publish_qrcode',
                    'highlight' => '[data-crowdfunding-qrcode]',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_publish_done',
                    'description' => 'description.guided_step_crowdfunding_publish_done',
                    'narration' => 'narration.guided_step_crowdfunding_publish_done',
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
                    'label' => 'label.guided_step_crowdfunding_media_cover',
                    'description' => 'description.guided_step_crowdfunding_media_cover',
                    'narration' => 'narration.guided_step_crowdfunding_media_cover',
                    'highlight' => '[data-crowdfunding-cover]',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_media_hero',
                    'description' => 'description.guided_step_crowdfunding_media_hero',
                    'narration' => 'narration.guided_step_crowdfunding_media_hero',
                    'highlight' => '[data-crowdfunding-hero]',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_media_photos',
                    'description' => 'description.guided_step_crowdfunding_media_photos',
                    'narration' => 'narration.guided_step_crowdfunding_media_photos',
                    'highlight' => '[data-crowdfunding-slides]',
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
                // Said where the editor is composing, and nowhere else: the fourth kind of this bundle is the only one carrying no "contexts" (see config/services.yaml), so it is offered on the site's own pages rather than on a campaign's. No url and no marker - the screen it is placed on belongs to another bundle, and a step leading there would tie this parcours to it
                [
                    'label' => 'label.guided_step_crowdfunding_blocks_listing',
                    'description' => 'description.guided_step_crowdfunding_blocks_listing',
                    'narration' => 'narration.guided_step_crowdfunding_blocks_listing',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_blocks_done',
                    'description' => 'description.guided_step_crowdfunding_blocks_done',
                    'narration' => 'narration.guided_step_crowdfunding_blocks_done',
                ],
            ],
        ];
    }

    // The chronicle of a campaign, once its page is composed: a follow-up is published from the public page, where its author reaches a form no back-office account is needed for, and only corrected or removed here - which is what this parcours walks. The publishing itself is the "publier-actualite-campagne" procedure, happening outside any screen a step could highlight
    private function newsProject(): array
    {
        return [
            'slug' => 'crowdfunding-news',
            'label' => 'label.guided_project_crowdfunding_news',
            'description' => 'description.guided_project_crowdfunding_news',
            'translation_domain' => 'crowdfunding',
            'order' => 9045,
            'role' => $this->roleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_crowdfunding_news_open',
                    'description' => 'description.guided_step_crowdfunding_news_open',
                    'narration' => 'narration.guided_step_crowdfunding_news_open',
                    'url' => $this->indexUrl(),
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_news_edit',
                    'description' => 'description.guided_step_crowdfunding_news_edit',
                    'narration' => 'narration.guided_step_crowdfunding_news_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_news_fieldset',
                    'description' => 'description.guided_step_crowdfunding_news_fieldset',
                    'narration' => 'narration.guided_step_crowdfunding_news_fieldset',
                    'highlight' => '[data-crowdfunding-news]',
                ],
                // Same fold as the counterpart and lottery parcours: the entry is opened before anything nested in it is read
                [
                    'label' => 'label.guided_step_crowdfunding_news_expand',
                    'description' => 'description.guided_step_crowdfunding_news_expand',
                    'narration' => 'narration.guided_step_crowdfunding_news_expand',
                    'highlight' => '[data-crowdfunding-news] .accordion-button',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_news_save',
                    'narration' => 'narration.guided_step_crowdfunding_news_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_news_done',
                    'description' => 'description.guided_step_crowdfunding_news_done',
                    'narration' => 'narration.guided_step_crowdfunding_news_done',
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

    // Deleting a campaign takes two gestures and two screens, which is the whole point: the first is undone from the recycle bin, the second is not undone at all
    private function trashProject(): array
    {
        return [
            'slug' => 'crowdfunding-trash',
            'label' => 'label.guided_project_crowdfunding_trash',
            'description' => 'description.guided_project_crowdfunding_trash',
            'translation_domain' => 'crowdfunding',
            'order' => 9070,
            // Deleting only moves a campaign to the recycle bin, which an editor may do - emptying it or pulling a campaign back out is "site-role-admin" (see CrowdfundingCrudController::grantActions()), so the whole parcours takes that role rather than ending on an access-denied page
            'role' => $this->adminRoleNeeded(),
            'steps' => [
                [
                    'label' => 'label.guided_step_crowdfunding_trash_open',
                    'description' => 'description.guided_step_crowdfunding_trash_open',
                    'narration' => 'narration.guided_step_crowdfunding_trash_open',
                    'url' => $this->indexUrl(),
                ],
                // "Delete" only moves the campaign to the recycle bin here (see CrowdfundingCrudController::deleteEntity), which is why the parcours goes on rather than ending on it
                [
                    'label' => 'label.guided_step_crowdfunding_trash_delete',
                    'description' => 'description.guided_step_crowdfunding_trash_delete',
                    'narration' => 'narration.guided_step_crowdfunding_trash_delete',
                    'highlight' => '.action-delete',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_trash_bin',
                    'description' => 'description.guided_step_crowdfunding_trash_bin',
                    'narration' => 'narration.guided_step_crowdfunding_trash_bin',
                    'highlight' => '.action-trash',
                ],
                // Both buttons below only exist on a trashed row, so the parcours has the user open the recycle bin on the step before rather than pointing at them from the listing
                [
                    'label' => 'label.guided_step_crowdfunding_trash_restore',
                    'description' => 'description.guided_step_crowdfunding_trash_restore',
                    'narration' => 'narration.guided_step_crowdfunding_trash_restore',
                    'highlight' => '.action-restore',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_trash_purge',
                    'description' => 'description.guided_step_crowdfunding_trash_purge',
                    'narration' => 'narration.guided_step_crowdfunding_trash_purge',
                    'highlight' => '.action-deletePermanently',
                ],
                [
                    'label' => 'label.guided_step_crowdfunding_trash_done',
                    'description' => 'description.guided_step_crowdfunding_trash_done',
                    'narration' => 'narration.guided_step_crowdfunding_trash_done',
                ],
            ],
        ];
    }

    // The bar the whole CRUD sits behind, index to delete (see CrowdfundingCrudController::configureActions) - a campaign is written and opened by whoever writes the site
    private function roleNeeded(): string
    {
        return (string) $this->configService->get('site-role-editor');
    }

    // The stricter bar, for the one parcours walking to the recycle bin's own two actions
    private function adminRoleNeeded(): string
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
