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
use c975L\UiBundle\Video\VideoPlatform;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
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

    // A bare YouTube id, what the field held when YouTube was its only platform: read as that video's address, so a campaign saved then keeps playing
    private const string LEGACY_YOUTUBE_ID = '/^[A-Za-z0-9_-]{11}$/';

    // The address of the video on any platform UiBundle's VideoPlatform declares (YouTube, Vimeo, Dailymotion, TikTok), framed through its privacy-respecting embed (see Video.html.twig). Its column keeps the name it had when YouTube was the only one, so no site migrates anything
    #[ORM\Column(name: 'youtube_url', length: 255, nullable: true)]
    #[SafeUrl]
    #[Assert\Length(max: 255)]
    private ?string $videoUrl = null;

    public function getCrowdfunding(): ?Crowdfunding
    {
        return $this->crowdfunding;
    }

    public function setCrowdfunding(?Crowdfunding $crowdfunding): static
    {
        $this->crowdfunding = $crowdfunding;

        return $this;
    }

    public function getVideoUrl(): ?string
    {
        return null !== $this->videoUrl && 1 === preg_match(self::LEGACY_YOUTUBE_ID, $this->videoUrl)
            ? 'https://www.youtube.com/watch?v=' . $this->videoUrl
            : $this->videoUrl;
    }

    public function setVideoUrl(?string $videoUrl): static
    {
        $this->videoUrl = $videoUrl;

        if (!empty($videoUrl)) {
            $this->setUpdatedAt(new \DateTimeImmutable());
            $this->setName((VideoPlatform::resolve($this->getVideoUrl())?->platform->name ?? 'Video') . ' (' . $videoUrl . ')');
        }

        return $this;
    }

    // The privacy-respecting embed of the platform the address belongs to, null for one VideoPlatform does not declare - a row saved before the address was checked is never framed on the strength of being stored
    public function getVideoEmbedUrl(): ?string
    {
        return VideoPlatform::resolve($this->getVideoUrl())?->embedUrl();
    }

    // Only a platform VideoPlatform declares is framed: any other address would put a page nobody vetted inside the campaign
    #[Assert\Callback]
    public function validateVideoUrl(ExecutionContextInterface $context): void
    {
        if (!empty($this->videoUrl) && null === VideoPlatform::resolve($this->getVideoUrl())) {
            $context->buildViolation('label.video_url_unknown')
                ->setTranslationDomain('crowdfunding')
                ->atPath('videoUrl')
                ->addViolation();
        }
    }

    public function getVichMediaPath(): string
    {
        return 'medias/crowdfunding/crowdfundings/' . $this->getCrowdfunding()->getSlug() . '-video';
    }
}
