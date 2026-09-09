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
    // What the file is for, set by the field it was dropped on rather than picked from a list (see Crowdfunding::addCover(), addHero() and addSlide()). One file, one use: a campaign wanting the same image as its cover and in its slider uploads it on both fields, so moving one never moves the other
    public const string KIND_COVER = 'cover';
    public const string KIND_HERO = 'hero';
    public const string KIND_SLIDE = 'slide';

    #[Vich\UploadableField(mapping: 'block_media', fileNameProperty: 'name', size: 'size')]
    protected ?File $file = null;

    // Held here and not on the hierarchy's abstract Media: a video and a counterpart's image are each the only file of their kind on their owner, and have nothing to be told apart from
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $kind = null;

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

    public function getKind(): ?string
    {
        return $this->kind;
    }

    public function setKind(?string $kind): static
    {
        $this->kind = $kind;

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
