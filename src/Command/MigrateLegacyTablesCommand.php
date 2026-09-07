<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

// One-time rename of the legacy shop_* tables and media folders; raw DBAL, the ORM already expecting the new names, and idempotent
#[AsCommand(
    name: 'c975l:crowdfunding:migrate-legacy-tables',
    description: 'Renames shop_crowdfunding*/shop_lottery* tables and medias/shop/crowdfundings|counterparts for sites installed before the table/folder rename',
)]
class MigrateLegacyTablesCommand extends Command
{
    private const array TABLE_RENAMES = [
        'shop_crowdfunding' => 'crowdfunding_crowdfunding',
        'shop_crowdfunding_contributor' => 'crowdfunding_contributor',
        'shop_crowdfunding_contributor_counterpart' => 'crowdfunding_contributor_counterpart',
        'shop_crowdfunding_counterpart' => 'crowdfunding_counterpart',
        'shop_crowdfunding_news' => 'crowdfunding_news',
        'shop_lottery' => 'crowdfunding_lottery',
        'shop_lottery_prize' => 'crowdfunding_lottery_prize',
        'shop_lottery_ticket' => 'crowdfunding_lottery_ticket',
    ];

    private const array MEDIA_FOLDER_RENAMES = [
        'medias/shop/crowdfundings' => 'medias/crowdfunding/crowdfundings',
        'medias/shop/counterparts' => 'medias/crowdfunding/counterparts',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $renamedTables = $this->renameTables($io);
        $movedFolders = $this->moveMediaFolders($io);
        $updatedRows = $this->rewriteStoredFilenames($io);

        $io->success(\sprintf(
            '%d table(s) renamed, %d media folder(s) moved, %d stored filename(s) rewritten.',
            $renamedTables,
            $movedFolders,
            $updatedRows,
        ));

        return Command::SUCCESS;
    }

    private function renameTables(SymfonyStyle $io): int
    {
        $schemaManager = $this->connection->createSchemaManager();
        $renamed = 0;

        foreach (self::TABLE_RENAMES as $oldName => $newName) {
            if (!$schemaManager->tablesExist([$oldName])) {
                $io->text(\sprintf('Skipped table "%s": does not exist (already renamed, or a fresh install).', $oldName));

                continue;
            }

            if ($schemaManager->tablesExist([$newName])) {
                $io->warning(\sprintf('Skipped table "%s": both "%s" and "%s" exist, resolve manually.', $oldName, $oldName, $newName));

                continue;
            }

            $this->connection->executeStatement(\sprintf('RENAME TABLE `%s` TO `%s`', $oldName, $newName));
            $io->text(\sprintf('Renamed table "%s" to "%s".', $oldName, $newName));
            ++$renamed;
        }

        return $renamed;
    }

    private function moveMediaFolders(SymfonyStyle $io): int
    {
        $filesystem = new Filesystem();
        $publicDir = $this->parameterBag->get('kernel.project_dir') . '/public/';
        $moved = 0;

        foreach (self::MEDIA_FOLDER_RENAMES as $oldPath => $newPath) {
            $oldAbsolute = $publicDir . $oldPath;
            $newAbsolute = $publicDir . $newPath;

            if (!$filesystem->exists($oldAbsolute)) {
                $io->text(\sprintf('Skipped folder "%s": does not exist.', $oldPath));

                continue;
            }

            if ($filesystem->exists($newAbsolute)) {
                $io->warning(\sprintf('Skipped folder "%s": target "%s" already exists, resolve manually.', $oldPath, $newPath));

                continue;
            }

            $filesystem->mkdir(\dirname($newAbsolute));
            $filesystem->rename($oldAbsolute, $newAbsolute);
            $io->text(\sprintf('Moved "%s" to "%s".', $oldPath, $newPath));
            ++$moved;
        }

        return $moved;
    }

    // Rows uploaded before the rename still hold the old path, queried on the already-renamed table
    private function rewriteStoredFilenames(SymfonyStyle $io): int
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['crowdfunding_media'])) {
            return 0;
        }

        $updated = 0;
        foreach (self::MEDIA_FOLDER_RENAMES as $oldPath => $newPath) {
            $affected = $this->connection->executeStatement(
                'UPDATE crowdfunding_media SET name = REPLACE(name, ?, ?) WHERE name LIKE ?',
                [$oldPath . '/', $newPath . '/', $oldPath . '/%'],
            );

            if ($affected > 0) {
                $io->text(\sprintf('Rewrote %d stored filename(s) under "%s".', $affected, $oldPath));
            }
            $updated += $affected;
        }

        return $updated;
    }
}
