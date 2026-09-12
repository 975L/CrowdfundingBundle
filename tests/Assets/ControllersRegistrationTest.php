<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// Guards assets/controllers.js, the front-end Stimulus barrel - the repository has no browser to load it in, so a barrel starting no app is silent here and leaves the draw wheel dead in a consuming app, which is exactly what it did until 07/09/2026
class ControllersRegistrationTest extends TestCase
{
    private const string BARREL = 'assets/controllers.js';

    // The barrel joins the one application of the page rather than starting a second: startStimulusApp() also registers whatever controllers.json enables, so a page loading several of these barrels built "live" once per barrel
    public function testTheBarrelJoinsTheSharedStimulusApp(): void
    {
        $barrel = $this->read();

        $this->assertStringContainsString('startStimulusApp()', $barrel, 'The barrel starts no Stimulus app, so nothing registers its controllers.');
        $this->assertStringContainsString('globalThis.c975lStimulusApp ??= startStimulusApp()', $barrel, 'The barrel starts an application of its own instead of joining the page\'s.');
        $this->assertStringNotContainsString('export function register', $barrel, 'The barrel still exports register(), which no consuming app calls any more.');
    }

    // The identifier lottery/display.html.twig writes in its data-controller - registered lazily, the layout loading this barrel site-wide while the wheel lives on the lottery page alone
    public function testTheLotteryControllerIsRegisteredAsALazyFrontController(): void
    {
        $this->assertStringContainsString("lottery: () => import('./js/lottery.js'),", $this->read());
    }

    // Registering on load alone leaves a page reached by a Turbo navigation without its controllers, the module never running again
    public function testTheLazyControllersAreRegisteredAgainOnTurboLoad(): void
    {
        $barrel = $this->read();

        $this->assertStringContainsString('registerPresentControllers()', $barrel);
        $this->assertStringContainsString("document.addEventListener('turbo:load', registerPresentControllers);", $barrel);
    }

    // An import of a missing file leaves the identifier registered with nothing behind it, and the draw button dead
    public function testEveryLazyControllerIsShipped(): void
    {
        preg_match_all("#import\('\./(js/[^']+\.js)'\)#", $this->read(), $matches);
        $this->assertNotSame([], $matches[1], 'The barrel imports no controller at all.');

        foreach ($matches[1] as $path) {
            $this->assertFileExists(\dirname(__DIR__, 2) . '/assets/' . $path, sprintf('The barrel imports "%s", which the bundle does not ship', $path));
        }
    }

    // Every identifier a template asks for has to be registered, or the markup is inert with nothing in the console to say so
    public function testEveryIdentifierTheTemplatesNameIsRegisteredOrBorrowed(): void
    {
        $registered = $this->registeredIdentifiers();

        // "basket" is PaymentBundle's own, registered by its barrel: the counterparts draw its add buttons
        $borrowed = ['basket'];

        foreach ($this->identifiersTheTemplatesName() as $identifier => $template) {
            $this->assertTrue(
                \in_array($identifier, $registered, true) || \in_array($identifier, $borrowed, true),
                sprintf('"%s" writes data-controller="%s", which no barrel of this bundle registers.', $template, $identifier),
            );
        }
    }

    /** @return list<string> */
    private function registeredIdentifiers(): array
    {
        preg_match_all('/^\s{4}(\w+): \(\) => import/m', $this->read(), $matches);

        return $matches[1];
    }

    /** @return array<string, string> */
    private function identifiersTheTemplatesName(): array
    {
        $named = [];

        foreach (glob(\dirname(__DIR__, 2) . '/templates/**/*.twig') ?: [] as $template) {
            preg_match_all('/data-controller="([^"]+)"/', (string) file_get_contents($template), $matches);
            foreach ($matches[1] as $value) {
                foreach (preg_split('/\s+/', $value) ?: [] as $identifier) {
                    $named[$identifier] = basename($template);
                }
            }
        }

        return $named;
    }

    private function read(): string
    {
        $path = \dirname(__DIR__, 2) . '/' . self::BARREL;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
