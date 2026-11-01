<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Entity;

use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Validator\SafeUrl;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class CrowdfundingVideo extends Media implements VichMediaNamableInterface
{
    #[Vich\UploadableField(mapping: 'block_media', fileNameProperty: 'name', size: 'size')]
    protected ?File $file = null;

    #[ORM\ManyToOne(targetEntity: Crowdfunding::class, inversedBy: 'videos')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Crowdfunding $crowdfunding = null;

    // Despite its name it holds a bare YouTube id, appended to the embed address (see Video.html.twig): SafeUrl lets it through, where Assert\Url would refuse every one of them
    #[ORM\Column(length: 255, nullable: true)]
    #[SafeUrl]
    private ?string $youtubeUrl = null;

    public function getCrowdfunding(): ?Crowdfunding
    {
        return $this->crowdfunding;
    }

    public function setCrowdfunding(?Crowdfunding $crowdfunding): static
    {
        $this->crowdfunding = $crowdfunding;

        return $this;
    }

    public function getYoutubeUrl(): ?string
    {
        return $this->youtubeUrl;
    }

    public function setYoutubeUrl(?string $youtubeUrl): static
    {
        $this->youtubeUrl = $youtubeUrl;

        if (!empty($youtubeUrl)) {
            $this->setUpdatedAt(new \DateTimeImmutable());
            $this->setName('YouTube (' . $youtubeUrl . ')');
        }

        return $this;
    }

    public function getVichMediaPath(): string
    {
        return 'medias/crowdfunding/crowdfundings/' . $this->getCrowdfunding()->getSlug() . '-video';
    }
}
