<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Form\Block;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CounterpartsBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
            // The counterpart's own slug rather than a list of them: the form is built without knowing which campaign the block will be placed on, a list would name another campaign's tiers
            ->add('highlightedSlug', TextType::class, [
                'label' => 'label.block_highlighted_counterpart',
                'help' => 'label.block_highlighted_counterpart_help',
                'required' => false,
            ])
            // Under how many remaining a tier says so: factual scarcity, read off the quantity the campaign actually declared
            ->add('lowStockThreshold', IntegerType::class, [
                'label' => 'label.block_low_stock_threshold',
                'required' => false,
                'attr' => ['min' => 0],
            ])
            ->add('columns', ChoiceType::class, [
                'label' => 'label.block_columns',
                'choices' => ['2' => 2, '3' => 3, '4' => 4],
                'required' => false,
                'placeholder' => false,
                'choice_translation_domain' => false,
            ])
        ;
    }

    // BlockType translates the embedded data form in the "ui" domain: without this, every label above would be looked up there and rendered raw
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'crowdfunding']);
    }
}
