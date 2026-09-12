<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Controller\Management;

use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\Management\ContentLocaleScreen;
use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Repository\RedirectRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Form\CrowdfundingCounterpartType;
use c975L\CrowdfundingBundle\Form\CrowdfundingMediaType;
use c975L\CrowdfundingBundle\Form\CrowdfundingNewsType;
use c975L\CrowdfundingBundle\Form\CrowdfundingVideoType;
use c975L\CrowdfundingBundle\Form\LotteryType;
use c975L\CrowdfundingBundle\Form\Type\CrowdfundingQrCodeType;
use c975L\CrowdfundingBundle\Management\CrowdfundingBlockOwnerResolver;
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslator;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Endroid\QrCode\Builder\Builder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Locales;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class CrowdfundingCrudController extends AbstractCrudController
{
    // The path a campaign is served under, and what a "gone" Redirect is written from once one is deleted for good
    private const string CROWDFUNDING_PATH = '/crowdfunding/';

    public const string RESTORE_CSRF_TOKEN = 'crowdfunding_restore';

    public const string DELETE_PERMANENTLY_CSRF_TOKEN = 'crowdfunding_delete_permanently';

    public function __construct(
        private readonly BlockMoveRowAttrBuilder $blockMoveRowAttrBuilder,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RequestStack $requestStack,
        private readonly RedirectRepository $redirectRepository,
        private readonly ContentLocaleScreen $contentLocaleScreen,
        private readonly CrowdfundingTranslator $crowdfundingTranslator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Crowdfunding::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $crowdfunding = $this->getContext()?->getEntity()->getInstance();

        // The very same edit screen, opened on another language: what that language says of the campaign itself. A goal, a currency, a set of dates and a slug are the same in every language and are written on the screen the campaign was written on (see ContentLocaleScreen)
        $contentLocale = Crud::PAGE_EDIT === $pageName ? $this->contentLocale() : null;
        if (null !== $contentLocale && $crowdfunding instanceof Crowdfunding) {
            return $this->translationFields($crowdfunding, $contentLocale);
        }

        return [
            IdField::new('id')
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('title')
                ->setLabel(t('label.title', [], 'crowdfunding')),
            $this->hiddenField(),
            SlugField::new('slug')
                ->setTargetFieldName('title')
                ->hideOnIndex(),
            MoneyField::new('amountGoal')
                ->setLabel(t('label.goal', [], 'crowdfunding'))
                ->setCurrency('EUR')
                ->setStoredAsCents(true),
            TextField::new('currency')
                ->setLabel(t('label.currency', [], 'crowdfunding')),
            MoneyField::new('amountAchieved')
                ->setLabel(t('label.amount_achieved', [], 'crowdfunding'))
                ->setCurrency('EUR')
                ->setStoredAsCents(true)
                ->hideOnForm(),
            DateField::new('beginDate')
                ->setLabel(t('label.begin_date', [], 'crowdfunding'))
                ->setRequired(true),
            DateField::new('endDate')
                ->setLabel(t('label.end_date', [], 'crowdfunding'))
                ->setRequired(true),
            TextEditorField::new('description')
                ->setLabel(t('label.description', [], 'crowdfunding'))
                ->hideOnIndex(),

            // Author
            FormField::addFieldset(t('label.author', [], 'crowdfunding'))
                ->hideOnIndex(),
            TextField::new('authorName')
                ->setLabel(t('label.author', [], 'crowdfunding')),
            TextEditorField::new('authorPresentation')
                ->setLabel(t('label.author_presentation', [], 'crowdfunding'))
                ->hideOnIndex(),
            TextField::new('authorWebsite')
                ->setLabel(t('label.website', [], 'crowdfunding')),
            TextEditorField::new('useFor')
                ->setLabel(t('label.use_for', [], 'crowdfunding'))
                ->hideOnIndex(),

            ...$this->mediaFields($crowdfunding),

            // Counterpart management
            FormField::addFieldset(t('label.counterparts', [], 'crowdfunding'))
                ->setHelp(t('text.items_management', [], 'crowdfunding'))
                ->hideOnIndex(),
            CollectionField::new('counterparts')
                ->hideOnIndex()
                ->setEntryType(CrowdfundingCounterpartType::class)
                ->setFormTypeOption('row_attr', ['data-crowdfunding-counterparts' => '1']),

            ...$this->newsFields(),

            // Lottery management
            FormField::addFieldset(t('label.lottery', [], 'crowdfunding'))
                ->hideOnIndex(),
            CollectionField::new('lotteries')
                ->hideOnIndex()
                ->setEntryType(LotteryType::class)
                ->setFormTypeOption('row_attr', ['data-crowdfunding-lotteries' => '1']),

            // What an admin composes the rest of the page with, on top of the fields above
            FormField::addFieldset(t('label.blocks', [], 'crowdfunding'))
                ->hideOnIndex(),
            $this->blocksField($crowdfunding),

            ...$this->qrCodeFields(),

            // Dates
            DateTimeField::new('creation')
                ->setLabel(t('label.creation', [], 'crowdfunding'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled')
                ->onlyOnDetail(),
            DateTimeField::new('modification')
                ->setLabel(t('label.modification', [], 'crowdfunding'))
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled')
                ->onlyOnDetail(),
        ];
    }

    // Trashed campaigns are hidden by definition (see deleteEntity() and Crowdfunding::setIsDeleted()), so the column would hold "yes" for every row of that view - taken off rather than left there saying nothing
    private function hiddenField(): BooleanField
    {
        $hiddenField = BooleanField::new('hidden')
            ->setLabel(t('label.hidden', [], 'crowdfunding'))
            ->setHelp(t('text.hidden', [], 'crowdfunding'));
        if ($this->isTrash()) {
            $hiddenField->hideOnIndex();
        }

        return $hiddenField;
    }

    // The QR code leading to the campaign's public page, on a fieldset of its own. Edit only: it is drawn from the campaign's saved id, which a campaign being created does not have yet
    /** @return list<FieldInterface> */
    private function qrCodeFields(): array
    {
        return [
            FormField::addFieldset(t('label.qrcode', [], 'crowdfunding'))
                ->hideOnIndex()
                ->onlyWhenUpdating(),
            // The widget prints no id of its own, so its row carries the marker the guided projects point at (see CrowdfundingGuidedProjectProvider)
            Field::new('qrcode', false)
                ->setFormType(CrowdfundingQrCodeType::class)
                ->setFormTypeOption('row_attr', ['data-crowdfunding-qrcode' => '1'])
                ->onlyWhenUpdating(),
        ];
    }

    // The campaign's chronicle, named rather than inlined like the media above: a follow-up is written from the campaign page by its author, and only corrected or removed here - the page's form adds one and nothing else
    /** @return list<FieldInterface> */
    private function newsFields(): array
    {
        return [
            FormField::addFieldset(t('label.news', [], 'crowdfunding'))
                ->setHelp(t('text.items_management', [], 'crowdfunding'))
                ->hideOnIndex(),
            CollectionField::new('news')
                ->hideOnIndex()
                ->setEntryType(CrowdfundingNewsType::class)
                // A CollectionField prints no id of its own, so its row carries the marker the guided projects point at (see CrowdfundingGuidedProjectProvider)
                ->setFormTypeOption('row_attr', ['data-crowdfunding-news' => '1']),
        ];
    }

    // The fieldset a campaign's own files are laid on, named rather than inlined: a field per use rather than one collection asking what a file is for, the field it is dropped on being what says so (see Crowdfunding::addCover(), addHero() and addSlide()) - the cover the catalogue shows, the image the page opens on, then the slider's plates
    /** @return list<FieldInterface> */
    private function mediaFields(mixed $crowdfunding): array
    {
        return [
            FormField::addFieldset(t('label.media', [], 'crowdfunding'))
                ->hideOnIndex(),
            CollectionField::new('covers')
                ->setLabel(t('label.cover', [], 'crowdfunding'))
                ->setHelp(t('text.cover', [], 'crowdfunding'))
                ->hideOnIndex()
                ->setEntryType(CrowdfundingMediaType::class)
                // One image only, same as the opening one below: a campaign stands for itself with a single cover
                ->allowAdd(self::isEmpty($crowdfunding?->getCovers()))
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('row_attr', ['data-crowdfunding-cover' => '1']),
            CollectionField::new('heroes')
                ->setLabel(t('label.hero', [], 'crowdfunding'))
                ->setHelp(t('text.hero', [], 'crowdfunding'))
                ->hideOnIndex()
                ->setEntryType(CrowdfundingMediaType::class)
                // One image only: a campaign opens on one image, and "Add an item" under the one already laid invited a second nothing would have shown
                ->allowAdd(self::isEmpty($crowdfunding?->getHeroes()))
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                // A CollectionField prints no id of its own, so its row carries the marker the guided projects point at (see CrowdfundingGuidedProjectProvider)
                ->setFormTypeOption('row_attr', ['data-crowdfunding-hero' => '1']),
            CollectionField::new('slides')
                ->setLabel(t('label.slides', [], 'crowdfunding'))
                ->setHelp(t('text.slides', [], 'crowdfunding'))
                ->hideOnIndex()
                ->setEntryType(CrowdfundingMediaType::class)
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('row_attr', ['data-crowdfunding-slides' => '1']),
            CollectionField::new('videos')
                ->hideOnIndex()
                ->setEntryType(CrowdfundingVideoType::class)
                ->setFormTypeOption('row_attr', ['data-crowdfunding-videos' => '1']),
        ];
    }

    // Is the opening image's slot still free? Read on the row as it stands in database: the "Add" link comes back on saving a removal, not on the click of the trash - laying another file on the existing row stays the gesture replacing an image
    /** @param Collection<int, mixed>|null $medias */
    private static function isEmpty(?Collection $medias): bool
    {
        return null === $medias || $medias->isEmpty();
    }

    // The block editor, the one field carrying enough of its own configuration to read better named than inlined - its row_attr is UiBundle's, which is what the move buttons of a block read their target from
    private function blocksField(mixed $crowdfunding): CollectionField
    {
        return CollectionField::new('blocks')
            ->setLabel(false)
            ->hideOnIndex()
            // CollectionField's "col-md-8 col-xxl-7" default would leave a nested block editor working in 7/12 of the row
            ->setColumns('col-12')
            ->setEntryType(BlockType::class)
            ->allowAdd()
            ->allowDelete()
            ->setFormTypeOption('by_reference', false)
            ->setFormTypeOption('row_attr', $this->blockMoveRowAttrBuilder->build(CrowdfundingBlockOwnerResolver::TYPE_CROWDFUNDING, $crowdfunding instanceof Crowdfunding ? $crowdfunding->getId() : null))
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-editor');

        // Opens the public page of the campaign on the site, in a new tab - out while it is not readable there, which a button leading to a 404 would not say
        $viewOnSiteAction = Action::new('viewOnSite', t('action.view_on_site', [], 'crowdfunding'), 'fa fa-external-link-alt')
            ->linkToUrl(fn (Crowdfunding $crowdfunding): string => $this->generateUrl('crowdfunding_display', ['slug' => $crowdfunding->getSlug()]))
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(static fn (Crowdfunding $crowdfunding): bool => !$crowdfunding->isHidden() && !$crowdfunding->isDeleted() && '' !== (string) $crowdfunding->getSlug())
            ->addCssClass('btn btn-secondary')
        ;

        // Opens the page of a campaign that is not opened yet, in a new tab - the same page the visitor will read, minus the block cache and with a banner saying so
        $previewAction = Action::new('preview', t('action.preview', [], 'crowdfunding'), 'fa fa-eye')
            ->linkToUrl(fn (Crowdfunding $crowdfunding): string => $this->generateUrl('crowdfunding_preview', ['slug' => $crowdfunding->getSlug()]))
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(static fn (Crowdfunding $crowdfunding): bool => $crowdfunding->isHidden() && !$crowdfunding->isDeleted() && '' !== (string) $crowdfunding->getSlug())
            ->addCssClass('btn btn-secondary')
        ;

        // Takes a campaign back out of the recycle bin, with everything it still holds - it comes back hidden, its own switch having been turned on when it was trashed
        $restoreAction = Action::new('restore', t('action.restore', [], 'crowdfunding'), 'fa fa-trash-restore')
            ->linkToUrl(fn (Crowdfunding $crowdfunding): string => $this->tokenizedUrl('restore', $crowdfunding, self::RESTORE_CSRF_TOKEN))
            ->displayIf(static fn (Crowdfunding $crowdfunding): bool => $crowdfunding->isDeleted())
            ->addCssClass('btn btn-secondary')
        ;

        // Removes the campaign for good, its medias, its counterparts and its draws with it - only reachable from the recycle bin
        $deletePermanentlyAction = Action::new('deletePermanently', t('action.delete_permanently', [], 'crowdfunding'), 'fa fa-trash')
            ->linkToUrl(fn (Crowdfunding $crowdfunding): string => $this->tokenizedUrl('deletePermanently', $crowdfunding, self::DELETE_PERMANENTLY_CSRF_TOKEN))
            ->displayIf(static fn (Crowdfunding $crowdfunding): bool => $crowdfunding->isDeleted())
            ->askConfirmation(t('confirm.delete_permanently', [], 'crowdfunding'))
            ->asDangerAction()
            ->addCssClass('btn btn-danger')
        ;

        $actions = $actions
            ->add(Crud::PAGE_INDEX, $this->trashAction())
            ->add(Crud::PAGE_INDEX, $viewOnSiteAction)
            ->add(Crud::PAGE_INDEX, $previewAction)
            ->add(Crud::PAGE_INDEX, $this->translateAction())
            ->add(Crud::PAGE_INDEX, $restoreAction)
            ->add(Crud::PAGE_INDEX, $deletePermanentlyAction)
            ->add(Crud::PAGE_EDIT, $viewOnSiteAction)
            ->add(Crud::PAGE_EDIT, $previewAction)
            // A campaign is sent to the recycle bin from its own screen too, rather than only from the row button of the list - this version of EasyAdmin puts no delete button on the edit page
            ->add(Crud::PAGE_EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action->displayIf(static fn (Crowdfunding $crowdfunding): bool => !$crowdfunding->isDeleted()),
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            // "Delete" only moves the campaign to the recycle bin here, and says so - what actually removes it is the action of that view
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action
                    ->setIcon('fa fa-box-archive')
                    ->askConfirmation(t('confirm.move_to_trash', [], 'crowdfunding'))
                    ->displayIf(static fn (Crowdfunding $crowdfunding): bool => !$crowdfunding->isDeleted()),
                $this->translator->trans('action.move_to_trash', [], 'crowdfunding'),
            ))
            // Same gesture on the screen itself, keeping its words there, where the bar has the room for them
            ->update(Crud::PAGE_EDIT, Action::DELETE, fn (Action $action) => $action
                ->setLabel(t('action.move_to_trash', [], 'crowdfunding'))
                ->setIcon('fa fa-box-archive')
                ->askConfirmation(t('confirm.move_to_trash', [], 'crowdfunding'))
                ->displayIf(static fn (Crowdfunding $crowdfunding): bool => !$crowdfunding->isDeleted()))
            ->update(Crud::PAGE_INDEX, Action::DETAIL, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.detail', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, 'viewOnSite', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.view_on_site', [], 'crowdfunding'),
            ))
            ->update(Crud::PAGE_INDEX, 'preview', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.preview', [], 'crowdfunding'),
            ))
            ->update(Crud::PAGE_INDEX, 'translate', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.translate', [], 'crowdfunding'),
            ))
            ->update(Crud::PAGE_INDEX, 'restore', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.restore', [], 'crowdfunding'),
            ))
            ->update(Crud::PAGE_INDEX, 'deletePermanently', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.delete_permanently', [], 'crowdfunding'),
            ))
            ->reorder(Crud::PAGE_INDEX, [Action::EDIT, Action::DETAIL, 'viewOnSite', 'preview', 'translate', 'restore', Action::DELETE, 'deletePermanently'])
            ->reorder(Crud::PAGE_EDIT, ['viewOnSite', 'preview'])
        ;

        return $this->grantActions($actions, $role);
    }

    // Every screen and every button of the campaigns behind the same role, named rather than left at the end of the chain above - a campaign is written and opened by whoever writes the site, which is the bar every other c975L CRUD sets on its own index
    private function grantActions(Actions $actions, string $role): Actions
    {
        // Deleting a campaign only moves it to the recycle bin, which an editor may do - pulling one back out or removing it for good is the bar restore()/deletePermanently() state themselves, and a button leading to its own 403 is a button not to draw
        $adminRole = $this->configService->get('site-role-admin');

        return $actions
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            ->setPermission(Action::DETAIL, $role)
            ->setPermission('viewOnSite', $role)
            ->setPermission('preview', $role)
            ->setPermission('translate', $role)
            ->setPermission('trash', $role)
            ->setPermission('restore', $adminRole)
            ->setPermission('deletePermanently', $adminRole)
            ->setPermission('qrcode', $role)
        ;
    }

    // Whether the index is showing the recycle bin rather than the campaigns - the one flag the whole screen reads, from the fields to the query
    private function isTrash(): bool
    {
        return (bool) $this->requestStack->getCurrentRequest()?->query->get('trash');
    }

    // Toggles between "recycle bin" and "back to the campaigns", depending on which of the two is being shown
    private function trashAction(): Action
    {
        $action = $this->isTrash()
            ? Action::new('trash', t('label.crowdfundings', [], 'crowdfunding'), 'fa fa-box-open')
                ->linkToUrl(fn (): string => $this->adminUrlGenerator
                    ->unsetAll()
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->generateUrl())
            : Action::new('trash', t('action.trash', [], 'crowdfunding'), 'fa fa-trash-alt')
                ->linkToUrl(fn (): string => $this->trashIndexUrl());

        return $action
            ->createAsGlobalAction()
            ->addCssClass('btn btn-secondary')
        ;
    }

    // Opens the first language screen straight from the list, the way ShopBundle's products and SiteBundle's pages are translated - the tabs above a campaign already opened are the only other way in, and a translation screen nobody finds translates nothing
    private function translateAction(): Action
    {
        return $this->contentLocaleScreen
            ->action('translate', t('action.translate', [], 'crowdfunding'), 'fa fa-language', $this->crowdfundingTranslator->getTranslatableLocales())
            ->displayIf(fn (Crowdfunding $crowdfunding): bool => !$crowdfunding->isDeleted() && $this->crowdfundingTranslator->isActive())
            ->addCssClass('btn btn-secondary')
        ;
    }

    // The recycle bin both trash actions come back to, whether they ran or were refused
    private function trashIndexUrl(): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->set('trash', 1)
            ->generateUrl();
    }

    // The url of a row action, its csrf token in the query string - both are read from the recycle bin and come back to it, so they carry the flag that view is read from
    private function tokenizedUrl(string $action, Crowdfunding $crowdfunding, string $tokenId): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController(self::class)
            ->setAction($action)
            ->setEntityId($crowdfunding->getId())
            ->set('token', $this->csrfTokenManager->getToken($tokenId)->getValue())
            ->set('trash', 1)
            ->generateUrl();
    }

    // The campaigns, or the recycle bin - never the two mixed
    #[\Override]
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.isDeleted = :isDeleted')
            ->setParameter('isDeleted', $this->isTrash())
        ;
    }

    // Puts back what a contributor already paid for, before the save turns its removal into a DELETE - the campaign's collections carry "orphanRemoval" (see Crowdfunding), which the form's own delete buttons then reach
    #[\Override]
    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if ($entityInstance instanceof Crowdfunding) {
            $this->keepSubscribedCounterparts($entityInstance);
            $this->keepDrawnLotteries($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    // The rows of crowdfunding_contributor_counterpart pointing at a tier would have the database refuse the whole flush with a foreign key error, rather than a sentence the editor can act on
    private function keepSubscribedCounterparts(Crowdfunding $crowdfunding): void
    {
        $kept = [];
        foreach ($this->removedFrom($crowdfunding->getCounterparts()) as $counterpart) {
            if ($counterpart instanceof CrowdfundingCounterpart && !$counterpart->getContributors()->isEmpty()) {
                $crowdfunding->addCounterpart($counterpart);
                $kept[] = (string) $counterpart->getTitle();
            }
        }

        // Said once for all of them rather than one message per row, and named so the editor knows which of the tiers came back
        if ([] !== $kept) {
            $this->addFlash('danger', $this->translator->trans('flash.counterparts_kept_subscribed', ['%counterparts%' => implode(', ', $kept)], 'crowdfunding'));
        }
    }

    // A draw refuses nothing, which is worse: it cascades its own removal to its prizes and its tickets, so a lottery taken off the form would go through and take the winner already drawn with it
    private function keepDrawnLotteries(Crowdfunding $crowdfunding): void
    {
        $kept = [];
        foreach ($this->removedFrom($crowdfunding->getLotteries()) as $lottery) {
            if ($lottery instanceof Lottery && !$lottery->getTickets()->isEmpty()) {
                $crowdfunding->addLottery($lottery);
                $kept[] = (string) $lottery->getIdentifier();
            }
        }

        if ([] !== $kept) {
            $this->addFlash('danger', $this->translator->trans('flash.lotteries_kept_drawn', ['%lotteries%' => implode(', ', $kept)], 'crowdfunding'));
        }
    }

    // What was taken off a collection since the form was loaded, read from its own snapshot - a removed entry is no longer in the collection, so there is nothing else to compare against, and one built outside the ORM carries no snapshot at all
    private function removedFrom(Collection $collection): array
    {
        return $collection instanceof PersistentCollection ? $collection->getDeleteDiff() : [];
    }

    // Deleting a campaign only moves it to the recycle bin, with everything it holds: its url answers 410 from there, and it is restored or removed for good from that view alone
    #[\Override]
    public function deleteEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if (!$entityInstance instanceof Crowdfunding) {
            parent::deleteEntity($entityManager, $entityInstance);

            return;
        }

        $entityInstance->setIsDeleted(true);
        $entityManager->flush();
    }

    // Draws on the fly the QR code leading to the campaign's public page, printed on the edit screen (see management/crowdfunding_crud_form_theme.html.twig)
    #[AdminRoute('/{entityId}/qrcode')]
    public function qrcode(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $crowdfunding = $context->getEntity()->getInstance();

        // The url names the campaign the code has to lead to - with none, or with one deleted since the screen was drawn, there is no code to draw rather than a fatal on the slug below
        if (!$crowdfunding instanceof Crowdfunding) {
            throw $this->createNotFoundException();
        }

        // Built off the site's own address rather than the request, the back office being reached on a host of its own on some installs
        $builder = new Builder();
        $result = $builder->build(
            data: rtrim((string) $this->configService->get('site-url'), '/') . self::CROWDFUNDING_PATH . $crowdfunding->getSlug(),
            size: 250,
            margin: 10,
        );

        return new Response($result->getString(), Response::HTTP_OK, ['Content-Type' => $result->getMimeType()]);
    }

    // Takes a campaign back out of the recycle bin, untouched - it comes back hidden, to be read once before it is opened again
    #[AdminRoute(options: ['methods' => ['GET']])]
    public function restore(Request $request, CrowdfundingRepository $crowdfundingRepository, EntityManagerInterface $entityManager): Response
    {
        $crowdfunding = $this->trashedCampaign($request, $crowdfundingRepository, self::RESTORE_CSRF_TOKEN);
        if (!$crowdfunding instanceof Crowdfunding) {
            return $this->redirect($this->trashIndexUrl());
        }

        $crowdfunding->setIsDeleted(false);
        $entityManager->flush();

        $this->addFlash('success', $this->translator->trans('flash.crowdfunding_restored', [], 'crowdfunding'));

        return $this->redirect($this->trashIndexUrl());
    }

    // Removes the campaign for good, its medias, its counterparts, its news and its draws with it - only reachable from the recycle bin
    #[AdminRoute(options: ['methods' => ['GET']])]
    public function deletePermanently(Request $request, CrowdfundingRepository $crowdfundingRepository, EntityManagerInterface $entityManager): Response
    {
        $crowdfunding = $this->trashedCampaign($request, $crowdfundingRepository, self::DELETE_PERMANENTLY_CSRF_TOKEN);
        if (!$crowdfunding instanceof Crowdfunding) {
            return $this->redirect($this->trashIndexUrl());
        }

        $this->writeGoneRedirect($entityManager, $crowdfunding);

        $entityManager->remove($crowdfunding);
        $entityManager->flush();

        $this->addFlash('success', $this->translator->trans('flash.crowdfunding_deleted_permanently', [], 'crowdfunding'));

        return $this->redirect($this->trashIndexUrl());
    }

    // The campaign both trash actions run on: the role, the csrf token and the row itself, checked once for the two of them. Null is "go back to the recycle bin", where the row the action was clicked on is either still there or already gone
    private function trashedCampaign(Request $request, CrowdfundingRepository $crowdfundingRepository, string $tokenId): ?Crowdfunding
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        if (!$this->isCsrfTokenValid($tokenId, $request->query->getString('token'))) {
            return null;
        }

        $crowdfunding = $crowdfundingRepository->find($request->query->getInt('entityId'));

        // The two actions belong to the recycle bin: a campaign that is not in it is not theirs to act on, whatever the url says
        return $crowdfunding instanceof Crowdfunding && $crowdfunding->isDeleted() ? $crowdfunding : null;
    }

    // The 410 the recycle bin served only lasts as long as the campaign can be restored, and the url would fall back to a plain 404 once the row is gone - a "gone" Redirect keeps answering 410 for good, which a search engine acts on far faster. Redirects pointing at that url are turned into "gone" rows too rather than left dangling, and a path an admin already covered deliberately is left alone, a target saying more than a dead end
    private function writeGoneRedirect(EntityManagerInterface $entityManager, Crowdfunding $crowdfunding): void
    {
        $fromPath = self::CROWDFUNDING_PATH . $crowdfunding->getSlug();

        foreach ($this->redirectRepository->findByToUrl($fromPath) as $redirect) {
            $redirect->setGone(true)->setToUrl(null);
        }

        if (null === $this->redirectRepository->findOneByFromPath($fromPath)) {
            $redirect = new Redirect();
            $redirect->setFromPath($fromPath)->setGone(true);
            $entityManager->persist($redirect);
        }
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            // Named in the editor's own language: with no label, EasyAdmin falls back on the class name and prints "Crowdfunding" on every screen and every button
            ->setEntityLabelInSingular(t('label.crowdfunding', [], 'crowdfunding'))
            ->setEntityLabelInPlural(t('label.crowdfundings', [], 'crowdfunding'))
            ->setEntityPermission($this->configService->get('site-role-editor'))
            // Carries the language tabs above the form, and nothing at all on a site declaring a single language (see ContentLocaleScreen)
            ->overrideTemplate('crud/edit', '@c975LCrowdfunding/management/crowdfunding_crud_edit.html.twig')
            ->setDefaultSort(['endDate' => 'DESC'])
            ->overrideTemplate('crud/index', '@c975LCrowdfunding/management/crowdfunding_crud_index.html.twig')
            // Appended rather than set alone: EasyAdmin's own theme is what every other field of the screen is drawn by
            ->addFormTheme('@c975LCrowdfunding/management/crowdfunding_crud_form_theme.html.twig')
        ;
    }

    // The language this campaign is being written in, when it is not the one the site was written in (see ContentLocaleScreen)
    private function contentLocale(): ?string
    {
        return $this->contentLocaleScreen->locale($this->crowdfundingTranslator->getTranslatableLocales());
    }

    // What a language screen offers: the campaign's own texts, unmapped so nothing overwrites the text it was written in, then its tiers, follow-ups and prizes through the types they are always edited with (see CrowdfundingTranslator)
    /** @return list<FieldInterface> */
    private function translationFields(Crowdfunding $crowdfunding, string $locale): array
    {
        $values = $this->crowdfundingTranslator->promptValues($crowdfunding, $locale);

        return [
            FormField::addFieldset(t('label.fieldset_this_language', ['%language%' => Locales::getName($locale, $locale)], 'crowdfunding'))
                ->setHelp(t('label.fieldset_this_language_help', [], 'crowdfunding')),
            TextField::new('title')
                ->setLabel(t('label.title', [], 'crowdfunding'))
                ->setRequired(false)
                ->setFormTypeOption('mapped', false)
                ->setFormTypeOption('data', $values['title']),
            TextareaField::new('description')
                ->setLabel(t('label.description', [], 'crowdfunding'))
                ->setRequired(false)
                ->setFormTypeOption('mapped', false)
                ->setFormTypeOption('data', $values['description'])
                // Opt-in marker read by the block form theme, which is what puts Donovan under a plain textarea
                ->setFormTypeOption('attr', ['data-ai-rephrase' => true]),
            TextareaField::new('authorPresentation')
                ->setLabel(t('label.author_presentation', [], 'crowdfunding'))
                ->setRequired(false)
                ->setFormTypeOption('mapped', false)
                ->setFormTypeOption('data', $values['authorPresentation'])
                ->setFormTypeOption('attr', ['data-ai-rephrase' => true]),
            FormField::addFieldset(t('label.counterparts', [], 'crowdfunding')),
            $this->translationCollection('counterparts', CrowdfundingCounterpartType::class, $locale),
            FormField::addFieldset(t('label.news', [], 'crowdfunding')),
            $this->translationCollection('news', CrowdfundingNewsType::class, $locale),
            FormField::addFieldset(t('label.lottery', [], 'crowdfunding')),
            $this->translationCollection('lotteries', LotteryType::class, $locale),
        ];
    }

    // One of the campaign's collections on its language screen: every row it already holds, through the type it is always edited with, and neither "+" nor bin - a tier taken away there would be taken away from every language at once
    private function translationCollection(string $property, string $entryType, string $locale): CollectionField
    {
        return CollectionField::new($property)
            ->setLabel(false)
            ->setEntryType($entryType)
            ->allowAdd(false)
            ->allowDelete(false)
            ->setFormTypeOption('by_reference', false)
            ->setFormTypeOption('entry_options.translation_locale', $locale);
    }

    // What the language tabs at the top of the edit screen need, and nothing at all where the campaign is not saved yet or the site declares a single language
    #[\Override]
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $responseParameters = parent::configureResponseParameters($responseParameters);

        $crowdfunding = $this->getContext()?->getEntity()->getInstance();
        $id = $crowdfunding instanceof Crowdfunding ? $crowdfunding->getId() : null;
        if (null !== $id && $this->crowdfundingTranslator->isActive()) {
            $this->contentLocaleScreen->addParameters($responseParameters, self::class, $id, $this->crowdfundingTranslator->getTranslatableLocales(), $this->contentLocale());
        }

        return $responseParameters;
    }

    // What a language screen wrote, handed over to be stored on the flush that saves the campaign and never before it (see ContentLocaleScreen::stageOnSubmit)
    #[\Override]
    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createEditFormBuilder($entityDto, $formOptions, $context);
        $contentLocale = $this->contentLocale();

        $this->contentLocaleScreen->stageOnSubmit(
            $formBuilder,
            $contentLocale,
            CrowdfundingTranslator::CAMPAIGN_FIELDS,
            function (object $entity, array $values) use ($contentLocale): void {
                if ($entity instanceof Crowdfunding && null !== $contentLocale) {
                    $this->crowdfundingTranslator->stage($entity, $contentLocale, $values);
                }
            }
        );

        return $formBuilder;
    }
}
