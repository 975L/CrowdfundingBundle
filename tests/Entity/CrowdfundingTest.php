<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Entity;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingMedia;
use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\CrowdfundingBundle\Entity\CrowdfundingVideo;
use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CrowdfundingTest extends TestCase
{
    // Six collections, and each one is read by a page before anything has been added to it: left uninitialised, a campaign built with new would fatal on the first foreach
    public function testTheSixCollectionsAreUsableOnACampaignJustBuilt(): void
    {
        $crowdfunding = new Crowdfunding();

        $this->assertCount(0, $crowdfunding->getMedias());
        $this->assertCount(0, $crowdfunding->getVideos());
        $this->assertCount(0, $crowdfunding->getContributors());
        $this->assertCount(0, $crowdfunding->getNews());
        $this->assertCount(0, $crowdfunding->getCounterparts());
        $this->assertCount(0, $crowdfunding->getLotteries());
    }

    // The campaign is what the back office lists and what a lottery ticket names in its email
    public function testACampaignIsPrintedAsItsTitle(): void
    {
        $this->assertSame('Sauver les chats', (string) new Crowdfunding()->setTitle('Sauver les chats'));
    }

    public function testACampaignWithoutATitleIsPrintedAsAnEmptyString(): void
    {
        $this->assertSame('', (string) new Crowdfunding());
    }

    /** @return iterable<string, array{string, string, object}> */
    public static function ownedCollections(): iterable
    {
        yield 'medias' => ['Media', 'Medias', new CrowdfundingMedia()];
        yield 'videos' => ['Video', 'Videos', new CrowdfundingVideo()];
        yield 'contributors' => ['Contributor', 'Contributors', new CrowdfundingContributor()];
        yield 'news' => ['News', 'News', new CrowdfundingNews()];
        yield 'counterparts' => ['Counterpart', 'Counterparts', new CrowdfundingCounterpart()];
        yield 'lotteries' => ['Lottery', 'Lotteries', new Lottery()];
    }

    // Doctrine only writes the owning side: adding to the collection without setting the back reference leaves a row whose foreign key stays null
    #[DataProvider('ownedCollections')]
    public function testAddingToACollectionSetsTheBackReference(string $singular, string $plural, object $entity): void
    {
        $crowdfunding = new Crowdfunding();

        $crowdfunding->{'add' . $singular}($entity);

        $this->assertSame($crowdfunding, $entity->getCrowdfunding());
    }

    #[DataProvider('ownedCollections')]
    public function testAddingTheSameEntityTwiceKeepsOneRow(string $singular, string $plural, object $entity): void
    {
        $crowdfunding = new Crowdfunding();

        $crowdfunding->{'add' . $singular}($entity);
        $crowdfunding->{'add' . $singular}($entity);

        $this->assertCount(1, $crowdfunding->{'get' . $plural}());
    }

    #[DataProvider('ownedCollections')]
    public function testRemovingFromACollectionClearsTheBackReference(string $singular, string $plural, object $entity): void
    {
        $crowdfunding = new Crowdfunding();
        $crowdfunding->{'add' . $singular}($entity);

        $crowdfunding->{'remove' . $singular}($entity);

        $this->assertNull($entity->getCrowdfunding());
    }

    // A campaign page is composed in the back office with UiBundle's own kinds, as a book's and a gallery's are - the last entity of the ecosystem to get there
    public function testACampaignOwnsBlocks(): void
    {
        $crowdfunding = new Crowdfunding();

        $this->assertInstanceOf(HasBlocksInterface::class, $crowdfunding);
        $this->assertCount(0, $crowdfunding->getBlocks());
    }

    public function testABlockIsAddedOnceAndRemovedCleanly(): void
    {
        $crowdfunding = new Crowdfunding();
        $block = new Block();

        $crowdfunding->addBlock($block);
        $crowdfunding->addBlock($block);

        $this->assertCount(1, $crowdfunding->getBlocks());

        $crowdfunding->removeBlock($block);

        $this->assertCount(0, $crowdfunding->getBlocks());
    }

    // A campaign's total is the sum of what was paid for, and the page draws a gauge off it: the column is an amount in cents, like every price of the ecosystem
    public function testTheAmountAchievedIsHeldInCents(): void
    {
        $crowdfunding = new Crowdfunding()->setAmountGoal(500000)->setAmountAchieved(125000);

        $this->assertSame(500000, $crowdfunding->getAmountGoal());
        $this->assertSame(125000, $crowdfunding->getAmountAchieved());
    }
}
