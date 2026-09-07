<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Message;

use c975L\CrowdfundingBundle\Message\CrowdfundingContributionMessage;
use c975L\CrowdfundingBundle\Message\LotteryTicketsMessage;
use c975L\CrowdfundingBundle\Message\LotteryWinningTicketMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The three messages carry an id and nothing else: an entity serialised into the queue would be read back stale, minutes after the worker picked it up, and the handlers all look their row up again
class MessagePayloadTest extends TestCase
{
    /** @return iterable<string, array{object, string, int}> */
    public static function messages(): iterable
    {
        yield 'contribution' => [new CrowdfundingContributionMessage(12), 'getBasketId', 12];
        yield 'lottery tickets' => [new LotteryTicketsMessage(34), 'getContributorId', 34];
        yield 'winning ticket' => [new LotteryWinningTicketMessage(56), 'getPrizeId', 56];
    }

    #[DataProvider('messages')]
    public function testEachMessageHandsBackTheIdItWasBuiltOn(object $message, string $getter, int $id): void
    {
        $this->assertSame($id, $message->{$getter}());
    }

    // Declared an int rather than a nullable one: a row not yet flushed has no id, and the message is where that has to be refused - dispatched before the flush, the tickets announcement carried a null through the whole payment confirmation
    #[DataProvider('messages')]
    public function testEachMessageRefusesToBeBuiltWithoutAnId(object $message, string $getter, int $id): void
    {
        $parameter = new \ReflectionMethod($message, '__construct')->getParameters()[0];

        $this->assertSame('int', (string) $parameter->getType());
        $this->assertFalse($parameter->getType()->allowsNull());
    }

    // Nothing is settable: what the queue holds is what the dispatcher decided
    #[DataProvider('messages')]
    public function testEachMessageIsReadOnlyOnceBuilt(object $message, string $getter, int $id): void
    {
        foreach (new \ReflectionClass($message)->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly(), sprintf('%s::$%s can be written after the message was dispatched.', $message::class, $property->getName()));
        }
    }
}
