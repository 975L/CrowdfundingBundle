<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Email;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Email\CrowdfundingEmailFactory;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class CrowdfundingEmailFactoryTest extends TestCase
{
    // The body is what the composed template rendered - the site's own row, or the wording the provider declares - and never a Twig file of this bundle any more
    public function testItSendsWhatTheComposedTemplateRendered(): void
    {
        $request = $this->createFactory()->create('lottery_tickets', 'label.lottery_tickets', 'camille@example.com');

        $this->assertSame('<p>rendu</p>', $request->html);
        $this->assertNull($request->template);
        $this->assertFalse($request->wrapLayout, 'renderNamed() dresses the body already, so wrapping it again would nest two shells.');
    }

    // "Boutique <nom du site> - <ce dont il s'agit> - <identifiant>", the shape every email of the checkout has
    public function testTheSubjectCarriesTheShopPrefixAndTheSuffix(): void
    {
        $request = $this->createFactory()->create('lottery_tickets', 'label.lottery_tickets', 'camille@example.com', [], 'fr', 'BCD-1A2B-3C4D');

        $this->assertSame('label.shop Ma boutique - label.lottery_tickets - BCD-1A2B-3C4D', $request->subject);
    }

    public function testTheSubjectDropsTheDashWhenThereIsNothingToAppend(): void
    {
        $request = $this->createFactory()->create('lottery_tickets', 'label.lottery_tickets', 'camille@example.com');

        $this->assertSame('label.shop Ma boutique - label.lottery_tickets', $request->subject);
    }

    // The six shop-email-* keys are PaymentBundle's, this bundle reading the same senders as the rest of the checkout
    public function testTheEnvelopeIsTheShopOwn(): void
    {
        $request = $this->createFactory()->create('lottery_tickets', 'label.lottery_tickets', 'camille@example.com');

        $this->assertSame('boutique@example.com', $request->from);
        $this->assertSame('La boutique', $request->fromName);
        $this->assertSame('archives@example.com', $request->bcc);
        $this->assertSame('contact@example.com', $request->replyTo);
        $this->assertSame('camille@example.com', $request->to);
    }

    // A key left blank comes back null rather than as an empty string, so UiBundle falls back on the site-wide "email-*" address instead of building a broken one - trimmed, a key holding a space alone being blank too
    public function testABlankAddressIsHandedOverAsNullForUiBundleToFallBackOn(): void
    {
        $request = $this->createFactory(['shop-name' => 'Ma boutique', 'shop-email-bcc' => '   '])->create('lottery_tickets', 'label.lottery_tickets', 'camille@example.com');

        $this->assertNull($request->from);
        $this->assertNull($request->bcc);
    }

    // Each slot is rendered from its own template and handed over under its name: a block naming one that came out empty renders nothing, so a contribution shipping nothing shows no gap
    public function testEverySlotIsRenderedAndHandedToTheTemplate(): void
    {
        $rendered = [];
        $this->createFactory(renderedSlots: $rendered)->create('crowdfunding_contribution', 'label.crowdfunding_contribution', 'camille@example.com', ['counterparts' => []]);

        $this->assertSame(['order_link', 'counterparts', 'delivery', 'tickets', 'prize'], $rendered);
    }

    // The declaration is this bundle's own, so a body coming back null means a half-installed package - and a silent blank email is the worse of the two answers
    public function testItRefusesToSendAnEmailWithNoBody(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIsOrContains('lottery_tickets');

        $this->createFactory(html: null)->create('lottery_tickets', 'label.lottery_tickets', 'camille@example.com');
    }

    /** @param array<string, string>|null $configs */
    private function createFactory(?array $configs = null, ?string $html = '<p>rendu</p>', ?array &$renderedSlots = null): CrowdfundingEmailFactory
    {
        $configs ??= [
            'shop-name' => 'Ma boutique',
            'shop-email-from' => 'boutique@example.com',
            'shop-email-from-name' => 'La boutique',
            'shop-email-bcc' => 'archives@example.com',
            'shop-email-reply-to' => 'contact@example.com',
        ];

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): mixed => $configs[$key] ?? null);

        $renderer = $this->createStub(EmailTemplateRenderer::class);
        $renderer->method('renderNamed')->willReturn($html);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $renderedSlots ??= [];
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $view) use (&$renderedSlots): string {
            $renderedSlots[] = basename($view, '.html.twig');

            return '';
        });

        return new CrowdfundingEmailFactory($configService, $renderer, $translator, $twig);
    }
}
