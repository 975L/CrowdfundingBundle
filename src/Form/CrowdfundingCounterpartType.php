<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Form;

use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CrowdfundingCounterpartType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => true,
            ])
            ->add('slug', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'readonly' => true,
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'label.description',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                ],
            ])
            ->add('lotteryTickets', ChoiceType::class, [
                'label' => 'label.lottery_tickets',
                'required' => false,
                'choices' => [
                    '0 ticket' => 0,
                    '1 ticket' => 1,
                    '2 tickets' => 2,
                    '3 tickets' => 3,
                    '4 tickets' => 4,
                    '5 tickets' => 5,
                    '6 tickets' => 6,
                    '7 tickets' => 7,
                    '8 tickets' => 8,
                    '9 tickets' => 9,
                    '10 tickets' => 10,
                ],
                'placeholder' => 'Choisir un nombre de tickets',
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('requiresShipping', CheckboxType::class, [
                'label' => 'label.requires_shipping',
                'required' => false,
            ])
            ->add('price', MoneyType::class, [
                'required' => true,
                'label' => 'label.price',
                'divisor' => 100,
            ])
            ->add('currency', TextType::class, [
                'required' => true,
                'empty_data' => 'eur',
                'label' => 'label.currency',
                'attr' => [
                    'value' => 'eur',
                ],
            ])
            ->add('expectedDelivery', TextType::class, [
                'required' => true,
                'label' => 'label.expected_delivery',
            ])
            ->add('limitedQuantity', IntegerType::class, [
                'label' => 'label.limited_quantity',
                'required' => false,
                'row_attr' => ['data-counterpart-quantity' => '1'],
            ])
            ->add('orderedQuantity', IntegerType::class, [
                'label' => 'label.ordered_quantity',
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'readonly' => true,
                ],
            ])
            ->add('media', CrowdfundingCounterpartMediaType::class, [
                'label' => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CrowdfundingCounterpart::class,
            'translation_domain' => 'crowdfunding',
        ]);
    }
}
