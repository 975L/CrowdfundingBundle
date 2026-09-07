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
use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use PHPUnit\Framework\TestCase;

// The campaign's follow-up, written from the public page by its author rather than from the back office
class CrowdfundingNewsTest extends TestCase
{
    public function testANewsCarriesItsTitleAndItsContent(): void
    {
        $news = new CrowdfundingNews()->setTitle('Nous y sommes')->setContent('Le palier est atteint.');

        $this->assertSame('Nous y sommes', $news->getTitle());
        $this->assertSame('Le palier est atteint.', $news->getContent());
    }

    // Three separate dates: a news is dated by its publication on the page, and by its creation and its edit in the back office
    public function testANewsCarriesItsThreeDatesSeparately(): void
    {
        $published = new \DateTime('2026-06-08 09:00:00');
        $created = new \DateTime('2026-06-07 18:00:00');
        $modified = new \DateTime('2026-06-09 11:00:00');

        $news = new CrowdfundingNews()
            ->setPublishedDate($published)
            ->setCreation($created)
            ->setModification($modified)
        ;

        $this->assertSame($published, $news->getPublishedDate());
        $this->assertSame($created, $news->getCreation());
        $this->assertSame($modified, $news->getModification());
    }

    // Doctrine writes the owning side, and the collection's own adder is what sets it: assigned directly, the news would be saved with a null campaign
    public function testANewsHangsFromItsCampaign(): void
    {
        $crowdfunding = new Crowdfunding();
        $news = new CrowdfundingNews();

        $crowdfunding->addNews($news);

        $this->assertSame($crowdfunding, $news->getCrowdfunding());
    }
}
