<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Form;

use c975L\CrowdfundingBundle\Entity\Lottery;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LotteryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // A draw says nothing of its own in any language: its prizes do, offered through the type they are always edited with, and neither added nor removed there
        $locale = $options['translation_locale'] ?? null;
        if (null !== $locale) {
            $builder->add('prizes', CollectionType::class, [
                'entry_type' => LotteryPrizeType::class,
                'entry_options' => ['translation_locale' => $locale],
                'allow_add' => false,
                'allow_delete' => false,
                'by_reference' => false,
                'label' => 'label.prizes',
                'required' => false,
            ]);

            return;
        }

        $builder
            ->add('isActive', CheckboxType::class, [
                'label' => 'label.enable_lottery',
                'required' => false,
            ])
            ->add('identifier', TextType::class, [
                'label' => 'label.lottery_identifier',
                'required' => false,
                'attr' => [
                    'readonly' => true,
                ],
            ])
            ->add('drawDate', DateTimeType::class, [
                'label' => 'label.draw_date',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('videos', CollectionType::class, [
                'entry_type' => LotteryVideoType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => 'label.videos',
                'required' => false,
                // Nested in a CollectionField printing no id, so its own row carries the marker the draw's guided project points at
                'row_attr' => ['data-lottery-videos' => '1'],
            ])
            ->add('prizes', CollectionType::class, [
                'entry_type' => LotteryPrizeType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => 'label.prizes',
                'required' => false,
                'row_attr' => ['data-lottery-prizes' => '1'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lottery::class,
            'translation_domain' => 'crowdfunding',
            // A language code opens the row's language screen: its texts alone, unmapped (see CrowdfundingTranslationBuilder)
            'translation_locale' => null,
        ]);
        $resolver->setAllowedTypes('translation_locale', ['null', 'string']);
    }
}
