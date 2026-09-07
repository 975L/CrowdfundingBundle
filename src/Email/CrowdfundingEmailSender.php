<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Email;

use c975L\UiBundle\Service\EmailService;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The one place a campaign email is written in the contributor's language rather than in whoever's language happens to be current: a lottery email goes out from a draw an admin clicked, and the contribution is the only thing that remembers what language it was made in
class CrowdfundingEmailSender
{
    public function __construct(
        private readonly CrowdfundingEmailFactory $crowdfundingEmailFactory,
        private readonly EmailService $emailService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Sends one campaign email, and says whether it left.
     *
     * The whole build and send happens inside the language window on purpose: the subject and the fragments are
     * translated as they are built, so nothing is left to be rendered later by a worker that would know nothing of
     * this contributor.
     *
     * @param array<string, mixed> $context what the slots are rendered from
     */
    public function send(string $template, string $subjectKey, string $to, array $context = [], ?string $locale = null, string $subjectSuffix = ''): bool
    {
        $previous = $this->useLocale($locale);

        try {
            return $this->emailService->send($this->crowdfundingEmailFactory->create($template, $subjectKey, $to, $context, $locale, $subjectSuffix));
        } finally {
            $this->useLocale($previous);
        }
    }

    public function getLastError(): ?string
    {
        return $this->emailService->getLastError();
    }

    // Switches the translator over and hands back what it was on, so a French contribution following an English one is not written in English
    private function useLocale(?string $locale): ?string
    {
        if (null === $locale || '' === $locale || !$this->translator instanceof LocaleAwareInterface) {
            return null;
        }

        $previous = $this->translator->getLocale();
        $this->translator->setLocale($locale);

        return $previous;
    }
}
