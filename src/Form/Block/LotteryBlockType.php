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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LotteryBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
            // A sentence above the draws, the prizes themselves being read from the campaign's own lotteries - nothing about a prize is typed here, or it would drift from the row the draw is made on
            ->add('intro', TextareaType::class, [
                'label' => 'label.block_intro',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('showTicketsCount', CheckboxType::class, [
                'label' => 'label.block_show_tickets_count',
                'required' => false,
            ])
        ;
    }

    // BlockType translates the embedded data form in the "ui" domain: without this, every label above would be looked up there and rendered raw
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'crowdfunding']);
    }
}
