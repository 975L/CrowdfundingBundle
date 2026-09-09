<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Controller\Management\CrowdfundingCrudController;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Twig\Extension\CrowdfundingEditExtension;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

// What sends an editor from a section of the campaign page to the field that section is written in
class CrowdfundingEditExtensionTest extends TestCase
{
    // The url UiBundle's field-focus.js reads: the campaign's edit screen, plus the name of the field to open, scroll to and focus
    public function testTheEditScreenIsOpenedOnTheNamedField(): void
    {
        $urlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $urlGenerator->method('unsetAll')->willReturnSelf();
        $urlGenerator->expects($this->once())->method('setController')->with(CrowdfundingCrudController::class)->willReturnSelf();
        $urlGenerator->expects($this->once())->method('setAction')->with(Action::EDIT)->willReturnSelf();
        $urlGenerator->expects($this->once())->method('setEntityId')->willReturnSelf();
        $urlGenerator->expects($this->once())->method('set')->with('focusField', 'useFor')->willReturnSelf();
        $urlGenerator->method('generateUrl')->willReturn('/management?focusField=useFor');

        $url = $this->extension($urlGenerator, true)->getEditUrl(new Crowdfunding(), 'useFor');

        $this->assertSame('/management?focusField=useFor', $url);
    }

    // The url is written into a page anyone can read, so anyone but an editor gets none rather than a link a controller would refuse
    public function testAVisitorWhoCannotEditGetsNoUrl(): void
    {
        $urlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('setController');

        $this->assertNull($this->extension($urlGenerator, false)->getEditUrl(new Crowdfunding(), 'useFor'));
    }

    // A section rendered without its campaign - the counterparts of a campaign holding none - asks all the same, and answers nothing rather than raising
    public function testNoCampaignGivesNoUrl(): void
    {
        $urlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('setController');

        $this->assertNull($this->extension($urlGenerator, true)->getEditUrl(null, 'counterparts'));
    }

    private function extension(AdminUrlGeneratorInterface $urlGenerator, bool $isEditor): CrowdfundingEditExtension
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');

        // The role itself is what the extension asks for, so the stub answers on it alone: what is asserted below is the url, and that a visitor without it gets none
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isEditor);

        return new CrowdfundingEditExtension($urlGenerator, $configService, $security);
    }
}
