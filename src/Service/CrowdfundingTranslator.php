<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\CrowdfundingBundle\Entity\LotteryPrize;
use c975L\UiBundle\Service\ContentTranslator;

// What a campaign, its tiers, its follow-ups and its prizes say in another language, living beside the one row they belong to as a page's translations do (see SiteBundle's PageTranslator) - only what a visitor reads, never a slug, an amount, a name or a ticket number, and nothing at all on a single-language site
class CrowdfundingTranslator
{
    // The vocabulary this bundle's rows are named with, the way Page and Block name theirs - a plain string, no foreign key ever pointing at it (see UiBundle's Translation)
    public const string OWNER_CAMPAIGN = 'crowdfunding_campaign';

    public const string OWNER_COUNTERPART = 'crowdfunding_counterpart';

    public const string OWNER_NEWS = 'crowdfunding_news';

    public const string OWNER_PRIZE = 'crowdfunding_prize';

    // What a translation may cover of a campaign: its name, the story it tells, and what its author says of themselves
    public const array CAMPAIGN_FIELDS = ['title', 'description', 'authorPresentation'];

    // A tier's own name and description, and when it says it will be delivered - a sentence ("Mars 2027"), not a date
    public const array COUNTERPART_FIELDS = ['title', 'description', 'expectedDelivery'];

    public const array NEWS_FIELDS = ['title', 'content'];

    public const array PRIZE_FIELDS = ['title', 'description'];

    public function __construct(
        private readonly ContentTranslator $contentTranslator,
        private readonly SiteLocales $siteLocales,
    ) {
    }

    // False on a site declaring a single language, where nothing here reads or writes anything
    public function isActive(): bool
    {
        return $this->contentTranslator->isActive();
    }

    // The languages a row may be written in besides the one it was written in
    /** @return list<string> */
    public function getTranslatableLocales(): array
    {
        return $this->contentTranslator->getTranslatableLocales();
    }

    // Lays the language being rendered over each row's own texts, for this render only - called by whatever renders them rather than on postLoad, the back office having to go on showing the text a row was written in
    /** @param iterable<Crowdfunding|CrowdfundingCounterpart|CrowdfundingNews|LotteryPrize> $rows */
    public function apply(iterable $rows, ?string $locale = null): void
    {
        // Tested before the collection is touched: on a single-language site the proxy behind it is never initialised, and a listing costs no query at all here
        if (!$this->contentTranslator->isActive()) {
            return;
        }

        $rows = $rows instanceof \Traversable ? iterator_to_array($rows) : $rows;

        if ([] === $rows) {
            return;
        }

        $this->preload($rows, $locale);

        foreach ($rows as $row) {
            $id = $row->getId();
            if (null === $id) {
                continue;
            }

            // Given nothing to lay over, translate() hands back the translated fields alone - an untranslated one is absent rather than null, which is what makes the getters fall back on the text the row was written in
            $row->setTranslated($this->contentTranslator->translate($this->owner($row), $id, [], $this->fields($row), $locale));
        }
    }

    // Reads ahead every language of a whole set of rows, so asking translatedLocales() of each of them costs one query per language rather than one per row
    /** @param iterable<Crowdfunding|CrowdfundingCounterpart|CrowdfundingNews|LotteryPrize> $rows */
    public function preloadEveryLanguage(iterable $rows): void
    {
        foreach ($this->getTranslatableLocales() as $locale) {
            $this->preload($rows, $locale);
        }
    }

    // Reads ahead a whole set of rows, so a page of a dozen tiers costs one query rather than a dozen
    /** @param iterable<Crowdfunding|CrowdfundingCounterpart|CrowdfundingNews|LotteryPrize> $rows */
    public function preload(iterable $rows, ?string $locale = null): void
    {
        if (!$this->contentTranslator->isActive()) {
            return;
        }

        // Grouped by kind: each is one query of its own, a campaign and a tier being two owner types
        $ids = [];
        foreach ($rows as $row) {
            $id = $row->getId();
            if (null !== $id) {
                $ids[$this->owner($row)][] = $id;
            }
        }

        foreach ($ids as $owner => $ownerIds) {
            $this->contentTranslator->preload($owner, $ownerIds, $locale);
        }
    }

    // The languages this row really exists in, its own included - the ones an "hreflang" group may name: a row says something in a language once its own name does, a description translated under an untranslated name being half a sheet (see preloadEveryLanguage for a whole set)
    /** @return list<string> */
    public function translatedLocales(Crowdfunding | CrowdfundingCounterpart | CrowdfundingNews | LotteryPrize $row): array
    {
        $id = $row->getId();
        $locales = [$this->siteLocales->getDefaultLocale()];
        if (null === $id) {
            return $locales;
        }

        $name = $this->fields($row)[0];

        foreach ($this->getTranslatableLocales() as $locale) {
            $written = $this->contentTranslator->values($this->owner($row), $id, $locale)[$name] ?? null;
            if (null !== $written && '' !== $written) {
                $locales[] = $locale;
            }
        }

        return $locales;
    }

    // Every language this row has been given, for the screen that writes them
    /** @return array<string, array<string, string|null>> locale => field => value */
    public function all(Crowdfunding | CrowdfundingCounterpart | CrowdfundingNews | LotteryPrize $row): array
    {
        $id = $row->getId();

        return null === $id ? [] : $this->contentTranslator->all($this->owner($row), $id);
    }

    // What a language screen offers for each translatable text: what that language already says, or the source text between brackets where it says nothing yet
    /** @return array<string, string|null> field => value */
    public function promptValues(Crowdfunding | CrowdfundingCounterpart | CrowdfundingNews | LotteryPrize $row, string $locale): array
    {
        $written = $this->all($row)[$locale] ?? [];

        $values = [];
        foreach ($this->fields($row) as $field) {
            $translated = $written[$field] ?? null;
            $values[$field] = null !== $translated && '' !== $translated
                ? $translated
                : ContentTranslator::prompt($row->getUntranslated($field));
        }

        return $values;
    }

    // Hands what a language screen wrote over to be stored on the flush that saves the row, a field left holding the bracketed source counting as nothing written (see ContentTranslator::stage)
    /** @param array<string, string|null> $values field => value */
    public function stage(Crowdfunding | CrowdfundingCounterpart | CrowdfundingNews | LotteryPrize $row, string $locale, array $values): void
    {
        $id = $row->getId();
        if (null === $id) {
            return;
        }

        $staged = [];
        foreach ($this->fields($row) as $field) {
            if (!array_key_exists($field, $values)) {
                continue;
            }

            $staged[$field] = ContentTranslator::untouched($values[$field], $row->getUntranslated($field)) ? null : $values[$field];
        }

        if ([] !== $staged) {
            $this->contentTranslator->stage($this->owner($row), $id, $locale, $staged);
        }
    }

    // Writes a translation straight away rather than staging it - what a seeder or a bulk pass does, having no form to wait for
    /** @param array<string, string|null> $values field => value */
    public function store(Crowdfunding | CrowdfundingCounterpart | CrowdfundingNews | LotteryPrize $row, string $locale, array $values): void
    {
        $id = $row->getId();
        if (null !== $id) {
            $this->contentTranslator->store($this->owner($row), $id, $locale, $values);
        }
    }

    // What a row's translations are filed under
    public function owner(Crowdfunding | CrowdfundingCounterpart | CrowdfundingNews | LotteryPrize $row): string
    {
        return match (true) {
            $row instanceof Crowdfunding => self::OWNER_CAMPAIGN,
            $row instanceof CrowdfundingCounterpart => self::OWNER_COUNTERPART,
            $row instanceof CrowdfundingNews => self::OWNER_NEWS,
            default => self::OWNER_PRIZE,
        };
    }

    // The texts a row of that kind may be translated in, its name first
    /** @return list<string> */
    public function fields(Crowdfunding | CrowdfundingCounterpart | CrowdfundingNews | LotteryPrize $row): array
    {
        return match (true) {
            $row instanceof Crowdfunding => self::CAMPAIGN_FIELDS,
            $row instanceof CrowdfundingCounterpart => self::COUNTERPART_FIELDS,
            $row instanceof CrowdfundingNews => self::NEWS_FIELDS,
            default => self::PRIZE_FIELDS,
        };
    }
}
