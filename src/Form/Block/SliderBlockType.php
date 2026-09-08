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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SliderBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Milliseconds between two slides, 0 leaving the slider on its arrows alone - the very field UiBundle's own "slider" offers
            ->add('duration', IntegerType::class, [
                'label' => 'label.block_slide_duration',
                'required' => false,
                'attr' => ['min' => 0, 'step' => 500],
            ])
            // The shape the slides are cut to, "free" keeping each media's own - the three UiBundle's slider accepts
            ->add('ratio', ChoiceType::class, [
                'label' => 'label.block_ratio',
                'choices' => [
                    'label.block_ratio_free' => 'free',
                    'label.block_ratio_square' => 'square',
                    'label.block_ratio_landscape' => 'landscape',
                ],
                'required' => false,
                'placeholder' => false,
            ])
        ;
    }

    // BlockType translates the embedded data form in the "ui" domain: without this, every label above would be looked up there and rendered raw
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'crowdfunding']);
    }
}
