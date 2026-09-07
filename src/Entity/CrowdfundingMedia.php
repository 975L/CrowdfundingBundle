<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Entity;

use c975L\UiBundle\Contract\VichImageResizableInterface;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class CrowdfundingMedia extends Media implements VichImageResizableInterface, VichMediaNamableInterface
{
    #[Vich\UploadableField(mapping: 'block_media', fileNameProperty: 'name', size: 'size')]
    protected ?File $file = null;

    #[ORM\ManyToOne(targetEntity: Crowdfunding::class, inversedBy: 'medias')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Crowdfunding $crowdfunding = null;

    public function getCrowdfunding(): ?Crowdfunding
    {
        return $this->crowdfunding;
    }

    public function setCrowdfunding(?Crowdfunding $crowdfunding): static
    {
        $this->crowdfunding = $crowdfunding;

        return $this;
    }

    public function getImageWidth(): int
    {
        return 600;
    }

    public function getVichMediaPath(): string
    {
        return 'medias/crowdfunding/crowdfundings/' . $this->getCrowdfunding()->getSlug();
    }
}
