<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Mcp;

use c975L\ConfigBundle\Mcp\McpTool;
use c975L\ConfigBundle\Mcp\McpToolProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\CrowdfundingBundle\Repository\CrowdfundingNewsRepository;
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\CrowdfundingBundle\Twig\Extension\CrowdfundingFundingExtension;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use Symfony\Bundle\SecurityBundle\Security;

// The campaigns on the site's MCP endpoint, for an account that edits them on the back office: listed, read with their progress, their texts and their news written, money, dates, visibility and the lottery left to a human
class CrowdfundingMcpProvider implements McpToolProviderInterface
{
    // Campaigns a list gives at most
    public const int LIMIT = 50;

    public function __construct(
        private readonly CrowdfundingRepository $crowdfundingRepository,
        private readonly CrowdfundingNewsRepository $newsRepository,
        private readonly CrowdfundingFundingExtension $fundingExtension,
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigServiceInterface $configService,
        private readonly Security $security,
    ) {
    }

    // Key of the campaigns for the agent tokens
    public function getMcpKey(): string
    {
        return 'crowdfunding';
    }

    // How the campaigns' tools go together
    public function getMcpInstructions(): string
    {
        return 'Crowdfunding campaigns collect contributions against counterparts. Amounts are in cents. An agent reads the campaigns and writes their texts and those of their news; goals, prices, dates, visibility and the lottery stay a human\'s, on the back office.';
    }

    // The tools of the campaigns, the writing ones flagged
    public function getMcpTools(): iterable
    {
        yield new McpTool('crowdfunding_list', 'Campaigns', 'The campaigns whose title holds the search words, every campaign without them, hidden ones included, in the order the site lists them.', $this->list(...));
        yield new McpTool('crowdfunding_get', 'Campaign', 'A campaign: its texts, its goal and amount achieved in cents, its progress and days left, its counterparts and its news.', $this->get(...));
        yield new McpTool('crowdfunding_edit', 'Write a campaign', 'Changes only the title, description, author presentation or use of funds given of a campaign.', $this->edit(...), true);
        yield new McpTool('crowdfunding_news_edit', 'Write a news', 'Changes only the title or content given of a campaign\'s news.', $this->editNews(...), true);
    }

    // The campaigns whose title holds the words, every one without them, the trash left aside
    /**
     * @param ?string $search words looked for in the titles
     *
     * @return list<array{id: ?int, title: ?string, slug: ?string, hidden: bool, begin: ?string, end: ?string, goal: ?int, achieved: int, currency: ?string}>
     */
    public function list(?string $search = null): array
    {
        $this->guard();
        $builder = $this->crowdfundingRepository->createQueryBuilder('c')
            ->andWhere('c.isDeleted = false')
            ->orderBy('c.position', \SortDirection::Ascending)
            ->setMaxResults(self::LIMIT);
        if ('' !== trim((string) $search)) {
            $builder->andWhere('c.title LIKE :words')->setParameter('words', '%' . addcslashes(trim((string) $search), '%_\\') . '%');
        }

        return array_map($this->summary(...), $builder->getQuery()->getResult());
    }

    // A campaign with its texts, where its funding stands, its counterparts and its news
    /**
     * @param int $crowdfunding id of the campaign
     *
     * @return array<string, mixed>
     */
    public function get(int $crowdfunding): array
    {
        $found = $this->found($crowdfunding);
        $state = $this->fundingExtension->getState($found);

        return [
            ...$this->summary($found),
            'authorName' => $found->getAuthorName(),
            'authorPresentation' => $found->getAuthorPresentation(),
            'description' => $found->getDescription(),
            'useFor' => $found->getUseFor(),
            'progressPercent' => $state['progressPercent'],
            'started' => $state['isStarted'],
            'ended' => $state['isEnded'],
            'daysLeft' => $state['daysLeft'],
            'contributors' => $found->getContributors()->count(),
            'counterparts' => array_map($this->counterpart(...), $found->getCounterparts()->getValues()),
            'news' => array_map($this->news(...), $found->getNews()->getValues()),
        ];
    }

    // A campaign's texts changed, only those given, an empty one left as it was since none of them may be blank
    /**
     * @param int     $crowdfunding       id of the campaign
     * @param ?string $title              new title, cut at 100 characters
     * @param ?string $description        new description, html allowed
     * @param ?string $authorPresentation new presentation of its author, html allowed
     * @param ?string $useFor             new text on what the funds are used for, html allowed
     *
     * @return array{id: ?int}
     */
    public function edit(int $crowdfunding, ?string $title = null, ?string $description = null, ?string $authorPresentation = null, ?string $useFor = null): array
    {
        $found = $this->found($crowdfunding);
        if ('' !== trim((string) $title)) {
            $found->setTitle(mb_substr(trim((string) $title), 0, 100));
        }
        if ('' !== trim((string) $description)) {
            $found->setDescription(trim((string) $description));
        }
        if ('' !== trim((string) $authorPresentation)) {
            $found->setAuthorPresentation(trim((string) $authorPresentation));
        }
        if ('' !== trim((string) $useFor)) {
            $found->setUseFor(trim((string) $useFor));
        }
        $this->entityManager->flush();

        return ['id' => $found->getId()];
    }

    // A news' title or content changed, only those given, its publication day kept
    /**
     * @param int     $news    id of the news
     * @param ?string $title   new title, cut at 100 characters
     * @param ?string $content new content, html allowed
     *
     * @return array{id: ?int}
     */
    public function editNews(int $news, ?string $title = null, ?string $content = null): array
    {
        $this->guard();
        $found = $this->newsRepository->find($news);
        if (!$found instanceof CrowdfundingNews || false !== $found->getCrowdfunding()?->isDeleted()) {
            throw new ToolCallException(sprintf('No news %d.', $news));
        }
        if ('' !== trim((string) $title)) {
            $found->setTitle(mb_substr(trim((string) $title), 0, 100));
        }
        if ('' !== trim((string) $content)) {
            $found->setContent(trim((string) $content));
        }
        $this->entityManager->flush();

        return ['id' => $found->getId()];
    }

    // The campaigns are the editor's business, as on the back office
    private function guard(): void
    {
        if (!$this->security->isGranted((string) $this->configService->get('site-role-editor'))) {
            throw new ToolCallException('This account may not work on the campaigns.');
        }
    }

    // A campaign by its id, one in the trash answering as unknown
    private function found(int $id): Crowdfunding
    {
        $this->guard();
        $crowdfunding = $this->crowdfundingRepository->find($id);
        if (!$crowdfunding instanceof Crowdfunding || $crowdfunding->isDeleted()) {
            throw new ToolCallException(sprintf('No campaign %d.', $id));
        }

        return $crowdfunding;
    }

    // A campaign in a list
    /** @return array{id: ?int, title: ?string, slug: ?string, hidden: bool, begin: ?string, end: ?string, goal: ?int, achieved: int, currency: ?string} */
    private function summary(Crowdfunding $crowdfunding): array
    {
        return [
            'id' => $crowdfunding->getId(),
            'title' => $crowdfunding->getTitle(),
            'slug' => $crowdfunding->getSlug(),
            'hidden' => $crowdfunding->isHidden(),
            'begin' => $crowdfunding->getBeginDate()?->format('Y-m-d'),
            'end' => $crowdfunding->getEndDate()?->format('Y-m-d'),
            'goal' => $crowdfunding->getAmountGoal(),
            'achieved' => (int) $crowdfunding->getAmountAchieved(),
            'currency' => $crowdfunding->getCurrency(),
        ];
    }

    // A counterpart of a campaign, its price in cents and how many were taken
    /** @return array{id: ?int, title: ?string, price: ?int, limitedQuantity: ?int, orderedQuantity: int} */
    private function counterpart(CrowdfundingCounterpart $counterpart): array
    {
        return [
            'id' => $counterpart->getId(),
            'title' => $counterpart->getTitle(),
            'price' => $counterpart->getPrice(),
            'limitedQuantity' => $counterpart->getLimitedQuantity(),
            'orderedQuantity' => (int) $counterpart->getOrderedQuantity(),
        ];
    }

    // A news of a campaign
    /** @return array{id: ?int, title: ?string, content: ?string, published: ?string} */
    private function news(CrowdfundingNews $news): array
    {
        return [
            'id' => $news->getId(),
            'title' => $news->getTitle(),
            'content' => $news->getContent(),
            'published' => $news->getPublishedDate()?->format('Y-m-d'),
        ];
    }
}
