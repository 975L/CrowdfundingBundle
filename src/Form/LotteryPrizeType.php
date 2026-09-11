<?php

namespace c975L\CrowdfundingBundle\Form;

use c975L\CrowdfundingBundle\Entity\LotteryPrize;
use c975L\CrowdfundingBundle\Form\Util\CrowdfundingTranslationBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LotteryPrizeType extends AbstractType
{
    // Null only where a type is built by hand, a test reading its fields: the container always hands it over
    public function __construct(
        private readonly ?CrowdfundingTranslationBuilder $translationBuilder = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // A language screen offers what a language may change and nothing else: a prize's rank is the same in every language
        $locale = $options['translation_locale'] ?? null;
        if (null !== $locale) {
            $this->translationBuilder?->build($builder, $locale, [
                'title' => [TextType::class, 'label.title'],
                'description' => [TextareaType::class, 'label.description'],
            ]);

            return;
        }

        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'label.description',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('rank', ChoiceType::class, [
                'label' => 'label.prize_rank',
                'choices' => [
                    'Prize #1 (Grand Prize)' => 1,
                    'Prize #2' => 2,
                    'Prize #3' => 3,
                    'Prize #4' => 4,
                    'Prize #5' => 5,
                ],
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LotteryPrize::class,
            'translation_domain' => 'crowdfunding',
            // A language code opens the row's language screen: its texts alone, unmapped (see CrowdfundingTranslationBuilder)
            'translation_locale' => null,
        ]);
        $resolver->setAllowedTypes('translation_locale', ['null', 'string']);
    }
}
