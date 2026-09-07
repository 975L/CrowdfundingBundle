<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use c975L\UiBundle\Contract\BundleScriptProviderInterface;

// The barrel is loaded site-wide by the front layout and registers its controllers lazily, so only a page actually carrying the draw wheel downloads it
class ScriptProvider implements BundleScriptProviderInterface
{
    public function getScripts(): array
    {
        return [
            '@c975l/crowdfunding-bundle/controllers.js',
        ];
    }
}
