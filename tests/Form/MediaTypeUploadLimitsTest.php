<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Form;

use c975L\CrowdfundingBundle\Form\CrowdfundingCounterpartMediaType;
use c975L\CrowdfundingBundle\Form\CrowdfundingMediaType;
use c975L\CrowdfundingBundle\Form\CrowdfundingVideoType;
use c975L\CrowdfundingBundle\Form\LotteryVideoType;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Validator\Constraints\File;
use Vich\UploaderBundle\Form\Type\VichFileType;
use Vich\UploaderBundle\Form\Type\VichImageType;

// The four upload types, whose ceiling is the only thing standing between a campaign's screen and a request PHP refuses before Symfony ever sees it
class MediaTypeUploadLimitsTest extends FormFieldsTestCase
{
    /** @return iterable<string, array{AbstractType, string, int}> */
    public static function uploadTypes(): iterable
    {
        yield 'campaign picture' => [new CrowdfundingMediaType(), VichImageType::class, 20_000_000];
        yield 'counterpart picture' => [new CrowdfundingCounterpartMediaType(), VichImageType::class, 20_000_000];
        yield 'campaign video' => [new CrowdfundingVideoType(), VichFileType::class, 500_000_000];
        yield 'lottery video' => [new LotteryVideoType(), VichFileType::class, 500_000_000];
    }

    #[DataProvider('uploadTypes')]
    public function testEachUploadFieldDeclaresItsCeiling(AbstractType $type, string $expectedType, int $maxSize): void
    {
        $file = $this->buildFields($type)['file'];

        $this->assertSame($expectedType, $file['type']);
        $this->assertInstanceOf(File::class, $file['options']['constraints'][0]);
        $this->assertSame($maxSize, $file['options']['constraints'][0]->maxSize);
    }

    // The file is never required: the row is written empty by the listener, and the upload fills it afterwards
    #[DataProvider('uploadTypes')]
    public function testTheFileIsOptionalAndDeletable(AbstractType $type, string $expectedType, int $maxSize): void
    {
        $file = $this->buildFields($type)['file'];

        $this->assertFalse($file['options']['required']);
        $this->assertTrue($file['options']['allow_delete']);
    }

    // Three container formats and nothing else: a browser plays them all, and anything else uploaded would need transcoding the site cannot do
    public function testTheVideosOnlyAcceptWhatABrowserPlays(): void
    {
        foreach ([new CrowdfundingVideoType(), new LotteryVideoType()] as $type) {
            $constraint = $this->buildFields($type)['file']['options']['constraints'][0];

            $this->assertSame(['video/mp4', 'video/webm', 'video/ogg'], $constraint->mimeTypes);
        }
    }

    // A campaign's pictures are arranged by hand, and the listener only places the ones left without a rank
    public function testTheCampaignPictureCarriesItsPosition(): void
    {
        $this->assertArrayHasKey('position', $this->buildFields(new CrowdfundingMediaType()));
    }
}
