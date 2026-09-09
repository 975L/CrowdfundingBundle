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
use Symfony\Component\OptionsResolver\OptionsResolver;

// The only form of this bundle served on a public page: the campaign's author writes a follow-up from the campaign itself
class CrowdfundingNewsTypeTest extends FormFieldsTestCase
{
    public function testItAsksForATitleAndAContent(): void
    {
        $fields = $this->buildFields(new CrowdfundingNewsType());

        $this->assertSame(['title', 'content'], array_keys($fields));
    }

    // Both fields name their domain themselves, the type declaring none of its own
    public function testBothFieldsResolveTheirLabelInThisBundleCatalogue(): void
    {
        $fields = $this->buildFields(new CrowdfundingNewsType());

        $this->assertSame('crowdfunding', $fields['title']['options']['translation_domain']);
        $this->assertSame('crowdfunding', $fields['content']['options']['translation_domain']);
    }

    public function testItIsBoundToTheNewsEntity(): void
    {
        $resolver = new OptionsResolver();
        new CrowdfundingNewsType()->configureOptions($resolver);

        $this->assertSame(CrowdfundingNews::class, $resolver->resolve()['data_class']);
    }

    // The type is entered from two places now - the campaign page through CrowdfundingFormFactory, and the campaign's edit form as the entry type of a CollectionField, which passes no option of its own. An option required here would have made the second throw the moment the form renders
    public function testItBuildsWithNoOptionOfItsOwn(): void
    {
        $resolver = new OptionsResolver();
        new CrowdfundingNewsType()->configureOptions($resolver);

        $this->assertSame('crowdfunding', $resolver->resolve()['translation_domain']);
    }
}
