<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Entity;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingVideo;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Validator\SafeUrl;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class CrowdfundingVideoTest extends TestCase
{
    // The back-office writes it, and a row saved before may hold a bare YouTube id: SafeUrl and not Url, which would refuse every id
    public function testTheVideoFieldDeclaresSafeUrl(): void
    {
        $this->assertCount(1, new \ReflectionProperty(CrowdfundingVideo::class, 'videoUrl')->getAttributes(SafeUrl::class));
    }

    // The column keeps its name, so no site migrates anything
    public function testTheColumnKeepsItsName(): void
    {
        $column = new \ReflectionProperty(CrowdfundingVideo::class, 'videoUrl')->getAttributes(\Doctrine\ORM\Mapping\Column::class)[0]->getArguments();

        $this->assertSame('youtube_url', $column['name']);
    }

    // Any declared platform, and a bare id from when YouTube was the only one read as that video
    public function testTheAddressIsReadOnAnyPlatformAndALegacyIdAsYoutube(): void
    {
        $video = new CrowdfundingVideo()->setVideoUrl('https://vimeo.com/76979871');
        $this->assertSame('https://vimeo.com/76979871', $video->getVideoUrl());
        $this->assertStringStartsWith('Vimeo (', (string) $video->getName());

        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', new CrowdfundingVideo()->setVideoUrl('dQw4w9WgXcQ')->getVideoUrl());
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', new CrowdfundingVideo()->setVideoUrl('dQw4w9WgXcQ')->getVideoEmbedUrl());
        $this->assertNull(new CrowdfundingVideo()->setVideoUrl('https://example.com/video.html')->getVideoEmbedUrl());
    }

    // Only a declared platform is framed
    public function testAnAddressOfNoDeclaredPlatformIsRefused(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $this->assertCount(1, $validator->validate(new CrowdfundingVideo()->setVideoUrl('https://example.com/video.html')));
        $this->assertCount(0, $validator->validate(new CrowdfundingVideo()->setVideoUrl('https://www.dailymotion.com/video/x8abc12')));
    }

    // Regression: implementing no naming contract made UiMediaNamer throw on any upload
    public function testImplementsVichMediaNamableInterface(): void
    {
        $this->assertInstanceOf(VichMediaNamableInterface::class, new CrowdfundingVideo());
    }

    public function testGetVichMediaPathUsesTheParentCrowdfundingSlugWithAVideoSuffix(): void
    {
        $crowdfunding = new Crowdfunding();
        $crowdfunding->setSlug('save-the-cats');

        $video = new CrowdfundingVideo();
        $video->setCrowdfunding($crowdfunding);

        $this->assertSame('medias/crowdfunding/crowdfundings/save-the-cats-video', $video->getVichMediaPath());
    }
}
