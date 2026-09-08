<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Controller\Management\CrowdfundingCrudController;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpartMedia;
use c975L\CrowdfundingBundle\Entity\CrowdfundingMedia;
use c975L\CrowdfundingBundle\Entity\Media;
use c975L\CrowdfundingBundle\Repository\MediaRepository;
use c975L\UiBundle\Management\AbstractDeclaredFilesHealthCheckProvider;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

// The files this bundle's own rows declare: the pictures of a campaign and of its counterparts. Everything the check does is in the parent (see UiBundle's AbstractDeclaredFilesHealthCheckProvider), this only names what to look for - a campaign is read by people deciding whether to pay, so a picture missing from the server is a tier nobody can see what they are joining
class CrowdfundingFilesHealthCheckProvider extends AbstractDeclaredFilesHealthCheckProvider
{
    // Named here rather than restated as a literal wherever a row of this kind is picked out
    public const string KIND = 'files-crowdfunding';

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        ConfigServiceInterface $configService,
        TranslatorInterface $translator,
        #[Autowire(param: 'kernel.project_dir')]
        string $projectDir,
    ) {
        parent::__construct($configService, $translator, $projectDir);
    }

    public function getKind(): string
    {
        return self::KIND;
    }

    protected function declaredFiles(): iterable
    {
        foreach ($this->mediaRepository->findWithFilename() as $media) {
            yield [
                'filename' => (string) $media->getName(),
                'label' => $this->label($media),
                'editUrl' => $this->editUrl($media),
                // Every picture of this bundle is served from public/: none of them is a file a buyer is sent privately
                'directory' => self::PUBLIC_DIRECTORY,
            ];
        }
    }

    // The campaign is what names the row: the medias themselves carry no title, and several campaigns hold a picture called the same thing
    private function label(Media $media): string
    {
        $campaign = $this->campaignOf($media);
        $title = null === $campaign ? '' : (string) $campaign->getTitle();

        return '' === $title ? (string) $media->getName() : $title;
    }

    // Every media of this bundle is re-uploaded from its campaign's own screen, whether it hangs off the campaign or off one of its counterparts
    private function editUrl(Media $media): ?string
    {
        $id = $this->campaignOf($media)?->getId();

        return null === $id ? null : $this->adminUrlGenerator
            ->unsetAll()
            ->setController(CrowdfundingCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($id)
            ->generateUrl()
        ;
    }

    // A counterpart has no screen of its own: its picture is traced back to the campaign it belongs to
    private function campaignOf(Media $media): ?Crowdfunding
    {
        if ($media instanceof CrowdfundingMedia) {
            return $media->getCrowdfunding();
        }

        if ($media instanceof CrowdfundingCounterpartMedia) {
            return $media->getCrowdfundingCounterpart()?->getCrowdfunding();
        }

        return null;
    }
}
