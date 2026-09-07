<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Command;

use c975L\CrowdfundingBundle\Command\MigrateLegacyTablesCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

// The one-time step a site installed before the tables were renamed runs after updating. It is idempotent by design - re-running it, or running it on a fresh install, has to be a no-op, since that is what the README tells people to do
class MigrateLegacyTablesCommandTest extends TestCase
{
    private string $projectDir;

    #[\Override]
    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/crowdfunding-migrate-' . bin2hex(random_bytes(4));
        new Filesystem()->mkdir($this->projectDir . '/public');
    }

    #[\Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    // Eight tables carrying ShopBundle's prefix, which the ORM no longer knows about the moment this version is installed
    public function testItRenamesEveryLegacyTableItFinds(): void
    {
        $statements = [];
        $tester = $this->createTester(['shop_crowdfunding', 'shop_lottery'], $statements);

        $tester->execute([]);

        $this->assertSame([
            'RENAME TABLE `shop_crowdfunding` TO `crowdfunding_crowdfunding`',
            'RENAME TABLE `shop_lottery` TO `crowdfunding_lottery`',
        ], $statements);
        $this->assertStringContainsString('2 table(s) renamed', $this->display($tester));
    }

    // A fresh install, or a second run: nothing to rename, and the command still answers success
    public function testItRenamesNothingOnAFreshInstall(): void
    {
        $statements = [];
        $tester = $this->createTester([], $statements);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSame([], $statements);
        $this->assertStringContainsString('0 table(s) renamed', $this->display($tester));
    }

    // Both names present is a half-finished migration: renaming over the new table would lose whatever it already holds, so it is left for a human
    public function testItRefusesToRenameOverATableThatAlreadyExists(): void
    {
        $statements = [];
        $tester = $this->createTester(['shop_crowdfunding', 'crowdfunding_crowdfunding'], $statements);

        $tester->execute([]);

        $this->assertSame([], $statements);
        $this->assertStringContainsString('resolve manually', $this->display($tester));
    }

    // The uploads live under public/, and the folder moves with the table
    public function testItMovesTheLegacyMediaFolders(): void
    {
        new Filesystem()->mkdir($this->projectDir . '/public/medias/shop/crowdfundings');
        file_put_contents($this->projectDir . '/public/medias/shop/crowdfundings/photo.webp', 'x');

        $statements = [];
        $tester = $this->createTester([], $statements);

        $tester->execute([]);

        $this->assertFileExists($this->projectDir . '/public/medias/crowdfunding/crowdfundings/photo.webp');
        $this->assertDirectoryDoesNotExist($this->projectDir . '/public/medias/shop/crowdfundings');
        $this->assertStringContainsString('1 media folder(s) moved', $this->display($tester));
    }

    // Same rule as the tables: a target already there is somebody's half-done move, not this command's to finish
    public function testItRefusesToMoveOntoAFolderThatAlreadyExists(): void
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->projectDir . '/public/medias/shop/crowdfundings');
        $filesystem->mkdir($this->projectDir . '/public/medias/crowdfunding/crowdfundings');

        $statements = [];
        $tester = $this->createTester([], $statements);

        $tester->execute([]);

        $this->assertDirectoryExists($this->projectDir . '/public/medias/shop/crowdfundings');
        $this->assertStringContainsString('resolve manually', $this->display($tester));
    }

    // The rows keep the path they were uploaded under: moved on disk and left alone in the database, every picture of a campaign 404s
    public function testItRewritesTheStoredFilenames(): void
    {
        $statements = [];
        $tester = $this->createTester(['crowdfunding_media'], $statements, updatedRows: 3);

        $tester->execute([]);

        $this->assertStringContainsString('6 stored filename(s) rewritten', $this->display($tester));
    }

    // A site whose media table is not there yet has nothing to rewrite, and the command says so rather than throwing
    public function testItRewritesNothingWithoutTheMediaTable(): void
    {
        $statements = [];
        $tester = $this->createTester([], $statements);

        $tester->execute([]);

        $this->assertStringContainsString('0 stored filename(s) rewritten', $this->display($tester));
    }

    // The success block is wrapped and padded by SymfonyStyle, so its sentence only reads back whole once the whitespace is collapsed
    private function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    /**
     * @param list<string> $existingTables
     * @param list<string> $statements     filled with the SQL the command runs, in order
     */
    private function createTester(array $existingTables, ?array &$statements, int $updatedRows = 0): CommandTester
    {
        $statements ??= [];

        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturnCallback(static fn (array $names): bool => [] === array_diff($names, $existingTables));

        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('executeStatement')->willReturnCallback(static function (string $sql, array $parameters = []) use (&$statements, $updatedRows): int {
            if ([] === $parameters) {
                $statements[] = $sql;

                return 0;
            }

            return $updatedRows;
        });

        return new CommandTester(new MigrateLegacyTablesCommand($connection, $this->createParameterBag()));
    }

    private function createParameterBag(): ParameterBagInterface
    {
        return new ParameterBag(['kernel.project_dir' => $this->projectDir]);
    }
}
