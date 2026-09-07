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
use c975L\CrowdfundingBundle\Management\CrowdfundingBlockOwnerResolver;
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\UiBundle\Contract\BlockOwnerResolverInterface;
use PHPUnit\Framework\TestCase;

// What lets UiBundle's block move screen walk back from a block to the campaign holding it, without that bundle knowing the Crowdfunding class
class CrowdfundingBlockOwnerResolverTest extends TestCase
{
    // The very string CrowdfundingCrudController writes on its collection: the two read it off this constant so they cannot drift apart
    public function testItSupportsItsOwnOwnerTypeAndNoOther(): void
    {
        $resolver = $this->createResolver();

        $this->assertTrue($resolver->supports(CrowdfundingBlockOwnerResolver::TYPE_CROWDFUNDING));
        $this->assertFalse($resolver->supports('page'));
        $this->assertFalse($resolver->supports('book'));
    }

    public function testItFindsTheCampaignHoldingTheBlock(): void
    {
        $crowdfunding = new Crowdfunding();

        $repository = $this->createMock(CrowdfundingRepository::class);
        $repository->expects($this->once())->method('find')->with(7)->willReturn($crowdfunding);

        $this->assertSame($crowdfunding, $this->createResolver($repository)->find(CrowdfundingBlockOwnerResolver::TYPE_CROWDFUNDING, 7));
    }

    // Another bundle's owner type reaches every resolver in turn: this one answers null rather than looking a campaign up on somebody else's id
    public function testItReadsNothingForAnOwnerTypeThatIsNotItsOwn(): void
    {
        $repository = $this->createMock(CrowdfundingRepository::class);
        $repository->expects($this->never())->method('find');

        $this->assertNull($this->createResolver($repository)->find('page', 7));
    }

    // Found by BlockOwnerResolverPass through the contract, not by a tag written by hand
    public function testItImplementsTheUiContract(): void
    {
        $this->assertInstanceOf(BlockOwnerResolverInterface::class, $this->createResolver());
    }

    private function createResolver(?CrowdfundingRepository $repository = null): CrowdfundingBlockOwnerResolver
    {
        return new CrowdfundingBlockOwnerResolver($repository ?? $this->createStub(CrowdfundingRepository::class));
    }
}
