<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Form\Block;

use c975L\UiBundle\Form\Block\HasAnchorFieldTrait;
use c975L\UiBundle\Form\Block\HasBackgroundFieldTrait;
use c975L\UiBundle\Form\TrixEditorType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The data sub-form of the "crowdfunding_campaigns" kind - the only kind of this bundle placed on an ordinary page rather than on a campaign's own, hence a head of its own: anchor, eyebrow, title, paragraph, link and colored flat, the very fields BookBundle's listings offer. The campaigns themselves are never stored here, they are read live at render time (see CrowdfundingBlockExtension)
class CampaignsBlockType extends AbstractType
{
    use HasAnchorFieldTrait;
    use HasBackgroundFieldTrait;

    public function __construct(
        private readonly BlockAnchorSlugger $anchorSlugger,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addAnchorField($builder, $this->anchorSlugger);

        $builder
            ->add('eyebrow', TextType::class, [
                'label' => 'label.block_eyebrow',
                'required' => false,
            ])
            // Optional, like the eyebrow above it: a listing typed with neither renders as a bare row of cards
            ->add('title', TextType::class, [
                'label' => 'label.block_title',
                'required' => false,
            ])
            ->add('content', TrixEditorType::class, [
                'label' => 'label.block_content',
                'required' => false,
            ])
            // The link closing the head, against its far edge - what a listing showing a cut of the campaigns points at their whole page. Both needed, either alone printing a broken link
            ->add('linkLabel', TextType::class, [
                'label' => 'label.block_link_label',
                'required' => false,
            ])
            ->add('linkUrl', TextType::class, [
                'label' => 'label.block_link_url',
                'help' => 'label.block_link_url_help',
                'required' => false,
            ])
            // Empty is every visible campaign, which is what the /crowdfunding page itself shows
            ->add('max', IntegerType::class, [
                'label' => 'label.block_max_campaigns',
                'required' => false,
                'attr' => ['min' => 1],
            ])
        ;

        $this->addBackgroundField($builder);
    }

    // BlockType translates the embedded data form in the "ui" domain: without this, every label above would be looked up there and rendered raw
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'crowdfunding',
        ]);
    }
}
