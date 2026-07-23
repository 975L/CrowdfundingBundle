<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;

// Media entities for crowdfunding uploads (CrowdfundingMedia/CrowdfundingCounterpartMedia/CrowdfundingVideo/
// LotteryVideo) stay in ShopBundle - see its Media abstract class (Doctrine SINGLE_TABLE inheritance forces every
// subclass into the same bundle as its parent) - so their vich_uploader mappings are registered there, not here
class c975LCrowdfundingBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $container->prependExtensionConfig(
            'framework', [
                'asset_mapper' => [
                    'paths' => [
                        __DIR__ . '/../assets' => '@c975l/crowdfunding-bundle',
                    ],
                ],
            ]);
    }

    public function loadExtension(array $config, ContainerConfigurator $containerConfigurator, ContainerBuilder $containerBuilder): void
    {
        $containerConfigurator->import('../config/services.yaml');
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
