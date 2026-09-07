<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Service;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\CrowdfundingBundle\Form\CrowdfundingFormFactoryInterface;
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\CrowdfundingBundle\Service\CrowdfundingService;
use c975L\CrowdfundingBundle\Service\CrowdfundingServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;

class CrowdfundingServiceTest extends TestCase
{
    public function testItImplementsItsOwnContract(): void
    {
        $this->assertInstanceOf(CrowdfundingServiceInterface::class, $this->createService());
    }

    // A news is written from the campaign page by its author, and reaches the database in one flush
    public function testAddNewsTiesTheNewsToItsCampaignAndSavesIt(): void
    {
        $crowdfunding = new Crowdfunding();
        $news = new CrowdfundingNews();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($news);
        $entityManager->expects($this->once())->method('flush');

        $this->createService(entityManager: $entityManager)->addNews($crowdfunding, $news);

        $this->assertSame($crowdfunding, $news->getCrowdfunding());
    }

    // Three DATETIME_MUTABLE columns: an immutable stamp on any of them is refused by Doctrine at the very flush above
    public function testAddNewsStampsTheThreeDatesWithMutableOnes(): void
    {
        $news = new CrowdfundingNews();

        $this->createService()->addNews(new Crowdfunding(), $news);

        $this->assertInstanceOf(\DateTime::class, $news->getCreation());
        $this->assertInstanceOf(\DateTime::class, $news->getModification());
        $this->assertInstanceOf(\DateTime::class, $news->getPublishedDate());
    }

    // Published straight away: the form has no draft state, and a news nobody reads has no reason to be written
    public function testAddNewsPublishesTheNewsAsItIsWritten(): void
    {
        $news = new CrowdfundingNews();

        $this->createService()->addNews(new Crowdfunding(), $news);

        $this->assertEqualsWithDelta($news->getCreation()->getTimestamp(), $news->getPublishedDate()->getTimestamp(), 1);
    }

    // The controller asks the service, which asks the factory: a site overriding the news form replaces the factory alone
    public function testCreateFormDelegatesToTheFormFactory(): void
    {
        $news = new CrowdfundingNews();
        $form = $this->createStub(FormInterface::class);

        $formFactory = $this->createMock(CrowdfundingFormFactoryInterface::class);
        $formFactory->expects($this->once())->method('create')->with('news', $news)->willReturn($form);

        $this->assertSame($form, $this->createService(formFactory: $formFactory)->createForm('news', $news));
    }

    public function testFindAllSortedHandsBackWhatTheRepositoryOrdered(): void
    {
        $crowdfundings = [new Crowdfunding(), new Crowdfunding()];

        $repository = $this->createStub(CrowdfundingRepository::class);
        $repository->method('findAllSorted')->willReturn($crowdfundings);

        $this->assertSame($crowdfundings, $this->createService(repository: $repository)->findAllSorted());
    }

    public function testFindAllHandsBackTheWholeTable(): void
    {
        $crowdfundings = [new Crowdfunding()];

        $repository = $this->createStub(CrowdfundingRepository::class);
        $repository->method('findAll')->willReturn($crowdfundings);

        $this->assertSame($crowdfundings, $this->createService(repository: $repository)->findAll());
    }

    // The eager-loading lookup rather than the magic finder: the campaign page draws seven collections off the row it answers
    public function testFindOneByIdGoesThroughTheRepositoryJoiningLookup(): void
    {
        $crowdfunding = new Crowdfunding();

        $repository = $this->createMock(CrowdfundingRepository::class);
        $repository->expects($this->once())->method('findOneById')->with(7)->willReturn($crowdfunding);

        $this->assertSame($crowdfunding, $this->createService(repository: $repository)->findOneById(7));
    }

    private function createService(
        ?CrowdfundingRepository $repository = null,
        ?EntityManagerInterface $entityManager = null,
        ?CrowdfundingFormFactoryInterface $formFactory = null,
    ): CrowdfundingService {
        return new CrowdfundingService(
            $repository ?? $this->createStub(CrowdfundingRepository::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $formFactory ?? $this->createStub(CrowdfundingFormFactoryInterface::class),
        );
    }
}
