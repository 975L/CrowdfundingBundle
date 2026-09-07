<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Email;

use c975L\CrowdfundingBundle\Email\CrowdfundingEmailTemplateProvider;
use c975L\UiBundle\Contract\EmailTemplateProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// The declaration three things read: the command that seeds the rows, the renderer that falls back on it when a row was deleted, and the health check that reports what a site is missing
class CrowdfundingEmailTemplateProviderTest extends TestCase
{
    private const array TEMPLATES = ['crowdfunding_contribution', 'lottery_tickets', 'lottery_ticket_winner'];

    public function testItDeclaresTheThreeEmailsACampaignSends(): void
    {
        $this->assertSame(self::TEMPLATES, array_keys($this->createProvider()->getEmailTemplates()));
    }

    // A locale missing from the declaration is a site seeding nothing for it, and a visitor reading the default language
    public function testEachEmailIsDeclaredInTheThreeShippedLocales(): void
    {
        foreach ($this->createProvider()->getEmailTemplates() as $name => $locales) {
            $this->assertSame(['fr', 'en', 'es'], array_keys($locales), sprintf('"%s" is not declared in every locale.', $name));
        }
    }

    // Listed rather than read from kernel.enabled_locales: the translator answers every locale by falling back, so iterating a site's languages would seed a Spanish row holding French sentences
    public function testEachLocaleIsTranslatedInItsOwnLanguage(): void
    {
        $templates = $this->createProvider()->getEmailTemplates();

        $this->assertSame('label.contribution_thanks@fr', $templates['crowdfunding_contribution']['fr'][0][3]);
        $this->assertSame('label.contribution_thanks@es', $templates['crowdfunding_contribution']['es'][0][3]);
    }

    // A slot naming a file that is not shipped renders nothing, and the fragment the code computes never reaches the reader
    public function testEverySlotItNamesIsShipped(): void
    {
        foreach ($this->createProvider()->getEmailTemplates() as $name => $locales) {
            foreach ($locales['fr'] as $block) {
                if ('slot' !== $block[0]) {
                    continue;
                }

                $this->assertFileExists(\dirname(__DIR__, 2) . '/templates/emails/slots/' . $block[4] . '.html.twig', sprintf('"%s" names the "%s" slot, which the bundle does not ship.', $name, $block[4]));
            }
        }
    }

    // Six-element tuples, the shape FormSeeder::ensureEmailTemplate() seeds from
    public function testEveryBlockIsATupleOfTheExpectedShape(): void
    {
        foreach ($this->createProvider()->getEmailTemplates() as $locales) {
            foreach ($locales as $blocks) {
                foreach ($blocks as $block) {
                    $this->assertCount(6, $block);
                    $this->assertContains($block[0], ['text', 'slot']);
                }
            }
        }
    }

    // The three Twig bodies were deleted with this conversion: kept as a fallback, the two copies of the same sentence drift apart, which is what happened to PaymentBundle in a single day
    public function testTheOldTwigBodiesAreGone(): void
    {
        foreach (self::TEMPLATES as $name) {
            $this->assertFileDoesNotExist(\dirname(__DIR__, 2) . '/templates/emails/' . $name . '.html.twig');
        }

        $this->assertFileDoesNotExist(\dirname(__DIR__, 2) . '/templates/emails/layout.html.twig', 'The email shell is UiBundle\'s from now on, renderNamed() dressing the body already.');
    }

    // Found by EmailTemplateProviderPass through the contract, not by a tag written by hand
    public function testItImplementsTheUiContract(): void
    {
        $this->assertInstanceOf(EmailTemplateProviderInterface::class, $this->createProvider());
    }

    private function createProvider(): CrowdfundingEmailTemplateProvider
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string => $id . '@' . $locale
        );

        return new CrowdfundingEmailTemplateProvider($translator);
    }
}
