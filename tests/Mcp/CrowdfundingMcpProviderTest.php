<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Mcp;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\CrowdfundingBundle\Mcp\CrowdfundingMcpProvider;
use c975L\CrowdfundingBundle\Repository\CrowdfundingNewsRepository;
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\CrowdfundingBundle\Twig\Extension\CrowdfundingFundingExtension;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class CrowdfundingMcpProviderTest extends TestCase
{
    // The tools named under the "crowdfunding" key, the writing ones flagged
    public function testTools(): void
    {
        $tools = iterator_to_array($this->provider()->getMcpTools(), false);

        $this->assertSame('crowdfunding', $this->provider()->getMcpKey());
        $this->assertStringContainsString('at once', $this->provider()->getMcpInstructions());
        $this->assertSame(['crowdfunding_list', 'crowdfunding_get', 'crowdfunding_edit', 'crowdfunding_news_edit'], array_map(static fn ($tool): string => $tool->name, $tools));
        $this->assertSame([false, false, true, true], array_map(static fn ($tool): bool => $tool->writes, $tools));
    }

    // A campaign read with its progress and its news, a campaign without dates read as not started
    public function testGet(): void
    {
        $crowdfunding = $this->crowdfunding()->setAmountGoal(10000)->setAmountAchieved(2500);
        $crowdfunding->addNews(new CrowdfundingNews()->setTitle('news-1')->setContent('content-1')->setPublishedDate(new \DateTimeImmutable('2026-11-21')));
        $repository = $this->createStub(CrowdfundingRepository::class);
        $repository->method('find')->willReturn($crowdfunding);

        $campaign = $this->provider($repository)->get(1);
        $this->assertSame(['item-1', 10000, 2500, 25, false], [$campaign['title'], $campaign['goal'], $campaign['achieved'], $campaign['progressPercent'], $campaign['started']]);
        $this->assertSame([['id' => null, 'title' => 'news-1', 'content' => 'content-1', 'published' => '2026-11-21']], $campaign['news']);
    }

    // Only the texts given change, a blank one left as it was, the goal untouched
    public function testEdit(): void
    {
        $crowdfunding = $this->crowdfunding()->setAmountGoal(10000);
        $repository = $this->createStub(CrowdfundingRepository::class);
        $repository->method('find')->willReturn($crowdfunding);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $this->provider($repository, entityManager: $entityManager)->edit(1, title: ' ', description: ' description-2 ');
        $this->assertSame(['item-1', 'description-2', 'use-1', 10000], [$crowdfunding->getTitle(), $crowdfunding->getDescription(), $crowdfunding->getUseFor(), $crowdfunding->getAmountGoal()]);
    }

    // A campaign in the trash answers as unknown
    public function testTrashedCampaignIsUnknown(): void
    {
        $repository = $this->createStub(CrowdfundingRepository::class);
        $repository->method('find')->willReturn($this->crowdfunding()->setIsDeleted(true));

        $this->expectException(ToolCallException::class);
        $this->provider($repository)->get(1);
    }

    // A news' title or content changed, its publication day kept, one of a trashed campaign unknown
    public function testEditNews(): void
    {
        $news = new CrowdfundingNews()->setTitle('news-1')->setContent('content-1')->setPublishedDate(new \DateTimeImmutable('2026-11-21'))->setCrowdfunding($this->crowdfunding());
        $newsRepository = $this->createStub(CrowdfundingNewsRepository::class);
        $newsRepository->method('find')->willReturn($news);

        $this->provider(newsRepository: $newsRepository)->editNews(1, content: 'content-2');
        $this->assertSame(['news-1', 'content-2', '2026-11-21'], [$news->getTitle(), $news->getContent(), $news->getPublishedDate()?->format('Y-m-d')]);

        $news->getCrowdfunding()?->setIsDeleted(true);
        $this->expectException(ToolCallException::class);
        $this->provider(newsRepository: $newsRepository)->editNews(1, title: 'news-2');
    }

    // An account that does not edit the campaigns is refused every tool
    public function testRefusedWithoutTheRole(): void
    {
        $this->expectException(ToolCallException::class);
        $this->provider(granted: false)->list();
    }

    // A campaign with its texts written
    private function crowdfunding(): Crowdfunding
    {
        return new Crowdfunding()->setTitle('item-1')->setSlug('item-1')->setDescription('description-1')->setAuthorPresentation('author-1')->setUseFor('use-1')->setCurrency('EUR');
    }

    // The provider for an account editing the campaigns or not
    private function provider(?CrowdfundingRepository $repository = null, ?CrowdfundingNewsRepository $newsRepository = null, ?EntityManagerInterface $entityManager = null, bool $granted = true): CrowdfundingMcpProvider
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn('ROLE_EDITOR');
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($granted);

        return new CrowdfundingMcpProvider($repository ?? $this->createStub(CrowdfundingRepository::class), $newsRepository ?? $this->createStub(CrowdfundingNewsRepository::class), new CrowdfundingFundingExtension(), $entityManager ?? $this->createStub(EntityManagerInterface::class), $config, $security);
    }
}
