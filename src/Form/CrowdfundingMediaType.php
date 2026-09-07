<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Form;

use c975L\CrowdfundingBundle\Entity\CrowdfundingMedia;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Vich\UploaderBundle\Form\Type\VichImageType;

class CrowdfundingMediaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('position', IntegerType::class, [
                'label' => 'label.position',
            ])
            ->add('file', VichImageType::class, [
                'label' => 'Media',
                'required' => false,
                'allow_delete' => true,
                'download_uri' => true,
                'asset_helper' => true,
                'constraints' => [
                    new File(maxSize: '20M'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CrowdfundingMedia::class,
            'translation_domain' => 'crowdfunding',
        ]);
    }
}
