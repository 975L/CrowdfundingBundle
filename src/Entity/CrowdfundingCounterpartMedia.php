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
class CrowdfundingCounterpartMedia extends Media implements VichImageResizableInterface, VichMediaNamableInterface
{
    #[Vich\UploadableField(mapping: 'block_media', fileNameProperty: 'name', size: 'size')]
    protected ?File $file = null;

    #[ORM\OneToOne(mappedBy: 'media', cascade: ['persist', 'remove'])]
    private ?CrowdfundingCounterpart $crowdfundingCounterpart = null;

    public function getCrowdfundingCounterpart(): ?CrowdfundingCounterpart
    {
        return $this->crowdfundingCounterpart;
    }

    public function setCrowdfundingCounterpart(?CrowdfundingCounterpart $crowdfundingCounterpart): static
    {
        $this->crowdfundingCounterpart = $crowdfundingCounterpart;

        return $this;
    }

    public function getImageWidth(): int
    {
        return 300;
    }

    public function getVichMediaPath(): string
    {
        return 'medias/crowdfunding/counterparts/' . $this->getCrowdfundingCounterpart()->getSlug();
    }
}
