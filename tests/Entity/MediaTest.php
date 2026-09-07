<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Entity;

use c975L\CrowdfundingBundle\Entity\CrowdfundingMedia;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MediaTest extends TestCase
{
    // The hierarchy holds no type column: what UiBundle's components read is deduced from the name the upload carries
    #[DataProvider('provideNames')]
    public function testGetMimeTypeReadsTheExtensionOfTheStoredName(string $name, string $expected): void
    {
        $media = new CrowdfundingMedia();
        $media->setName($name);

        $this->assertSame($expected, $media->getMimeType());
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideNames(): iterable
    {
        yield 'jpg' => ['poster.jpg', 'image/jpeg'];
        yield 'jpeg' => ['poster.jpeg', 'image/jpeg'];
        yield 'webp' => ['poster.webp', 'image/webp'];
        yield 'mp4' => ['draw.mp4', 'video/mp4'];
        yield 'pdf' => ['budget.pdf', 'application/pdf'];
        yield 'uppercase extension' => ['POSTER.PNG', 'image/png'];
        yield 'unknown extension' => ['archive.zip', 'application/octet-stream'];
        yield 'no extension' => ['poster', 'application/octet-stream'];
    }

    // A media reaching a component before its file was uploaded still answers a type rather than throwing on a null name
    public function testGetMimeTypeFallsBackOnAMediaWithNoName(): void
    {
        $this->assertSame('application/octet-stream', new CrowdfundingMedia()->getMimeType());
    }

    // Every text UiBundle's slider and image read: this hierarchy stores none, so each answers for want of one and the components fall back on their own defaults
    public function testTheTextsAndDimensionsUiBundleReadsAnswerForWantOfOne(): void
    {
        $media = new CrowdfundingMedia();

        $this->assertNull($media->getAlt());
        $this->assertNull($media->getLabel());
        $this->assertNull($media->getWidth());
        $this->assertNull($media->getHeight());
        $this->assertNull($media->getDescription());
        $this->assertNull($media->getCredits());
        $this->assertNull($media->getIntrinsicWidth());
        $this->assertNull($media->getIntrinsicHeight());
        $this->assertNull($media->getUrl());
        $this->assertSame([], $media->getCssClasses());
        $this->assertFalse($media->isAbove());
        $this->assertFalse($media->isRightsReserved());
    }
}
