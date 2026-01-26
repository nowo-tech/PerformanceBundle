<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Command to create the performance metrics table.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
#[AsCommand(
    name: 'nowo:performance:create-table',
    description: 'Create the performance metrics database table',
    help: <<<'HELP'
The <info>%command.name%</info> command creates the performance metrics database table with all necessary columns and indexes.

This command will:
  1. Check if the table already exists
  2. Create the table if it doesn't exist (or if --force is used)
  3. Create all necessary indexes for optimal query performance

<info>php %command.full_name%</info>

To add missing columns to existing table (safe, preserves data):
<info>php %command.full_name% --update</info>

To force recreation of the table (WARNING: This will drop existing data):
<info>php %command.full_name% --force</info>

Alternatively, you can use Doctrine's standard commands:
<info>php bin/console doctrine:schema:update --force</info>
or
<info>php bin/console doctrine:migrations:diff</info>
<info>php bin/console doctrine:migrations:migrate</info>

The table name and connection can be configured in <comment>config/packages/nowo_performance.yaml</comment>.
HELP
)]
final class CreateTableCommand extends Command
{
    /**
     * Constructor.
     *
     * @param ManagerRegistry $registry Doctrine registry
     * @param string $connectionName The name of the Doctrine connection to use
     * @param string $tableName The configured table name
     */
    public function __construct(
        private readonly ManagerRegistry $registry,
        #[Autowire('%nowo_performance.connection%')]
        private readonly string $connectionName,
        #[Autowire('%nowo_performance.table_name%')]
        private readonly string $tableName
    ) {
        parent::__construct();
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force table creation even if it already exists')
            ->addOption('update', 'u', InputOption::VALUE_NONE, 'Add missing columns to existing table without losing data');
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input The input interface
     * @param OutputInterface $output The output interface
     * @return int Command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Nowo Performance Bundle - Create Table');

        try {
            $connection = $this->registry->getConnection($this->connectionName);
            $schemaManager = $connection->createSchemaManager();
            
            // Get the actual table name from entity metadata (after TableNameSubscriber has processed it)
            $entityManager = $this->registry->getManager($this->connectionName);
            $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteData');
            $actualTableName = $metadata->getTableName();
            
            $tableExists = $schemaManager->tablesExist([$actualTableName]);

            if ($tableExists && !$input->getOption('force') && !$input->getOption('update')) {
                $io->warning(sprintf('Table "%s" already exists.', $actualTableName));
                $io->note('Use --update to add missing columns without losing data.');
                $io->note('Use --force to drop and recreate the table (WARNING: This will delete all data).');
                $io->note('Alternatively, use Doctrine migrations to update the schema:');
                $io->text([
                    '  php bin/console doctrine:migrations:diff',
                    '  php bin/console doctrine:migrations:migrate',
                ]);

                return Command::SUCCESS;
            }

            if ($tableExists && $input->getOption('update')) {
                $io->section('Updating Table Schema');
                $io->text([
                    sprintf('Table name: <info>%s</info>', $actualTableName),
                    sprintf('Connection: <info>%s</info>', $this->connectionName),
                ]);

                $this->updateTableSchema($entityManager, $io);

                $io->success(sprintf('Table "%s" updated successfully!', $actualTableName));
                return Command::SUCCESS;
            }

            if ($tableExists && $input->getOption('force')) {
                $io->warning(sprintf('Dropping existing table "%s"...', $actualTableName));
                $schemaManager->dropTable($actualTableName);
                $io->success('Table dropped.');
            }

            $io->section('Creating Table');
            $io->text([
                sprintf('Table name: <info>%s</info>', $actualTableName),
                sprintf('Connection: <info>%s</info>', $this->connectionName),
            ]);

            // Use Doctrine's schema tool to create the table
            $this->createTableUsingSchemaTool($entityManager, $io);

            $io->success(sprintf('Table "%s" created successfully!', $actualTableName));
            $io->note('The table is now ready to store performance metrics.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to create table: %s', $e->getMessage()));
            $io->note('You can also use Doctrine\'s standard commands:');
            $io->text([
                '  php bin/console doctrine:schema:update --force',
                '  or',
                '  php bin/console doctrine:migrations:diff',
                '  php bin/console doctrine:migrations:migrate',
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Create the table using Doctrine's schema tool.
     *
     * @param EntityManagerInterface $entityManager The entity manager
     * @param SymfonyStyle $io The Symfony style output
     * @return void
     */
    private function createTableUsingSchemaTool(EntityManagerInterface $entityManager, SymfonyStyle $io): void
    {
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        // Filter to only RouteData entity
        $routeDataMetadata = array_filter($metadata, function ($meta) {
            return $meta->getName() === 'Nowo\PerformanceBundle\Entity\RouteData';
        });

        if (empty($routeDataMetadata)) {
            throw new \RuntimeException('RouteData entity metadata not found.');
        }

        $io->text('Generating SQL statements...');
        $sql = $schemaTool->getCreateSchemaSql($routeDataMetadata);

        if (empty($sql)) {
            $io->warning('No SQL statements to execute. Table might already exist.');
            return;
        }

        $connection = $entityManager->getConnection();
        foreach ($sql as $statement) {
            $io->text(sprintf('  <comment>%s</comment>', $statement));
            $connection->executeStatement($statement);
        }

        $io->text('✓ Table and indexes created.');
    }

    /**
     * Update the table schema by adding missing columns.
     *
     * @param EntityManagerInterface $entityManager The entity manager
     * @param SymfonyStyle $io The Symfony style output
     * @return void
     */
    private function updateTableSchema(EntityManagerInterface $entityManager, SymfonyStyle $io): void
    {
        $connection = $entityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();
        
        // Get the actual table name from entity metadata (after TableNameSubscriber has processed it)
        $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteData');
        $actualTableName = $metadata->getTableName();
        
        // Verify table exists
        if (!$schemaManager->tablesExist([$actualTableName])) {
            $io->error(sprintf('Table "%s" does not exist. Use the create command without --update to create it.', $actualTableName));
            return;
        }
        
        $table = $schemaManager->introspectTable($actualTableName);
        $existingColumns = array_map(function ($column) {
            return strtolower($column->getName());
        }, $table->getColumns());

        // Get expected columns from entity metadata
        $expectedColumns = [];
        foreach ($metadata->getFieldNames() as $fieldName) {
            $columnName = $metadata->getColumnName($fieldName);
            $expectedColumns[strtolower($columnName)] = [
                'field' => $fieldName,
                'column' => $columnName,
                'type' => $metadata->getTypeOfField($fieldName),
                'nullable' => !$metadata->isNullable($fieldName),
                'options' => $metadata->getFieldMapping($fieldName)['options'] ?? [],
            ];
        }

        $columnsToAdd = [];
        foreach ($expectedColumns as $columnName => $columnInfo) {
            if (!in_array(strtolower($columnName), $existingColumns, true)) {
                $columnsToAdd[$columnName] = $columnInfo;
            }
        }

        if (empty($columnsToAdd)) {
            $io->success('All columns are already present. No updates needed.');
            return;
        }

        $io->text(sprintf('Found <info>%d</info> missing column(s) to add:', count($columnsToAdd)));
        $io->text(sprintf('Using table name: <info>%s</info>', $actualTableName));

        $platform = $connection->getDatabasePlatform();
        foreach ($columnsToAdd as $columnName => $columnInfo) {
            $io->text(sprintf('  - <comment>%s</comment> (%s)', $columnName, $columnInfo['type']));

            // Build ALTER TABLE statement using the actual table name from metadata
            $columnDefinition = $this->getColumnDefinition($columnInfo, $platform);
            $sql = sprintf(
                'ALTER TABLE %s ADD COLUMN %s %s',
                $platform->quoteIdentifier($actualTableName),
                $platform->quoteIdentifier($columnName),
                $columnDefinition
            );

            try {
                $connection->executeStatement($sql);
                $io->text(sprintf('  ✓ Added column <info>%s</info>', $columnName));
            } catch (\Exception $e) {
                $io->error(sprintf('  ✗ Failed to add column %s: %s', $columnName, $e->getMessage()));
                throw $e;
            }
        }

        // Add missing indexes
        $this->addMissingIndexes($entityManager, $io, $table);
    }

    /**
     * Get SQL column definition for a column.
     *
     * @param array<string, mixed> $columnInfo Column information
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform Database platform
     * @return string SQL column definition
     */
    private function getColumnDefinition(array $columnInfo, \Doctrine\DBAL\Platforms\AbstractPlatform $platform): string
    {
        $type = $columnInfo['type'];
        $nullable = $columnInfo['nullable'];
        $options = $columnInfo['options'];

        // Use Doctrine's type system to get proper SQL type
        $typeRegistry = \Doctrine\DBAL\Types\Type::getTypeRegistry();
        try {
            $doctrineType = $typeRegistry->get($type);
            $column = [];
            if (isset($options['length'])) {
                $column['length'] = $options['length'];
            }
            $sqlType = $doctrineType->getSQLDeclaration($column, $platform);
        } catch (\Exception $e) {
            // Fallback to manual mapping
            $typeMap = [
                'boolean' => 'BOOLEAN',
                'integer' => 'INTEGER',
                'float' => 'FLOAT',
                'string' => 'VARCHAR(255)',
                'datetime_immutable' => 'DATETIME',
                'json' => 'JSON',
            ];
            $sqlType = $typeMap[strtolower($type)] ?? 'VARCHAR(255)';

            // Handle string length
            if ($type === 'string' && isset($options['length'])) {
                $sqlType = sprintf('VARCHAR(%d)', $options['length']);
            }
        }

        // Handle default values
        $default = '';
        if (isset($options['default'])) {
            if (is_bool($options['default'])) {
                $default = ' DEFAULT ' . ($options['default'] ? '1' : '0');
            } elseif (is_numeric($options['default'])) {
                $default = ' DEFAULT ' . $options['default'];
            }
        }

        $nullConstraint = $nullable ? ' NULL' : ' NOT NULL';

        return $sqlType . $nullConstraint . $default;
    }

    /**
     * Add missing indexes to the table.
     *
     * @param EntityManagerInterface $entityManager The entity manager
     * @param SymfonyStyle $io The Symfony style output
     * @param \Doctrine\DBAL\Schema\Table $table The table schema
     * @return void
     */
    private function addMissingIndexes(EntityManagerInterface $entityManager, SymfonyStyle $io, \Doctrine\DBAL\Schema\Table $table): void
    {
        $connection = $entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $schemaManager = $connection->createSchemaManager();

        // Expected indexes from entity
        $expectedIndexes = [
            'idx_route_reviewed' => ['reviewed'],
            'idx_route_reviewed_at' => ['reviewed_at'],
        ];

        $existingIndexes = [];
        foreach ($table->getIndexes() as $index) {
            $existingIndexes[strtolower($index->getName())] = $index;
        }

        $indexesToAdd = [];
        foreach ($expectedIndexes as $indexName => $columns) {
            if (!isset($existingIndexes[strtolower($indexName)])) {
                // Check if all columns exist
                $allColumnsExist = true;
                foreach ($columns as $column) {
                    if (!$table->hasColumn($column)) {
                        $allColumnsExist = false;
                        break;
                    }
                }

                if ($allColumnsExist) {
                    $indexesToAdd[$indexName] = $columns;
                }
            }
        }

        if (empty($indexesToAdd)) {
            return;
        }

        $io->text(sprintf('Adding <info>%d</info> missing index(es):', count($indexesToAdd)));

        foreach ($indexesToAdd as $indexName => $columns) {
            $quotedColumns = array_map(function ($col) use ($platform) {
                return $platform->quoteIdentifier($col);
            }, $columns);

            // Get the actual table name from entity metadata
            $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteData');
            $actualTableName = $metadata->getTableName();
            
            $sql = sprintf(
                'CREATE INDEX %s ON %s (%s)',
                $platform->quoteIdentifier($indexName),
                $platform->quoteIdentifier($actualTableName),
                implode(', ', $quotedColumns)
            );

            try {
                $connection->executeStatement($sql);
                $io->text(sprintf('  ✓ Created index <info>%s</info>', $indexName));
            } catch (\Exception $e) {
                $io->warning(sprintf('  ✗ Failed to create index %s: %s', $indexName, $e->getMessage()));
            }
        }
    }
}
