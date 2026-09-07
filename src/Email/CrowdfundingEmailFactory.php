<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Email;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

// Builds the EmailSendRequest the three campaign emails share - same envelope, same subject shape - so the sending itself is left to UiBundle's EmailService. Deliberately not an interface: it carries no extension point, unlike the EmailServiceInterface it replaces, the composing having moved to the back office
class CrowdfundingEmailFactory
{
    /**
     * The fragments a campaign email can hold, each rendered by a template of templates/emails/slots/ and each
     * responsible for coming out empty when it has nothing to show - an EmailBlock naming an empty slot renders
     * nothing at all, which is what keeps a contribution shipping nothing from printing a blank address.
     */
    private const array SLOTS = [
        'order_link',
        'counterparts',
        'delivery',
        'tickets',
        'prize',
    ];

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly EmailTemplateRenderer $emailTemplateRenderer,
        private readonly TranslatorInterface $translator,
        private readonly Environment $twig,
    ) {
    }

    /**
     * Builds the request for one campaign email.
     *
     * Always composed from the EmailTemplate of that name: the site's own row when it has one, the wording
     * CrowdfundingEmailTemplateProvider declares when it does not. This bundle ships no Twig body beside them any
     * more - a second copy of the same sentences is a second copy to keep in step.
     *
     * @param array<string, mixed> $context what the slots are rendered from
     *
     * @throws \LogicException when neither exists, which no installed CrowdfundingBundle can produce: the
     *                         declaration is this bundle's own, so its absence means a half-installed package and a
     *                         silent blank email is the worse of the two answers
     */
    public function create(string $template, string $subjectKey, string $to, array $context = [], ?string $locale = null, string $subjectSuffix = ''): EmailSendRequest
    {
        $html = $this->emailTemplateRenderer->renderNamed($template, $this->variables($context), $locale);

        if (null === $html) {
            throw new \LogicException(sprintf('No email template named "%s" is declared or stored, so this email has no body to send.', $template));
        }

        return new EmailSendRequest(
            subject: $this->buildSubject($subjectKey, $subjectSuffix, $locale),
            context: [],
            html: $html,
            from: $this->config('shop-email-from'),
            fromName: $this->config('shop-email-from-name'),
            to: $to,
            replyTo: $this->config('shop-email-reply-to'),
            replyToName: $this->config('shop-email-reply-to-name'),
            bcc: $this->config('shop-email-bcc'),
            wrapLayout: false,
        );
    }

    /**
     * What the composed template is given: the fragments its slot blocks stand in for, and the scalars its
     * "{{ key }}" placeholders resolve against.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, scalar|array<string, mixed>>
     */
    private function variables(array $context): array
    {
        $slots = [];
        foreach (self::SLOTS as $name) {
            $slots[$name] = trim($this->twig->render('@c975LCrowdfunding/emails/slots/' . $name . '.html.twig', $context));
        }

        return array_filter($context, is_scalar(...)) + ['slots' => $slots];
    }

    // "Boutique <nom du site> - <ce dont il s'agit>", the shape every email of the checkout has. "label.shop" is read in PaymentBundle's catalogue, the bundle that declares the "shop-name" key beside it
    private function buildSubject(string $subjectKey, string $suffix, ?string $locale): string
    {
        $subject = $this->translator->trans('label.shop', [], 'payment', $locale)
            . ' ' . $this->configService->get('shop-name')
            . ' - ' . $this->translator->trans($subjectKey, [], 'crowdfunding', $locale);

        return '' === $suffix ? $subject : $subject . ' - ' . $suffix;
    }

    // A key left blank comes back as null rather than as an empty string, so UiBundle falls back on the site-wide "email-*" address instead of building a broken one - trimmed, a key holding a space alone being blank too
    private function config(string $key): ?string
    {
        return trim((string) $this->configService->get($key)) ?: null;
    }
}
