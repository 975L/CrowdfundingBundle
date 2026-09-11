<?php

namespace c975L\CrowdfundingBundle\Form;

use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\CrowdfundingBundle\Form\Util\CrowdfundingTranslationBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CrowdfundingNewsType extends AbstractType
{
    // Null only where a type is built by hand, a test reading its fields: the container always hands it over
    public function __construct(
        private readonly ?CrowdfundingTranslationBuilder $translationBuilder = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // A language screen offers the follow-up's two texts and nothing else
        $locale = $options['translation_locale'] ?? null;
        if (null !== $locale) {
            $this->translationBuilder?->build($builder, $locale, [
                'title' => [TextType::class, 'label.title'],
                'content' => [TextareaType::class, 'label.content'],
            ]);

            return;
        }

        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'translation_domain' => 'crowdfunding',
            ])
            ->add('content', TextareaType::class, [
                'label' => 'label.content',
                'translation_domain' => 'crowdfunding',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CrowdfundingNews::class,
            'translation_domain' => 'crowdfunding',
            // A language code opens the row's language screen: its texts alone, unmapped (see CrowdfundingTranslationBuilder)
            'translation_locale' => null,
        ]);
        $resolver->setAllowedTypes('translation_locale', ['null', 'string']);
    }
}
