<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Service;

use c975L\CrowdfundingBundle\Service\ScriptProvider;
use c975L\UiBundle\Contract\BundleScriptProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class ScriptProviderTest extends TestCase
{
    // What bundle_scripts() emits in the front layout: without it the barrel was never loaded and the draw wheel never answered a click
    public function testGetScriptsAnnouncesTheBarrel(): void
    {
        $this->assertSame(['@c975l/crowdfunding-bundle/controllers.js'], new ScriptProvider()->getScripts());
    }

    public function testItImplementsTheUiContract(): void
    {
        $this->assertInstanceOf(BundleScriptProviderInterface::class, new ScriptProvider());
    }

    // The scripts are collected by tag rather than by interface, so this one is written by hand and is exactly what a missing tag would silence
    public function testItIsTaggedAsAUiScript(): void
    {
        $services = Yaml::parseFile(\dirname(__DIR__, 2) . '/config/services.yaml')['services'];

        $this->assertSame([['name' => 'ui.script', 'priority' => 100]], $services[ScriptProvider::class]['tags']);
    }
}
