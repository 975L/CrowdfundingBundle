<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Management;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Management\CrowdfundingBlockEditUrlProvider;
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\UiBundle\Entity\Block;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

class CrowdfundingBlockEditUrlProviderTest extends TestCase
{
    // The hover button lands on the campaign's own edit screen, focused on the block that was clicked
    public function testABlockOfACampaignResolvesThatCampaignEditScreen(): void
    {
        $block = $this->createBlock(12);
        $campaign = new Crowdfunding()->addBlock($block);
        new \ReflectionProperty(Crowdfunding::class, 'id')->setValue($campaign, 3);

        $urls = $this->createProvider([$campaign])->getEditUrls([$block]);

        $this->assertSame([12 => '/management/crowdfunding/edit?focusBlock=12'], $urls);
    }

    // A block of a page this bundle does not own is somebody else's provider to answer, and this one says nothing about it
    public function testABlockNoCampaignOwnsIsLeftOut(): void
    {
        $urls = $this->createProvider([])->getEditUrls([$this->createBlock(99)]);

        $this->assertSame([], $urls);
    }

    // A block never saved carries no id, so there is nothing to look up and the repository is never asked
    public function testABlockWithoutAnIdQueriesNothing(): void
    {
        $repository = $this->createMock(CrowdfundingRepository::class);
        $repository->expects($this->never())->method('findByBlockIds');

        $provider = new CrowdfundingBlockEditUrlProvider($repository, $this->createUrlGenerator());

        $this->assertSame([], $provider->getEditUrls([new Block()]));
    }

    // A campaign holds several blocks and only the ones asked for are answered: the query brings the whole campaign back
    public function testOnlyTheBlocksAskedForAreAnswered(): void
    {
        $asked = $this->createBlock(12);
        $campaign = new Crowdfunding()->addBlock($asked)->addBlock($this->createBlock(13));
        new \ReflectionProperty(Crowdfunding::class, 'id')->setValue($campaign, 3);

        $urls = $this->createProvider([$campaign])->getEditUrls([$asked]);

        $this->assertSame([12], array_keys($urls));
    }

    private function createBlock(int $id): Block
    {
        $block = new Block()->setKind('crowdfunding_slider');
        new \ReflectionProperty(Block::class, 'id')->setValue($block, $id);

        return $block;
    }

    /** @param list<Crowdfunding> $campaigns */
    private function createProvider(array $campaigns): CrowdfundingBlockEditUrlProvider
    {
        $repository = $this->createStub(CrowdfundingRepository::class);
        $repository->method('findByBlockIds')->willReturn($campaigns);

        return new CrowdfundingBlockEditUrlProvider($repository, $this->createUrlGenerator());
    }

    // EasyAdmin's generator is a fluent builder: every setter answers itself, and the block focused is read back off the url
    private function createUrlGenerator(): AdminUrlGeneratorInterface
    {
        $focused = new \stdClass();
        $focused->blockId = null;

        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('setEntityId')->willReturnSelf();
        $adminUrlGenerator->method('set')->willReturnCallback(
            static function (string $name, mixed $value) use ($adminUrlGenerator, $focused): AdminUrlGeneratorInterface {
                $focused->blockId = $value;

                return $adminUrlGenerator;
            }
        );
        $adminUrlGenerator->method('generateUrl')->willReturnCallback(
            static fn (): string => '/management/crowdfunding/edit?focusBlock=' . $focused->blockId
        );

        return $adminUrlGenerator;
    }
}
