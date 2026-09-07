<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Controller\Management;

use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Form\CrowdfundingCounterpartType;
use c975L\CrowdfundingBundle\Form\CrowdfundingMediaType;
use c975L\CrowdfundingBundle\Form\CrowdfundingVideoType;
use c975L\CrowdfundingBundle\Form\LotteryType;
use c975L\CrowdfundingBundle\Management\CrowdfundingBlockOwnerResolver;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class CrowdfundingCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly BlockMoveRowAttrBuilder $blockMoveRowAttrBuilder,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Crowdfunding::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $crowdfunding = $this->getContext()?->getEntity()->getInstance();

        return [
            IdField::new('id')
                ->setFormTypeOption('disabled', 'disabled'),
            TextField::new('title')
                ->setLabel(t('label.title', [], 'crowdfunding')),
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
                ->setLabel(t('label.begin_date', [], 'crowdfunding')),
            DateField::new('endDate')
                ->setLabel(t('label.end_date', [], 'crowdfunding')),
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

            // Media management
            FormField::addFieldset(t('label.media', [], 'crowdfunding'))
                ->hideOnIndex(),
            CollectionField::new('medias')
                ->hideOnIndex()
                ->setEntryType(CrowdfundingMediaType::class),
            CollectionField::new('videos')
                ->hideOnIndex()
                ->setEntryType(CrowdfundingVideoType::class),

            // Counterpart management
            FormField::addFieldset(t('label.counterparts', [], 'crowdfunding'))
                ->setHelp(t('text.items_management', [], 'crowdfunding'))
                ->hideOnIndex(),
            CollectionField::new('counterparts')
                ->hideOnIndex()
                ->setEntryType(CrowdfundingCounterpartType::class),

            // Lottery management
            FormField::addFieldset(t('label.lottery', [], 'crowdfunding'))
                ->hideOnIndex(),
            CollectionField::new('lotteries')
                ->hideOnIndex()
                ->setEntryType(LotteryType::class),

            // What an admin composes the rest of the page with, on top of the fields above
            FormField::addFieldset(t('label.blocks', [], 'crowdfunding'))
                ->hideOnIndex(),
            CollectionField::new('blocks')
                ->setLabel(false)
                ->hideOnIndex()
                // CollectionField's "col-md-8 col-xxl-7" default would leave a nested block editor working in 7/12 of the row
                ->setColumns('col-12')
                ->setEntryType(BlockType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('row_attr', $this->blockMoveRowAttrBuilder->build(CrowdfundingBlockOwnerResolver::TYPE_CROWDFUNDING, $crowdfunding instanceof Crowdfunding ? $crowdfunding->getId() : null)),

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

    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-admin');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.delete', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, Action::DETAIL, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.detail', [], 'EasyAdminBundle'),
            ))
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            ->setPermission(Action::DETAIL, $role)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-admin'))
            ->setDefaultSort(['endDate' => 'DESC'])
            ->overrideTemplate('crud/index', '@c975LCrowdfunding/management/crowdfunding_crud_index.html.twig')
        ;
    }
}
