<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Form\Util;

use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\CrowdfundingBundle\Entity\LotteryPrize;
use c975L\CrowdfundingBundle\Service\CrowdfundingTranslator;
use c975L\UiBundle\Form\Util\CollectionReconciler;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormTypeInterface;

// The language screen of one row of a campaign's collections - a tier, a follow-up, a prize - shared by the three types editing them as UiBundle's FormTranslationBuilder is: the row's texts alone, unmapped, every row coming back since the screen neither adds nor removes one
class CrowdfundingTranslationBuilder
{
    public function __construct(
        private readonly CrowdfundingTranslator $crowdfundingTranslator,
    ) {
    }

    // Offers the row's texts once its data is set, and stages what was typed once the row is submitted
    /** @param array<string, array{0: class-string<FormTypeInterface>, 1: string}> $fields the row's translatable property => its type and its own label key */
    public function build(FormBuilderInterface $builder, string $locale, array $fields): void
    {
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (PreSetDataEvent $event) use ($locale, $fields): void {
                $row = $this->asRow($event->getData());

                // Matched back by id, the way the composing screen matches its rows
                CollectionReconciler::addIdField($event->getForm(), $row?->getId());

                if (null === $row) {
                    return;
                }

                $values = $this->crowdfundingTranslator->promptValues($row, $locale);

                foreach ($fields as $property => [$type, $label]) {
                    $event->getForm()->add($property, $type, [
                        'label' => $label,
                        'required' => false,
                        'mapped' => false,
                        'data' => $values[$property] ?? null,
                        // Opt-in marker read by the block form theme, which is what puts Donovan under a plain textarea
                        'attr' => TextareaType::class === $type ? ['rows' => 3, 'data-ai-rephrase' => true] : [],
                    ]);
                }
            }
        );

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (PostSubmitEvent $event) use ($locale, $fields): void {
                $row = $this->asRow($event->getData());
                if (null === $row) {
                    return;
                }

                $values = [];
                foreach (array_keys($fields) as $property) {
                    if ($event->getForm()->has($property)) {
                        $values[$property] = $event->getForm()->get($property)->getData();
                    }
                }

                // Staged rather than stored: this fires before the root form is validated, so a write here would keep a translation of a save about to be refused (see ContentTranslator::stage)
                $this->crowdfundingTranslator->stage($row, $locale, $values);
            }
        );
    }

    // The rows this builds a language screen for, and null for anything else - an entry not bound to a row yet
    private function asRow(mixed $row): CrowdfundingCounterpart | CrowdfundingNews | LotteryPrize | null
    {
        return $row instanceof CrowdfundingCounterpart || $row instanceof CrowdfundingNews || $row instanceof LotteryPrize ? $row : null;
    }
}
