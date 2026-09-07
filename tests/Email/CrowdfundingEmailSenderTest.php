<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Email;

use c975L\CrowdfundingBundle\Email\CrowdfundingEmailFactory;
use c975L\CrowdfundingBundle\Email\CrowdfundingEmailSender;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;

// A lottery email goes out from a draw an admin clicked, not from a request of the contributor's: the language it is written in has to be fetched from the contribution rather than from whoever is on the site
class CrowdfundingEmailSenderTest extends TestCase
{
    public function testItBuildsTheEmailInTheContributorLanguage(): void
    {
        $translator = new Translator('fr');
        $seen = null;

        $sender = $this->createSender($translator, function () use ($translator, &$seen): EmailSendRequest {
            $seen = $translator->getLocale();

            return new EmailSendRequest(subject: 'x', context: []);
        });

        $sender->send('lottery_tickets', 'label.lottery_tickets', 'camille@example.com', [], 'es');

        $this->assertSame('es', $seen);
    }

    // A French contribution following a Spanish one is not written in Spanish: whatever the translator was on is put back, even when the sending throws
    public function testItPutsTheTranslatorBackWhereItWas(): void
    {
        $translator = new Translator('fr');

        $sender = $this->createSender($translator, static fn (): EmailSendRequest => throw new \LogicException('nothing to send'));

        try {
            $sender->send('lottery_tickets', 'label.lottery_tickets', 'camille@example.com', [], 'es');
        } catch (\LogicException) {
        }

        $this->assertSame('fr', $translator->getLocale());
    }

    // A contribution made before the column existed carries no language, and the email goes out in the site's own rather than in none
    public function testAContributionWithoutALanguageLeavesTheTranslatorAlone(): void
    {
        $translator = new Translator('fr');
        $seen = null;

        $sender = $this->createSender($translator, function () use ($translator, &$seen): EmailSendRequest {
            $seen = $translator->getLocale();

            return new EmailSendRequest(subject: 'x', context: []);
        });

        $sender->send('lottery_tickets', 'label.lottery_tickets', 'camille@example.com', [], null);

        $this->assertSame('fr', $seen);
    }

    // The error is read off UiBundle rather than thrown, so a mail server refusing one email does not take the payment confirmation down with it
    public function testItHandsBackTheErrorUiBundleRecorded(): void
    {
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('getLastError')->willReturn('Connection could not be established');

        $sender = new CrowdfundingEmailSender($this->createStub(CrowdfundingEmailFactory::class), $emailService, new Translator('fr'));

        $this->assertSame('Connection could not be established', $sender->getLastError());
    }

    private function createSender(Translator $translator, callable $create): CrowdfundingEmailSender
    {
        $factory = $this->createStub(CrowdfundingEmailFactory::class);
        $factory->method('create')->willReturnCallback($create);

        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturn(true);

        return new CrowdfundingEmailSender($factory, $emailService, $translator);
    }
}
