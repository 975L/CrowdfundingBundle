<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Entity;

use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributorCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use PHPUnit\Framework\TestCase;

class CrowdfundingCounterpartTest extends TestCase
{
    // getContributors() walks the relation collection: left uninitialised, a counterpart built with new fatals there rather than answering an empty list
    public function testTheRelationCollectionIsUsableOnACounterpartJustBuilt(): void
    {
        $this->assertCount(0, new CrowdfundingCounterpart()->getContributors());
    }

    // The contributors are read through the join rows, which is where the quantity of each lives
    public function testGetContributorsWalksTheRelationRows(): void
    {
        $counterpart = new CrowdfundingCounterpart();
        $camille = new CrowdfundingContributor()->setName('Camille');
        $dominique = new CrowdfundingContributor()->setName('Dominique');

        // The collection is Doctrine's to fill, the entity declaring no adder for it - written here the way the ORM would
        $relations = new \ReflectionProperty(CrowdfundingCounterpart::class, 'contributorCounterparts')->getValue($counterpart);
        $relations->add(new CrowdfundingContributorCounterpart()->setContributor($camille)->setQuantity(2));
        $relations->add(new CrowdfundingContributorCounterpart()->setContributor($dominique)->setQuantity(1));

        $this->assertSame([$camille, $dominique], $counterpart->getContributors()->toArray());
    }

    // The basket keeps a copy of the counterpart rather than a reference, and toArray() is what it copies
    public function testToArrayCarriesTheFieldsTheBasketLineIsBuiltFrom(): void
    {
        $counterpart = new CrowdfundingCounterpart()
            ->setTitle('Le tote bag')
            ->setPrice(2500)
            ->setCurrency('eur')
        ;

        $itemData = $counterpart->toArray();

        $this->assertSame('Le tote bag', $itemData['title']);
        $this->assertSame(2500, $itemData['price']);
        $this->assertSame('eur', $itemData['currency']);
    }

    // A campaign's counterpart entitles to a whole number of tickets, zero meaning it is not a lottery counterpart
    public function testACounterpartEntitlesToAWholeNumberOfLotteryTickets(): void
    {
        $this->assertSame(0, new CrowdfundingCounterpart()->getLotteryTickets());
        $this->assertSame(5, new CrowdfundingCounterpart()->setLotteryTickets(5)->getLotteryTickets());
    }

    // The form offers zero to ten, and the setter holds that range whatever reaches it - an import, a fixture or a hand-written query
    public function testTheNumberOfTicketsIsHeldBetweenZeroAndTen(): void
    {
        $this->assertSame(0, new CrowdfundingCounterpart()->setLotteryTickets(-3)->getLotteryTickets());
        $this->assertSame(10, new CrowdfundingCounterpart()->setLotteryTickets(42)->getLotteryTickets());
    }

    public function testACounterpartIsPrintedAsItsTitle(): void
    {
        $this->assertSame('Le tote bag', (string) new CrowdfundingCounterpart()->setTitle('Le tote bag'));
    }

    // A language laid over the row is what the page reads, the row keeping the text it was written with - the expected delivery included, a sentence rather than a date
    public function testATranslationIsReadOverTheTextTheCounterpartWasWrittenWith(): void
    {
        $counterpart = new CrowdfundingCounterpart()->setTitle('Le tote bag')->setDescription('En coton bio')->setExpectedDelivery('Mars 2027');
        $counterpart->setTranslated(['title' => 'The tote bag', 'expectedDelivery' => 'March 2027']);

        $this->assertSame('The tote bag', $counterpart->getTitle());
        $this->assertSame('March 2027', $counterpart->getExpectedDelivery());
        $this->assertSame('En coton bio', $counterpart->getDescription());
        $this->assertSame('Le tote bag', $counterpart->getUntranslated('title'));
        $this->assertSame('Mars 2027', $counterpart->getUntranslated('expectedDelivery'));
        $this->assertNull($counterpart->getUntranslated('price'));
    }
}
