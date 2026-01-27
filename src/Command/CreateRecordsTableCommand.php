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
 * Command to create the access records table.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
#[AsCommand(
    name: 'nowo:performance:create-records-table',
    description: 'Create the access records database table for temporal analysis',
    help: <<<'HELP'
The <info>%command.name%</info> command creates the access records table (routes_data_records) 
with all necessary columns and indexes for temporal analysis of route access patterns.

This table stores individual access records with timestamp, HTTP status code, and response time.
The table name is automatically derived from the main table name + '_records'.

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

The table name is automatically derived from the main table name configured in 
<comment>config/packages/nowo_performance.yaml</comment> (main table + '_records').
HELP
)]
final class CreateRecordsTableCommand extends Command
{
    /**
     * Constructor.
     *
     * @param ManagerRegistry $registry       Doctrine registry
     * @param string          $connectionName The name of the Doctrine connection to use
     * @param string          $mainTableName  The configured main table name (to derive records table name)
     */
    public function __construct(
        private readonly ManagerRegistry $registry,
        #[Autowire('%nowo_performance.connection%')]
        private readonly string $connectionName,
        #[Autowire('%nowo_performance.table_name%')]
        private readonly string $mainTableName,
    ) {
        parent::__construct();
    }

    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force table creation even if it already exists')
            ->addOption('update', 'u', InputOption::VALUE_NONE, 'Add missing columns to existing table without losing data');
    }

    /**
     * Get schema manager from connection (compatible with DBAL 2.x and 3.x).
     *
     * @param \Doctrine\DBAL\Connection $connection The database connection
     *
     * @return \Doctrine\DBAL\Schema\AbstractSchemaManager The schema manager
     */
    private function getSchemaManager(\Doctrine\DBAL\Connection $connection): \Doctrine\DBAL\Schema\AbstractSchemaManager
    {
        // DBAL 3.x uses createSchemaManager()
        if (method_exists($connection, 'createSchemaManager')) {
            return $connection->createSchemaManager();
        }
        // DBAL 2.x uses getSchemaManager()
        if (method_exists($connection, 'getSchemaManager')) {
            // @phpstan-ignore-next-line - getSchemaManager() exists in DBAL 2.x but not in type definitions for DBAL 3.x
            /** @var callable $getSchemaManager */
            $getSchemaManager = [$connection, 'getSchemaManager'];

            return $getSchemaManager();
        }
        throw new \RuntimeException('Unable to get schema manager: neither createSchemaManager() nor getSchemaManager() is available.');
    }

    /**
     * Execute the command.
     *
     * @param InputInterface  $input  The input interface
     * @param OutputInterface $output The output interface
     *
     * @return int Command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Nowo Performance Bundle - Create Access Records Table');

        try {
            $connection = $this->registry->getConnection($this->connectionName);
            $schemaManager = $this->getSchemaManager($connection);

            // Get the actual table name from entity metadata (after RouteDataRecordTableNameSubscriber has processed it)
            $entityManager = $this->registry->getManager($this->connectionName);
            $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteDataRecord');

            // Get table name from metadata (compatible with different Doctrine versions)
            $actualTableName = method_exists($metadata, 'getTableName')
                ? $metadata->getTableName()
                : ($metadata->table['name'] ?? $this->mainTableName.'_records');

            $tableExists = $schemaManager->tablesExist([$actualTableName]);

            if ($tableExists && !$input->getOption('force') && !$input->getOption('update')) {
                $io->warning(\sprintf('Table "%s" already exists.', $actualTableName));
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
                    \sprintf('Table name: <info>%s</info>', $actualTableName),
                    \sprintf('Connection: <info>%s</info>', $this->connectionName),
                ]);

                $this->updateTableSchema($entityManager, $io);

                $io->success(\sprintf('Table "%s" updated successfully!', $actualTableName));

                return Command::SUCCESS;
            }

            if ($tableExists && $input->getOption('force')) {
                $io->warning(\sprintf('Dropping existing table "%s"...', $actualTableName));
                $schemaManager->dropTable($actualTableName);
                $io->success('Table dropped.');
            }

            $io->section('Creating Table');
            $io->text([
                \sprintf('Table name: <info>%s</info>', $actualTableName),
                \sprintf('Connection: <info>%s</info>', $this->connectionName),
            ]);

            // Use Doctrine's schema tool to create the table
            $this->createTableUsingSchemaTool($entityManager, $io);

            $io->success(\sprintf('Table "%s" created successfully!', $actualTableName));
            $io->note('The table is now ready to store access records for temporal analysis.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(\sprintf('Failed to create table: %s', $e->getMessage()));
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
     * @param SymfonyStyle           $io            The Symfony style output
     */
    private function createTableUsingSchemaTool(EntityManagerInterface $entityManager, SymfonyStyle $io): void
    {
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        // Filter to only RouteDataRecord entity
        $routeDataRecordMetadata = array_filter($metadata, static function ($meta) {
            return 'Nowo\PerformanceBundle\Entity\RouteDataRecord' === $meta->getName();
        });

        if (empty($routeDataRecordMetadata)) {
            throw new \RuntimeException('RouteDataRecord entity metadata not found.');
        }

        $io->text('Generating SQL statements...');
        $sql = $schemaTool->getCreateSchemaSql($routeDataRecordMetadata);

        if (empty($sql)) {
            $io->warning('No SQL statements to execute. Table might already exist.');

            return;
        }

        $connection = $entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        foreach ($sql as $statement) {
            // Fix invalid datetime defaults that MySQL might generate
            $statement = preg_replace(
                "/DEFAULT\s+'0000-00-00\s+00:00:00'/i",
                '',
                $statement
            );

            $io->text(\sprintf('  <comment>%s</comment>', $statement));
            $connection->executeStatement($statement);
        }

        $io->text('✓ Table and indexes created.');
    }

    /**
     * Update the table schema by adding missing columns.
     *
     * @param EntityManagerInterface $entityManager The entity manager
     * @param SymfonyStyle           $io            The Symfony style output
     */
    private function updateTableSchema(EntityManagerInterface $entityManager, SymfonyStyle $io): void
    {
        $connection = $entityManager->getConnection();
        $schemaManager = $this->getSchemaManager($connection);

        // Get the actual table name from entity metadata
        $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteDataRecord');
        $actualTableName = method_exists($metadata, 'getTableName')
            ? $metadata->getTableName()
            : ($metadata->table['name'] ?? $this->mainTableName.'_records');

        // Verify table exists
        if (!$schemaManager->tablesExist([$actualTableName])) {
            $io->error(\sprintf('Table "%s" does not exist. Use the create command without --update to create it.', $actualTableName));

            return;
        }

        $table = $schemaManager->introspectTable($actualTableName);
        $existingColumnsMap = [];
        foreach ($table->getColumns() as $column) {
            $existingColumnsMap[strtolower($column->getName())] = $column;
        }

        // Get expected columns from entity metadata
        $expectedColumns = [];
        foreach ($metadata->getFieldNames() as $fieldName) {
            $columnName = $metadata->getColumnName($fieldName);
            // getFieldMapping() returns array in DBAL 2.x, FieldMapping object in DBAL 3.x
            $fieldMapping = $metadata->getFieldMapping($fieldName);
            $fieldMappingArray = \is_array($fieldMapping) ? $fieldMapping : (array) $fieldMapping;

            $options = $fieldMappingArray['options'] ?? [];

            $defaultValue = $options['default'] ?? $fieldMappingArray['default'] ?? null;

            $expectedColumns[strtolower($columnName)] = [
                'field' => $fieldName,
                'column' => $columnName,
                'type' => $metadata->getTypeOfField($fieldName),
                'nullable' => $metadata->isNullable($fieldName),
                'options' => $options,
                'length' => $fieldMappingArray['length'] ?? null,
                'default' => $defaultValue,
            ];
        }

        $columnsToAdd = [];
        $platform = $connection->getDatabasePlatform();

        // Compare each expected column with existing ones
        foreach ($expectedColumns as $columnNameLower => $expectedInfo) {
            $columnName = $expectedInfo['column'];

            if (!isset($existingColumnsMap[$columnNameLower])) {
                // Column doesn't exist, needs to be added
                $columnsToAdd[$columnName] = $expectedInfo;
            }
        }

        if (empty($columnsToAdd)) {
            $io->success('All columns are up to date. No changes needed.');
            // Still check indexes
            $this->addMissingIndexes($entityManager, $io, $table);

            return;
        }

        $io->text(\sprintf('Using table name: <info>%s</info>', $actualTableName));
        $io->newLine();

        // Add missing columns
        if (!empty($columnsToAdd)) {
            $io->section(\sprintf('Adding <info>%d</info> missing column(s):', \count($columnsToAdd)));
            foreach ($columnsToAdd as $columnName => $columnInfo) {
                $io->text(\sprintf('  - <comment>%s</comment> (%s)', $columnName, $columnInfo['type']));

                $columnDefinition = $this->getColumnDefinition($columnInfo, $platform);
                $sql = \sprintf(
                    'ALTER TABLE %s ADD COLUMN %s %s',
                    $platform->quoteIdentifier($actualTableName),
                    $platform->quoteIdentifier($columnName),
                    $columnDefinition
                );

                try {
                    $connection->executeStatement($sql);
                    $io->text(\sprintf('  ✓ Added column <info>%s</info>', $columnName));
                } catch (\Exception $e) {
                    $io->error(\sprintf('  ✗ Failed to add column %s: %s', $columnName, $e->getMessage()));
                    throw $e;
                }
            }
            $io->newLine();
        }

        // Add missing indexes
        $this->addMissingIndexes($entityManager, $io, $table);
    }

    /**
     * Get SQL column definition for a column.
     *
     * @param array<string, mixed>                      $columnInfo Column information
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform   Database platform
     *
     * @return string SQL column definition
     */
    private function getColumnDefinition(array $columnInfo, \Doctrine\DBAL\Platforms\AbstractPlatform $platform): string
    {
        $sqlType = $this->getColumnSQLType($columnInfo, $platform);
        $nullable = $columnInfo['nullable'];
        $type = $columnInfo['type'];

        // Handle default values
        $default = '';
        if (isset($columnInfo['default'])) {
            $defaultValue = $columnInfo['default'];

            // Skip default for datetime types
            if (null !== $defaultValue && !\in_array($type, ['datetime', 'datetime_immutable', 'date', 'time'], true)) {
                if (\is_bool($defaultValue)) {
                    $default = ' DEFAULT '.($defaultValue ? '1' : '0');
                } elseif (is_numeric($defaultValue)) {
                    $default = ' DEFAULT '.$defaultValue;
                } elseif (\is_string($defaultValue)) {
                    $default = ' DEFAULT '.$platform->quoteStringLiteral($defaultValue);
                }
            }
        }

        $nullConstraint = $nullable ? ' NULL' : ' NOT NULL';

        return $sqlType.$nullConstraint.$default;
    }

    /**
     * Get SQL type string for a column.
     *
     * @param array<string, mixed>                      $columnInfo Column information
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform   Database platform
     *
     * @return string SQL type declaration
     */
    private function getColumnSQLType(array $columnInfo, \Doctrine\DBAL\Platforms\AbstractPlatform $platform): string
    {
        $type = $columnInfo['type'];
        $options = $columnInfo['options'];
        $length = $columnInfo['length'] ?? null;

        // Use Doctrine's type system to get proper SQL type
        // getTypeRegistry() is available in DBAL 3.x, use Type::getType() for DBAL 2.x compatibility
        try {
            $doctrineType = null;
            // Try DBAL 3.x method first
            if (method_exists(\Doctrine\DBAL\Types\Type::class, 'getTypeRegistry')) {
                $typeRegistry = \Doctrine\DBAL\Types\Type::getTypeRegistry();
                $doctrineType = $typeRegistry->get($type);
            } elseif (method_exists(\Doctrine\DBAL\Types\Type::class, 'getType')) {
                // DBAL 2.x method
                $doctrineType = \Doctrine\DBAL\Types\Type::getType($type);
            }

            if (null !== $doctrineType) {
                $column = [];
                if (null !== $length) {
                    $column['length'] = $length;
                } elseif (isset($options['length'])) {
                    $column['length'] = $options['length'];
                }

                return $doctrineType->getSQLDeclaration($column, $platform);
            }
        } catch (\Exception $e) {
            // Fall through to fallback mapping
        }

        // Fallback to manual mapping (used if Type system fails or is not available)
        $typeMap = [
            'boolean' => 'BOOLEAN',
            'integer' => 'INTEGER',
            'float' => 'FLOAT',
            'string' => 'VARCHAR(255)',
            'datetime_immutable' => 'DATETIME',
            'json' => 'JSON',
            'bigint' => 'BIGINT',
        ];
        $sqlType = $typeMap[strtolower($type)] ?? 'VARCHAR(255)';

        // Handle string length
        if ('string' === $type) {
            $finalLength = $length ?? $options['length'] ?? 255;
            $sqlType = \sprintf('VARCHAR(%d)', $finalLength);
        }

        return $sqlType;
    }

    /**
     * Add missing indexes to the table.
     *
     * @param EntityManagerInterface      $entityManager The entity manager
     * @param SymfonyStyle                $io            The Symfony style output
     * @param \Doctrine\DBAL\Schema\Table $table         The table schema
     */
    private function addMissingIndexes(EntityManagerInterface $entityManager, SymfonyStyle $io, \Doctrine\DBAL\Schema\Table $table): void
    {
        $connection = $entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $schemaManager = $this->getSchemaManager($connection);

        // Get expected indexes from entity metadata
        $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteDataRecord');
        $expectedIndexes = [];

        // Get indexes from table metadata
        if (isset($metadata->table['indexes']) && \is_array($metadata->table['indexes'])) {
            foreach ($metadata->table['indexes'] as $indexName => $indexDefinition) {
                $columns = $indexDefinition['columns'] ?? [];
                if (!empty($columns)) {
                    $expectedIndexes[$indexName] = $columns;
                }
            }
        }

        // Also check for Index attributes
        if (\PHP_VERSION_ID >= 80000) {
            try {
                $reflection = new \ReflectionClass('Nowo\PerformanceBundle\Entity\RouteDataRecord');
                $attributes = $reflection->getAttributes(\Doctrine\ORM\Mapping\Index::class);
                foreach ($attributes as $attribute) {
                    $index = $attribute->newInstance();
                    $indexName = $index->name ?? null;
                    $columns = $index->columns ?? [];
                    if ($indexName && !empty($columns)) {
                        $expectedIndexes[$indexName] = $columns;
                    }
                }
            } catch (\ReflectionException $e) {
                // Ignore reflection errors
            }
        }

        $existingIndexes = [];
        foreach ($table->getIndexes() as $index) {
            $existingIndexes[strtolower($index->getName())] = $index;
        }

        $indexesToAdd = [];
        foreach ($expectedIndexes as $indexName => $columns) {
            if (!isset($existingIndexes[strtolower($indexName)])) {
                // Check if all columns exist in the current table schema
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

        $io->text(\sprintf('Adding <info>%d</info> missing index(es):', \count($indexesToAdd)));

        foreach ($indexesToAdd as $indexName => $columns) {
            $quotedColumns = array_map(static function ($col) use ($platform) {
                return $platform->quoteIdentifier($col);
            }, $columns);

            // Get the actual table name from entity metadata
            $actualTableName = method_exists($metadata, 'getTableName')
                ? $metadata->getTableName()
                : ($metadata->table['name'] ?? $this->mainTableName.'_records');

            $sql = \sprintf(
                'CREATE INDEX %s ON %s (%s)',
                $platform->quoteIdentifier($indexName),
                $platform->quoteIdentifier($actualTableName),
                implode(', ', $quotedColumns)
            );

            try {
                $connection->executeStatement($sql);
                $io->text(\sprintf('  ✓ Created index <info>%s</info> on columns: %s', $indexName, implode(', ', $columns)));
            } catch (\Exception $e) {
                $io->warning(\sprintf('  ✗ Failed to create index %s: %s', $indexName, $e->getMessage()));
            }
        }
    }
}
