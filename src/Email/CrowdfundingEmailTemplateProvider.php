<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Email;

use c975L\UiBundle\Contract\EmailTemplateProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The three e-mails a campaign sends, as templates an admin composes rather than Twig files nobody but a developer
 * can touch. This bundle was the last of the ecosystem still building them from bodies of its own.
 *
 * Each is cut along the seam that matters: the sentences, which an admin rewrites, and the fragments the code
 * computes - the counterparts taken, the ticket numbers drawn, the prize won - which appear here as slot blocks
 * named after templates/emails/slots/ and hold whatever CrowdfundingEmailFactory rendered into them. A slot that
 * comes out empty renders nothing, so a contribution shipping nothing shows no gap where the address would be.
 *
 * Only the structure is written here. Every sentence is read from the translation catalogue, which is the one
 * place this bundle's default wording lives: what is seeded into a site's EmailTemplate row on the first
 * c975l:ui:email-templates:ensure, what EmailTemplateRenderer falls back on if that row is ever deleted, and what
 * a translator edits for a language the bundle does not ship yet.
 */
class CrowdfundingEmailTemplateProvider implements EmailTemplateProviderInterface
{
    // The languages this bundle ships a catalogue for. Listed rather than read from kernel.enabled_locales: the translator answers every locale, falling back on the default one, so iterating the site's languages would seed a Spanish row holding French sentences
    private const array LOCALES = ['fr', 'en', 'es'];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getEmailTemplates(): array
    {
        $templates = [];
        foreach (self::LOCALES as $locale) {
            foreach ($this->structure($locale) as $name => $blocks) {
                $templates[$name][$locale] = $blocks;
            }
        }

        return $templates;
    }

    /**
     * @return array<string, list<array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string}>>
     */
    private function structure(string $locale): array
    {
        return [
            // Sent once the payment is confirmed, to whoever paid
            'crowdfunding_contribution' => [
                $this->text('label.contribution_thanks', $locale),
                $this->slot('order_link'),
                $this->text('label.contribution_counterparts_reminder', $locale),
                $this->slot('counterparts'),
                $this->slot('delivery'),
            ],
            // Every ticket the contribution earned, in one email: a counterpart entitling to ten of them would otherwise fill the contributor's inbox
            'lottery_tickets' => [
                $this->text('text.lottery_reminder', $locale),
                $this->slot('tickets'),
                $this->text('label.good_luck', $locale),
            ],
            // Sent to the holder of the drawn ticket, from the draw itself rather than from any request of theirs
            'lottery_ticket_winner' => [
                $this->text('text.lottery_ticket_winner', $locale),
                $this->slot('prize'),
                $this->text('label.follow_up', $locale),
            ],
        ];
    }

    /** @return array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string} */
    private function text(string $key, string $locale): array
    {
        return ['text', null, null, $this->translator->trans($key, [], 'crowdfunding', $locale), null, null];
    }

    /** @return array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string} */
    private function slot(string $name): array
    {
        return ['slot', null, null, null, $name, null];
    }
}
