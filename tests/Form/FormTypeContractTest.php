<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Form;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

// What every form type of this bundle has to hold, checked over the lot rather than restated in each of their own tests: a data_class naming an entity that exists, and labels resolved in this bundle's own catalogue
class FormTypeContractTest extends TestCase
{
    /** @return iterable<string, array{class-string<AbstractType>}> */
    public static function formTypes(): iterable
    {
        foreach (glob(\dirname(__DIR__, 2) . '/src/Form/*Type.php') ?: [] as $file) {
            $name = basename($file, '.php');
            yield $name => ['c975L\\CrowdfundingBundle\\Form\\' . $name];
        }
    }

    // Bound to the entity the screen edits: without it the form hands back an array, and the CRUD saves nothing
    #[DataProvider('formTypes')]
    public function testEachTypeIsBoundToAnEntityOfThisBundle(string $class): void
    {
        $options = self::resolve($class);

        $this->assertArrayHasKey('data_class', $options, sprintf('%s declares no data_class.', $class));
        $this->assertTrue(class_exists($options['data_class']), sprintf('%s is bound to %s, which does not exist.', $class, $options['data_class']));
        $this->assertStringStartsWith('c975L\\CrowdfundingBundle\\Entity\\', $options['data_class']);
    }

    // A type resolving its labels in another bundle's domain shows them raw the day that bundle is not installed - which is exactly what happened while these read ShopBundle's "shop"
    #[DataProvider('formTypes')]
    public function testATypeNamingADomainNamesThisBundleOwn(string $class): void
    {
        $options = self::resolve($class);

        if (!isset($options['translation_domain'])) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertSame('crowdfunding', $options['translation_domain'], sprintf('%s resolves its labels in the "%s" domain.', $class, $options['translation_domain']));
    }

    /** @return array<string, mixed> */
    private static function resolve(string $class): array
    {
        $resolver = new OptionsResolver();
        new $class()->configureOptions($resolver);

        // A required option carries no default, and is handed an empty one here: the news form asks for the "config" the controller passes it
        $required = [];
        foreach ($resolver->getRequiredOptions() as $option) {
            $required[$option] = [];
        }

        return $resolver->resolve($required);
    }
}
