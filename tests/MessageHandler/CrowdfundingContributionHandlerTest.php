<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\MessageHandler;

use c975L\CrowdfundingBundle\Email\CrowdfundingEmailSender;
use c975L\CrowdfundingBundle\Message\CrowdfundingContributionMessage;
use c975L\CrowdfundingBundle\MessageHandler\CrowdfundingContributionHandler;
use c975L\PaymentBundle\Entity\Basket;
use c975L\PaymentBundle\Repository\BasketRepository;
use PHPUnit\Framework\TestCase;

class CrowdfundingContributionHandlerTest extends TestCase
{
    // A basket may hold products, gift cards and counterparts at once: only the lines filed under this bundle's own kind go into the thank-you email
    public function testItThanksTheContributorForTheCounterpartsOfTheBasket(): void
    {
        $basket = $this->createBasket([
            'product' => [['item' => ['id' => 1], 'quantity' => 1]],
            'crowdfunding' => [['item' => ['id' => 7], 'quantity' => 2, 'parent' => ['title' => 'Sauver les chats']]],
        ]);
        $basket->setEmail('camille@example.com');

        $sender = $this->createMock(CrowdfundingEmailSender::class);
        $sender->expects($this->once())->method('send')->with(
            'crowdfunding_contribution',
            'label.crowdfunding_contribution',
            'camille@example.com',
            ['basket' => $basket, 'counterparts' => $basket->getItems()['crowdfunding']],
            $basket->getLocale(),
            'Sauver les chats',
        );

        $this->createHandler($basket, $sender)(new CrowdfundingContributionMessage(1));
    }

    // An order holding no counterpart is somebody else's to acknowledge - PaymentBundle sends its own order email either way
    public function testItSendsNothingForABasketWithoutACounterpart(): void
    {
        $sender = $this->createMock(CrowdfundingEmailSender::class);
        $sender->expects($this->never())->method('send');

        $this->createHandler($this->createBasket(['product' => [['item' => ['id' => 1], 'quantity' => 1]]]), $sender)(new CrowdfundingContributionMessage(1));
    }

    // The message outlives the row it names: a basket deleted before the worker picks it up leaves the queue rather than throwing
    public function testItSendsNothingWhenTheBasketIsGone(): void
    {
        $sender = $this->createMock(CrowdfundingEmailSender::class);
        $sender->expects($this->never())->method('send');

        $this->createHandler(null, $sender)(new CrowdfundingContributionMessage(1));
    }

    private function createBasket(array $items): Basket
    {
        return new Basket()->setItems($items);
    }

    private function createHandler(?Basket $basket, CrowdfundingEmailSender $sender): CrowdfundingContributionHandler
    {
        $repository = $this->createStub(BasketRepository::class);
        $repository->method('find')->willReturn($basket);

        return new CrowdfundingContributionHandler($repository, $sender);
    }
}
