<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use Symfony\Component\Mime\Address;
use c975L\PaymentBundle\Entity\Basket;
use c975L\CrowdfundingBundle\Entity\LotteryPrize;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailService implements EmailServiceInterface
{
    private string $subjectPrefix;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
        $this->subjectPrefix = $this->translator->trans('label.shop', [], 'shop') . ' ' . $this->configService->get('shop-name') . ' - ';
    }

    // Creates a new email, using the same shop-* config as PaymentBundle/ShopBundle
    private function create(): TemplatedEmail
    {
        $email = new TemplatedEmail();
        $email->from(new Address($this->configService->get('shop-email-from'), $this->configService->has('shop-email-from-name') ? $this->configService->get('shop-email-from-name') : ''));
        $email->bcc(new Address($this->configService->get('shop-email-bcc'), $this->configService->has('shop-email-bcc-name') ? $this->configService->get('shop-email-bcc-name') : ''));
        $email->replyTo(new Address($this->configService->get('shop-email-reply-to'), $this->configService->has('shop-email-reply-to-name') ? $this->configService->get('shop-email-reply-to-name') : ''));

        return $email;
    }

    // Sends the crowdfunding contribution email
    public function crowdfundingContribution(Basket $basket, array $counterparts): void
    {
        $crowdfundingTitle = '';
        foreach ($counterparts as $counterpart) {
            $crowdfundingTitle = $counterpart['parent']['title'];
            break;
        }

        $email = $this->create();
        $email->to(new Address($basket->getEmail()));
        $email->subject($this->subjectPrefix . $this->translator->trans('label.crowdfunding_contribution', [], 'shop') . ' - ' . $crowdfundingTitle);
        $email->htmlTemplate('@c975LCrowdfunding/emails/crowdfunding_contribution.html.twig');
        $email->context([
            'basket' => $basket,
            'counterparts' => $counterparts,
        ]);

        $this->mailer->send($email);
    }

    // Sends the lottery tickets email
    public function lotteryTickets(string $emailAddress, array $tickets): void
    {
        $email = $this->create();
        $email->to(new Address($emailAddress));
        $email->subject($this->subjectPrefix . $this->translator->trans('label.lottery_tickets', [], 'shop') . ' - ' . $tickets[0]['lotteryIdentifier']);
        $email->htmlTemplate('@c975LCrowdfunding/emails/lottery_tickets.html.twig');
        $email->context([
            'tickets' => $tickets,
        ]);

        $this->mailer->send($email);
    }

    // Sends the lottery winning ticket email
    public function lotteryWinningTicket(LotteryPrize $prize): void
    {
        $email = $this->create();
        $email->to(new Address($prize->getWinningTicket()->getContributor()->getEmail()));
        $email->subject($this->subjectPrefix . $this->translator->trans('label.lottery', [], 'shop') . ' - ' . $prize->getLottery()->getIdentifier() . ' - ' . $this->translator->trans('label.winning_ticket', [], 'shop'));
        $email->htmlTemplate('@c975LCrowdfunding/emails/lottery_ticket_winner.html.twig');
        $email->context([
            'prize' => $prize,
        ]);

        $this->mailer->send($email);
    }
}
