<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpartMedia;
use c975L\CrowdfundingBundle\Entity\CrowdfundingMedia;
use c975L\CrowdfundingBundle\Entity\Media;
use c975L\CrowdfundingBundle\Management\CrowdfundingFilesHealthCheckProvider;
use c975L\CrowdfundingBundle\Repository\MediaRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class CrowdfundingFilesHealthCheckProviderTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/crowdfunding-files-health-check-test-' . uniqid();
        new Filesystem()->mkdir($this->projectDir . '/public');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    /**
     * @param array<int, array{0: Media, 1: bool}> $rows the media and whether its file sits on disk
     */
    private function createProvider(array $rows): CrowdfundingFilesHealthCheckProvider
    {
        $medias = [];
        foreach ($rows as [$media, $onDisk]) {
            if ($onDisk) {
                $path = $this->projectDir . '/public/' . $media->getName();
                new Filesystem()->mkdir(\dirname($path));
                file_put_contents($path, 'file');
            }

            $medias[] = $media;
        }

        $mediaRepository = $this->createStub(MediaRepository::class);
        $mediaRepository->method('findWithFilename')->willReturn($medias);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('https://example.com');

        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('setEntityId')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/crowdfunding/edit');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []) => $id . '|' . implode('', $params)
        );

        return new CrowdfundingFilesHealthCheckProvider(
            $mediaRepository,
            $adminUrlGenerator,
            $configService,
            $translator,
            $this->projectDir,
        );
    }

    private function createCampaign(): Crowdfunding
    {
        $campaign = new Crowdfunding()->setTitle('Les Triados, tome 2');
        new \ReflectionProperty(Crowdfunding::class, 'id')->setValue($campaign, 1);

        return $campaign;
    }

    private function createCampaignMedia(string $filename): CrowdfundingMedia
    {
        return new CrowdfundingMedia()->setName($filename)->setCrowdfunding($this->createCampaign());
    }

    private function createCounterpartMedia(string $filename): CrowdfundingCounterpartMedia
    {
        $counterpart = new CrowdfundingCounterpart()->setCrowdfunding($this->createCampaign());

        return new CrowdfundingCounterpartMedia()->setName($filename)->setCrowdfundingCounterpart($counterpart);
    }

    public function testGetKind(): void
    {
        $this->assertSame('files-crowdfunding', $this->createProvider([])->getKind());
    }

    public function testACampaignDeclaringNoFileReportsNothing(): void
    {
        $this->assertSame([], $this->createProvider([])->runChecks());
    }

    public function testADeclaredFileMissingFromTheServerIsAnError(): void
    {
        $rows = $this->createProvider([[$this->createCampaignMedia('medias/crowdfunding/triados.webp'), false]])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
        $this->assertSame('https://example.com/medias/crowdfunding/triados.webp', $rows[0]['url']);
        $this->assertSame('Les Triados, tome 2', $rows[0]['label']);
        $this->assertSame('/management/crowdfunding/edit', $rows[0]['editUrl']);
    }

    public function testAFileInPlaceStillGetsItsRow(): void
    {
        $rows = $this->createProvider([[$this->createCampaignMedia('medias/crowdfunding/triados.webp'), true]])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
    }

    // A counterpart has no screen of its own: its picture is re-uploaded from the campaign it belongs to, and is named after it
    public function testACounterpartPictureIsTracedBackToItsCampaign(): void
    {
        $rows = $this->createProvider([[$this->createCounterpartMedia('medias/crowdfunding/counterpart.webp'), true]])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame('Les Triados, tome 2', $rows[0]['label']);
        $this->assertSame('/management/crowdfunding/edit', $rows[0]['editUrl']);
    }
}
