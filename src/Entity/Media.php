<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Entity;

use c975L\CrowdfundingBundle\Repository\MediaRepository;
use c975L\UiBundle\Entity\Trait\VichMediaTrait;
use Doctrine\ORM\Mapping as ORM;

// Its own SINGLE_TABLE hierarchy, sharing only the trait with the other bundles' Media, never the table
#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'crowdfunding_media')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'owner_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'crowdfunding' => CrowdfundingMedia::class,
    'crowdfunding_counterpart' => CrowdfundingCounterpartMedia::class,
    'crowdfunding_video' => CrowdfundingVideo::class,
    'lottery_video' => LotteryVideo::class,
])]
abstract class Media
{
    use VichMediaTrait;

    // What a file's type is read from, the hierarchy holding no column for it: the extension is what an upload already carries, and a type stored beside it would be one more thing to keep true
    private const array MIME_TYPES = [
        'avif' => 'image/avif',
        'gif' => 'image/gif',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'mp4' => 'video/mp4',
        'ogg' => 'video/ogg',
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'webm' => 'video/webm',
        'webp' => 'image/webp',
    ];

    public function getMimeType(): string
    {
        $extension = strtolower(pathinfo((string) $this->getName(), PATHINFO_EXTENSION));

        return self::MIME_TYPES[$extension] ?? 'application/octet-stream';
    }

    // What UiBundle's own components read off any media they are handed - the slider, the image (see Slider.html.twig). This hierarchy holds none of these texts, so each answers for want of one rather than raising: a campaign's pictures are shown under a heading of their own, and are described by the campaign itself
    public function getAlt(): ?string
    {
        return null;
    }

    public function getLabel(): ?string
    {
        return null;
    }

    public function getWidth(): ?string
    {
        return null;
    }

    public function getHeight(): ?string
    {
        return null;
    }

    /** @return string[] */
    public function getCssClasses(): array
    {
        return [];
    }

    public function isAbove(): bool
    {
        return false;
    }

    public function getDescription(): ?string
    {
        return null;
    }

    public function getCredits(): ?string
    {
        return null;
    }

    // A campaign's own pictures carry no rights notice: the mention belongs to the media library, where a picture comes from somewhere else
    public function isRightsReserved(): bool
    {
        return false;
    }

    // The dimensions the file itself has, read by UiBundle to reserve the image's room before it loads. This hierarchy stores none, so the components fall back on their own defaults rather than on a wrong number
    public function getIntrinsicWidth(): ?int
    {
        return null;
    }

    public function getIntrinsicHeight(): ?int
    {
        return null;
    }

    // The address a media stands for when it is not a file of this site's own - never the case here, every campaign media being uploaded
    public function getUrl(): ?string
    {
        return null;
    }
}
