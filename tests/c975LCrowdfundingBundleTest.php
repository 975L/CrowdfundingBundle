<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests;

use c975L\CrowdfundingBundle\c975LCrowdfundingBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

class c975LCrowdfundingBundleTest extends TestCase
{
    // The media entities reuse UiBundle's "block_media" mapping, a mapping of their own doubling the upload path
    public function testPrependExtensionRegistersTheFrameworkConfig(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->createVichUploaderExtensionStub());
        $configurator = $this->createStub(ContainerConfigurator::class);

        new c975LCrowdfundingBundle()->prependExtension($configurator, $container);

        $frameworkConfigs = $container->getExtensionConfig('framework');
        $this->assertNotEmpty($frameworkConfigs, 'The framework extension must receive a prepended config');
        $paths = $frameworkConfigs[0]['asset_mapper']['paths'] ?? [];
        $this->assertSame(realpath(\dirname(__DIR__) . '/assets'), realpath(array_key_first($paths)));
    }

    public function testPrependExtensionDoesNothingWhenVichUploaderIsNotInstalled(): void
    {
        $container = new ContainerBuilder();
        $configurator = $this->createStub(ContainerConfigurator::class);

        new c975LCrowdfundingBundle()->prependExtension($configurator, $container);

        $this->assertEmpty($container->getExtensionConfig('framework'));
    }

    public function testLoadExtensionImportsServicesYaml(): void
    {
        $container = new ContainerBuilder();

        new c975LCrowdfundingBundle()->getContainerExtension()->load([], $container);

        $this->assertTrue($container->hasDefinition(\c975L\CrowdfundingBundle\Service\CrowdfundingService::class));
    }

    public function testGetPathReturnsTheBundleRootDirectory(): void
    {
        $bundle = new c975LCrowdfundingBundle();

        $this->assertSame(\dirname(__DIR__), $bundle->getPath());
    }

    private function createVichUploaderExtensionStub(): ExtensionInterface
    {
        return new class implements ExtensionInterface {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'vich_uploader';
            }
        };
    }
}
