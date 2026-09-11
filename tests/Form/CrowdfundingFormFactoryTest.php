<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Form;

use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\CrowdfundingBundle\Form\CrowdfundingFormFactory;
use c975L\CrowdfundingBundle\Form\CrowdfundingFormFactoryInterface;
use c975L\CrowdfundingBundle\Form\CrowdfundingNewsType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

// The one indirection between the controller and the form types: a site replacing this service swaps the news form without touching the controller
class CrowdfundingFormFactoryTest extends TestCase
{
    public function testItImplementsItsOwnContract(): void
    {
        $this->assertInstanceOf(CrowdfundingFormFactoryInterface::class, new CrowdfundingFormFactory($this->createStub(FormFactoryInterface::class)));
    }

    // The one name this bundle declares, resolved to its type
    public function testItBuildsTheNewsForm(): void
    {
        $news = new CrowdfundingNews();
        $form = $this->createStub(FormInterface::class);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->once())->method('create')->with(CrowdfundingNewsType::class, $news)->willReturn($form);

        $this->assertSame($form, new CrowdfundingFormFactory($formFactory)->create('news', $news));
    }

    // A name nobody declares is a programming mistake, not a visitor's: it fails loudly rather than handing back a form of the wrong type
    public function testItRefusesAFormItDoesNotKnow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Unknown form "contribution"');

        new CrowdfundingFormFactory($this->createStub(FormFactoryInterface::class))->create('contribution', new CrowdfundingNews());
    }
}
