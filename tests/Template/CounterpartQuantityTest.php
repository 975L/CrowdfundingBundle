<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Template;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;

// What a tier says it has left: a run sold past its cap has nothing left, not less than nothing
class CounterpartQuantityTest extends TestCase
{
    public function testWhatIsLeftOfARun(): void
    {
        $this->assertStringContainsString('<strong>2</strong>', $this->render(5, 3));
    }

    public function testARunPastItsCapHasNothingLeft(): void
    {
        $html = $this->render(3, 4);

        $this->assertStringContainsString('<strong>0</strong>', $html);
        $this->assertStringNotContainsString('-1', $html);
    }

    private function render(int $limited, int $ordered): string
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/components/CrowdfundingCounterpart/Quantity.html.twig');
        $twig = new Environment(new ArrayLoader(['template' => $source]));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        return $twig->render('template', ['limited' => $limited, 'ordered' => $ordered]);
    }
}
