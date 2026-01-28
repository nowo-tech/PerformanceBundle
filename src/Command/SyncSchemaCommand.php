<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sync database schema with entity metadata.
 *
 * Compares current DB (tables and column types) with entities: adds missing columns,
 * alters differing columns, and optionally drops columns that no longer exist in entities.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(
    name: 'nowo:performance:sync-schema',
    description: 'Sync database schema with entity metadata (add missing, alter differing, optionally drop obsolete)',
)]
final class SyncSchemaCommand extends Command
{
    /**
     * Creates a new instance.
     *
     * @param CreateTableCommand         $createTableCommand         Command to create/update the main table
     * @param CreateRecordsTableCommand  $createRecordsTableCommand  Command to create/update the records table
     */
    public function __construct(
        private readonly CreateTableCommand $createTableCommand,
        private readonly CreateRecordsTableCommand $createRecordsTableCommand,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(<<<'HELP'
The <info>%command.name%</info> command syncs the database schema with the entity metadata.

It runs the same logic as <info>nowo:performance:create-table --update</info> and
<info>nowo:performance:create-records-table --update</info> in one go:

  1. <strong>Add</strong> columns that exist in the entity but not in the database
  2. <strong>Alter</strong> columns whose type, nullable or default differ from the entity
  3. <strong>Drop</strong> (only with <comment>--drop-obsolete</comment>) columns that exist in the database
     but not in the entity

This is useful after changing entity mappings: run sync-schema to bring the database
in line with the code without generating migrations manually.

<info>php %command.full_name%</info>

Sync without dropping any columns (safe, additive + alter only):
<info>php %command.full_name%</info>

Sync and drop columns that are no longer in the entity:
<info>php %command.full_name% --drop-obsolete</info>

The primary key column <comment>id</comment> is never dropped.
HELP
        );

        $this->addOption(
            'drop-obsolete',
            null,
            InputOption::VALUE_NONE,
            'Drop columns that exist in DB but not in entity (routes_data and routes_data_records)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dropObsolete = (bool) $input->getOption('drop-obsolete');

        $io->title('Nowo Performance Bundle - Sync Schema');

        $io->text([
            'This will sync both tables with entity metadata:',
            '  • routes_data (RouteData)',
            '  • routes_data_records (RouteDataRecord)',
            $dropObsolete ? '  • Obsolete columns will be dropped.' : '  • Obsolete columns will be kept (use --drop-obsolete to remove them).',
            '',
        ]);

        $createTableInput = new ArrayInput([
            '--update' => true,
            '--drop-obsolete' => $dropObsolete,
        ]);
        $createTableInput->setInteractive(false);

        $createRecordsInput = new ArrayInput([
            '--update' => true,
            '--drop-obsolete' => $dropObsolete,
        ]);
        $createRecordsInput->setInteractive(false);

        $exitMain = $this->createTableCommand->run($createTableInput, $output);
        if (Command::SUCCESS !== $exitMain) {
            $io->error('Sync failed while updating routes_data table.');

            return $exitMain;
        }

        $io->newLine();

        $exitRecords = $this->createRecordsTableCommand->run($createRecordsInput, $output);
        if (Command::SUCCESS !== $exitRecords) {
            $io->error('Sync failed while updating routes_data_records table.');

            return $exitRecords;
        }

        $io->newLine();
        $io->success('Schema sync completed for both tables.');

        return Command::SUCCESS;
    }
}
