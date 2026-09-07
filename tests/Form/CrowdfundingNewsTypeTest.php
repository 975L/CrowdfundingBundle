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
use c975L\CrowdfundingBundle\Form\CrowdfundingNewsType;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The only form of this bundle served on a public page: the campaign's author writes a follow-up from the campaign itself
class CrowdfundingNewsTypeTest extends FormFieldsTestCase
{
    public function testItAsksForATitleAndAContent(): void
    {
        $fields = $this->buildFields(new CrowdfundingNewsType(), ['config' => []]);

        $this->assertSame(['title', 'content'], array_keys($fields));
    }

    // Both fields name their domain themselves, the type declaring none of its own
    public function testBothFieldsResolveTheirLabelInThisBundleCatalogue(): void
    {
        $fields = $this->buildFields(new CrowdfundingNewsType(), ['config' => []]);

        $this->assertSame('crowdfunding', $fields['title']['options']['translation_domain']);
        $this->assertSame('crowdfunding', $fields['content']['options']['translation_domain']);
    }

    public function testItIsBoundToTheNewsEntity(): void
    {
        $resolver = new OptionsResolver();
        new CrowdfundingNewsType()->configureOptions($resolver);

        $this->assertSame(CrowdfundingNews::class, $resolver->resolve(['config' => []])['data_class']);
    }

    // The form is built through CrowdfundingFormFactory, which passes it: a controller building it directly is told rather than left with a form missing what its theme reads
    public function testItRefusesToBeBuiltWithoutItsConfig(): void
    {
        $resolver = new OptionsResolver();
        new CrowdfundingNewsType()->configureOptions($resolver);

        $this->expectException(MissingOptionsException::class);

        $resolver->resolve();
    }
}
