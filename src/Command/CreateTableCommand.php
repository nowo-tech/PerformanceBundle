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
     * @param ManagerRegistry $registry       Doctrine registry
     * @param string          $connectionName The name of the Doctrine connection to use
     * @param string          $tableName      The configured table name
     */
    public function __construct(
        private readonly ManagerRegistry $registry,
        #[Autowire('%nowo_performance.connection%')]
        private readonly string $connectionName,
        #[Autowire('%nowo_performance.table_name%')]
        private readonly string $tableName,
        #[Autowire('%nowo_performance.enable_access_records%')]
        private readonly bool $enableAccessRecords = false,
    ) {
        parent::__construct();
    }

    /**
     * Configure the command.
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
        $io->title('Nowo Performance Bundle - Create Table');

        try {
            $connection = $this->registry->getConnection($this->connectionName);
            $schemaManager = $this->getSchemaManager($connection);

            // Get the actual table name from entity metadata (after TableNameSubscriber has processed it)
            $entityManager = $this->registry->getManager($this->connectionName);
            $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteData');
            // Get table name from metadata (compatible with different Doctrine versions)
            $actualTableName = isset($metadata->table['name'])
                ? $metadata->table['name']
                : $this->tableName;

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

                // Also update records table if access records are enabled
                if ($this->enableAccessRecords) {
                    $io->newLine();
                    $io->section('Updating Access Records Table');
                    $this->updateRecordsTable($entityManager, $io);
                }

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
            $io->note('The table is now ready to store performance metrics.');

            // Also create records table if access records are enabled
            if ($this->enableAccessRecords) {
                $io->newLine();
                $io->section('Creating Access Records Table');
                $this->createRecordsTable($entityManager, $io);
            }

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

        // Filter to only RouteData entity
        $routeDataMetadata = array_filter($metadata, static function ($meta) {
            return 'Nowo\PerformanceBundle\Entity\RouteData' === $meta->getName();
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
        $platform = $connection->getDatabasePlatform();

        foreach ($sql as $statement) {
            // Ensure AUTO_INCREMENT is set for id column in MySQL/MariaDB
            $platformClass = $platform::class;
            $isMySQL = $platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform
                || str_contains(strtolower($platformClass), 'mysql')
                || str_contains(strtolower($platformClass), 'mariadb');

            if ($isMySQL) {
                // Check if this is a CREATE TABLE statement and id column exists
                if (preg_match('/CREATE\s+TABLE/i', $statement)
                    && preg_match('/`?id`?\s+INT/i', $statement)
                    && !preg_match('/AUTO_INCREMENT/i', $statement)) {
                    // Add AUTO_INCREMENT to the id column - handle both backticked and non-backticked column names
                    // Pattern: id INT or `id` INT followed by NOT NULL
                    $statement = preg_replace(
                        '/([`]?id[`]?\s+INT(?:EGER)?[^,)]*?)(\s+NOT\s+NULL)(?=\s*[,)]|$)/i',
                        '$1$2 AUTO_INCREMENT',
                        $statement
                    );
                }
            }

            // Fix invalid datetime defaults that MySQL might generate
            // Remove DEFAULT '0000-00-00 00:00:00' for nullable datetime columns
            $statement = preg_replace(
                "/DEFAULT\s+'0000-00-00\s+00:00:00'/i",
                '',
                $statement
            );

            // Also remove DEFAULT '0000-00-00' for date columns
            $statement = preg_replace(
                "/DEFAULT\s+'0000-00-00'/i",
                '',
                $statement
            );

            $io->text(\sprintf('  <comment>%s</comment>', $statement));
            $connection->executeStatement($statement);
        }

        $io->text('✓ Table and indexes created.');
    }

    /**
     * Update the table schema by adding missing columns and updating existing ones.
     *
     * @param EntityManagerInterface $entityManager The entity manager
     * @param SymfonyStyle           $io            The Symfony style output
     */
    private function updateTableSchema(EntityManagerInterface $entityManager, SymfonyStyle $io): void
    {
        $connection = $entityManager->getConnection();
        $schemaManager = $this->getSchemaManager($connection);

        // Get the actual table name from entity metadata (after TableNameSubscriber has processed it)
        $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteData');
        // Get table name from metadata (compatible with different Doctrine versions)
        $actualTableName = isset($metadata->table['name'])
            ? $metadata->table['name']
            : $this->tableName;

        // Verify table exists
        if (!$schemaManager->tablesExist([$actualTableName])) {
            $io->error(\sprintf('Table "%s" does not exist. Use the create command without --update to create it.', $actualTableName));

            return;
        }

        $table = $schemaManager->introspectTable($actualTableName);
        $existingColumnsMap = [];
        foreach ($table->getColumns() as $column) {
            $columnName = $this->getColumnName($column, $connection);
            $existingColumnsMap[strtolower($columnName)] = $column;
        }

        // Check if id column has AUTO_INCREMENT (MySQL/MariaDB)
        $platform = $connection->getDatabasePlatform();
        $platformClass = $platform::class;
        $isMySQL = $platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform
            || str_contains(strtolower($platformClass), 'mysql')
            || str_contains(strtolower($platformClass), 'mariadb');

        if ($isMySQL && isset($existingColumnsMap['id'])) {
            $idColumn = $existingColumnsMap['id'];
            // Check if column is INTEGER type and doesn't have AUTO_INCREMENT
            $columnType = $idColumn->getType();
            // Check SQL declaration to determine if it's an integer type (works for both DBAL 2.x and 3.x)
            $sqlDeclaration = strtolower($columnType->getSQLDeclaration([], $platform));
            $isIntegerType = str_contains($sqlDeclaration, 'int') && !str_contains($sqlDeclaration, 'bigint');

            if ($isIntegerType) {
                // Check if AUTO_INCREMENT is missing by querying the database
                $checkSql = \sprintf(
                    "SELECT COLUMN_NAME, EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'id'",
                    $platform->quoteStringLiteral($actualTableName)
                );
                try {
                    $result = $connection->fetchAssociative($checkSql);
                    if ($result && !str_contains(strtoupper($result['EXTRA'] ?? ''), 'AUTO_INCREMENT')) {
                        $io->warning('Column "id" does not have AUTO_INCREMENT. Fixing...');
                        $this->fixAutoIncrementWithForeignKeys(
                            $connection,
                            $schemaManager,
                            $platform,
                            $actualTableName,
                            $io
                        );
                        $io->success('✓ Column "id" now has AUTO_INCREMENT');
                        // Refresh table schema
                        $table = $schemaManager->introspectTable($actualTableName);
                        $existingColumnsMap = [];
                        foreach ($table->getColumns() as $column) {
                            $columnName = $this->getColumnName($column, $connection);
                            $existingColumnsMap[strtolower($columnName)] = $column;
                        }
                    }
                } catch (\Exception $e) {
                    $io->warning(\sprintf('Could not check/fix AUTO_INCREMENT for id column: %s', $e->getMessage()));
                }
            }
        }

        // Get expected columns from entity metadata
        $expectedColumns = [];
        foreach ($metadata->getFieldNames() as $fieldName) {
            $columnName = $metadata->getColumnName($fieldName);

            // getFieldMapping() returns array in DBAL 2.x, FieldMapping object in DBAL 3.x
            $fieldMapping = $metadata->getFieldMapping($fieldName);
            $fieldMappingArray = \is_array($fieldMapping) ? $fieldMapping : (array) $fieldMapping;

            $options = $fieldMappingArray['options'] ?? [];

            // Get default value from options['default'] or fieldMapping['default']
            $defaultValue = $options['default'] ?? $fieldMappingArray['default'] ?? null;

            // Check if this field is autoincremental (ID field with GeneratedValue)
            // In Doctrine, if a field is an identifier and is of type INTEGER, it's typically autoincremental
            $isAutoincrement = false;
            if ($metadata->isIdentifier($fieldName)) {
                $fieldType = $metadata->getTypeOfField($fieldName);
                // Check if it's an integer type (integer, smallint, bigint)
                if (\in_array(strtolower($fieldType), ['integer', 'int', 'smallint', 'bigint'], true)) {
                    // Check generator type if available
                    $generatorType = $metadata->generatorType ?? null;
                    // If generatorType is AUTO, IDENTITY, or SEQUENCE (for MySQL, AUTO and IDENTITY are the same)
                    // Or if generatorType is null/not set, assume AUTO for integer IDs
                    if (null === $generatorType
                        || \Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_AUTO === $generatorType
                        || \Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_IDENTITY === $generatorType
                        || isset($fieldMappingArray['generated']) && true === $fieldMappingArray['generated']) {
                        $isAutoincrement = true;
                    }
                }
            }

            $expectedColumns[strtolower($columnName)] = [
                'field' => $fieldName,
                'column' => $columnName,
                'type' => $metadata->getTypeOfField($fieldName),
                'nullable' => $metadata->isNullable($fieldName),
                'options' => $options,
                'length' => $fieldMappingArray['length'] ?? null,
                'default' => $defaultValue,
                'autoincrement' => $isAutoincrement,
            ];
        }

        $columnsToAdd = [];
        $columnsToUpdate = [];
        $platform = $connection->getDatabasePlatform();

        // Compare each expected column with existing ones
        foreach ($expectedColumns as $columnNameLower => $expectedInfo) {
            $columnName = $expectedInfo['column'];

            if (!isset($existingColumnsMap[$columnNameLower])) {
                // Column doesn't exist, needs to be added
                $columnsToAdd[$columnName] = $expectedInfo;
            } else {
                // Column exists, check if it needs to be updated
                $existingColumn = $existingColumnsMap[$columnNameLower];
                $needsUpdate = $this->columnNeedsUpdate($existingColumn, $expectedInfo, $platform);

                if ($needsUpdate) {
                    $columnsToUpdate[$columnName] = [
                        'expected' => $expectedInfo,
                        'existing' => $existingColumn,
                    ];
                }
            }
        }

        $hasChanges = !empty($columnsToAdd) || !empty($columnsToUpdate);

        if (!$hasChanges) {
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
                    $this->quoteIdentifier($platform, $actualTableName),
                    $this->quoteIdentifier($platform, $columnName),
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

        // Update existing columns that have differences
        if (!empty($columnsToUpdate)) {
            $io->section(\sprintf('Updating <info>%d</info> column(s) with differences:', \count($columnsToUpdate)));
            foreach ($columnsToUpdate as $columnName => $columnData) {
                $expected = $columnData['expected'];
                $existing = $columnData['existing'];

                $io->text(\sprintf('  - <comment>%s</comment>', $columnName));

                // Show what's different
                $differences = $this->getColumnDifferences($existing, $expected, $platform);
                if (!empty($differences)) {
                    $io->text('    Differences: '.implode(', ', $differences));
                }

                // Special handling for id column with AUTO_INCREMENT when foreign keys exist
                if ('id' === strtolower($columnName) && ($expected['autoincrement'] ?? false)) {
                    try {
                        $this->fixAutoIncrementWithForeignKeys(
                            $connection,
                            $schemaManager,
                            $platform,
                            $actualTableName,
                            $io
                        );
                        $io->text(\sprintf('  ✓ Updated column <info>%s</info>', $columnName));
                    } catch (\Exception $e) {
                        $io->error(\sprintf('  ✗ Failed to update column %s: %s', $columnName, $e->getMessage()));
                        throw $e;
                    }
                } else {
                    $columnDefinition = $this->getColumnDefinition($expected, $platform);
                    $sql = \sprintf(
                        'ALTER TABLE %s MODIFY COLUMN %s %s',
                        $this->quoteIdentifier($platform, $actualTableName),
                        $this->quoteIdentifier($platform, $columnName),
                        $columnDefinition
                    );

                    try {
                        $connection->executeStatement($sql);
                        $io->text(\sprintf('  ✓ Updated column <info>%s</info>', $columnName));
                    } catch (\Exception $e) {
                        $io->error(\sprintf('  ✗ Failed to update column %s: %s', $columnName, $e->getMessage()));
                        throw $e;
                    }
                }
            }
            $io->newLine();
        }

        // Add missing indexes
        $this->addMissingIndexes($entityManager, $io, $table);
    }

    /**
     * Check if a column needs to be updated.
     *
     * @param \Doctrine\DBAL\Schema\Column              $existingColumn The existing column
     * @param array<string, mixed>                      $expectedInfo   Expected column information
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform       Database platform
     *
     * @return bool True if column needs update
     */
    private function columnNeedsUpdate(
        \Doctrine\DBAL\Schema\Column $existingColumn,
        array $expectedInfo,
        \Doctrine\DBAL\Platforms\AbstractPlatform $platform,
    ): bool {
        // Check nullable
        if ($existingColumn->getNotnull() !== !$expectedInfo['nullable']) {
            return true;
        }

        // Check type
        $expectedType = $this->getColumnSQLType($expectedInfo, $platform);
        // Build column array for getSQLDeclaration (compatible with DBAL 2.x and 3.x)
        $columnArray = [
            'length' => $existingColumn->getLength(),
            'precision' => $existingColumn->getPrecision(),
            'scale' => $existingColumn->getScale(),
            'unsigned' => $existingColumn->getUnsigned(),
            'fixed' => $existingColumn->getFixed(),
            'notnull' => $existingColumn->getNotnull(),
            'default' => $existingColumn->getDefault(),
            'autoincrement' => $existingColumn->getAutoincrement(),
        ];
        $existingType = $existingColumn->getType()->getSQLDeclaration(
            $columnArray,
            $platform
        );

        // Normalize types for comparison (remove length, etc.)
        $expectedTypeNormalized = preg_replace('/\([^)]+\)/', '', $expectedType);
        $existingTypeNormalized = preg_replace('/\([^)]+\)/', '', $existingType);

        if (strtolower($expectedTypeNormalized) !== strtolower($existingTypeNormalized)) {
            return true;
        }

        // Check length for string types
        if ('string' === $expectedInfo['type'] && null !== $expectedInfo['length']) {
            $existingLength = $existingColumn->getLength();
            if ($existingLength !== $expectedInfo['length']) {
                return true;
            }
        }

        // Check default value
        $expectedDefault = $expectedInfo['default'] ?? null;
        $existingDefault = $existingColumn->getDefault();

        // Normalize comparison: both null means no default
        if (null === $expectedDefault && null === $existingDefault) {
            // Both are null, no difference
        } elseif ($expectedDefault !== $existingDefault) {
            // Handle boolean defaults
            if (\is_bool($expectedDefault) && null !== $existingDefault) {
                $normalizedExisting = \in_array(strtolower((string) $existingDefault), ['1', 'true', 'yes'], true);
                if ($normalizedExisting !== $expectedDefault) {
                    return true;
                }
            } elseif (is_numeric($expectedDefault) && null !== $existingDefault) {
                // Compare numeric defaults (handle string vs int/float)
                // MySQL stores numeric defaults as strings, so we need to compare values
                if ((float) $expectedDefault !== (float) $existingDefault) {
                    return true;
                }
            } elseif ($expectedDefault !== $existingDefault) {
                return true;
            }
        }

        // Check AUTO_INCREMENT for MySQL/MariaDB
        $expectedAutoincrement = $expectedInfo['autoincrement'] ?? false;
        $existingAutoincrement = $existingColumn->getAutoincrement();
        if ($expectedAutoincrement !== $existingAutoincrement) {
            $platformClass = $platform::class;
            $isMySQL = $platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform
                || str_contains(strtolower($platformClass), 'mysql')
                || str_contains(strtolower($platformClass), 'mariadb');
            // Only check for MySQL/MariaDB as AUTO_INCREMENT is MySQL-specific
            if ($isMySQL) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get human-readable list of column differences.
     *
     * @param \Doctrine\DBAL\Schema\Column              $existingColumn The existing column
     * @param array<string, mixed>                      $expectedInfo   Expected column information
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform       Database platform
     *
     * @return array<string> List of differences
     */
    private function getColumnDifferences(
        \Doctrine\DBAL\Schema\Column $existingColumn,
        array $expectedInfo,
        \Doctrine\DBAL\Platforms\AbstractPlatform $platform,
    ): array {
        $differences = [];

        // Check nullable
        if ($existingColumn->getNotnull() !== !$expectedInfo['nullable']) {
            $differences[] = \sprintf(
                'nullable: %s → %s',
                $existingColumn->getNotnull() ? 'NOT NULL' : 'NULL',
                $expectedInfo['nullable'] ? 'NULL' : 'NOT NULL'
            );
        }

        // Check type - compare SQL declarations instead of type names
        // This is more reliable across different Doctrine versions
        $expectedSQLType = $this->getColumnSQLType($expectedInfo, $platform);
        // Build column array for getSQLDeclaration (compatible with DBAL 2.x and 3.x)
        $columnArray = [
            'length' => $existingColumn->getLength(),
            'precision' => $existingColumn->getPrecision(),
            'scale' => $existingColumn->getScale(),
            'unsigned' => $existingColumn->getUnsigned(),
            'fixed' => $existingColumn->getFixed(),
            'notnull' => $existingColumn->getNotnull(),
            'default' => $existingColumn->getDefault(),
            'autoincrement' => $existingColumn->getAutoincrement(),
        ];
        $existingSQLType = $existingColumn->getType()->getSQLDeclaration(
            $columnArray,
            $platform
        );

        // Normalize types for comparison (remove length, etc.)
        $normalizedExpected = strtolower(preg_replace('/\([^)]+\)/', '', $expectedSQLType));
        $normalizedExisting = strtolower(preg_replace('/\([^)]+\)/', '', $existingSQLType));

        if ($normalizedExisting !== $normalizedExpected) {
            $existingTypeName = \get_class($existingColumn->getType());
            $differences[] = \sprintf('type: %s → %s', $existingTypeName, $expectedInfo['type']);
        }

        // Check length
        if (null !== $expectedInfo['length']) {
            $existingLength = $existingColumn->getLength();
            if ($existingLength !== $expectedInfo['length']) {
                $differences[] = \sprintf('length: %s → %s', $existingLength ?? 'NULL', $expectedInfo['length']);
            }
        }

        // Check default
        $expectedDefault = $expectedInfo['default'] ?? null;
        $existingDefault = $existingColumn->getDefault();
        if ($expectedDefault !== $existingDefault) {
            $differences[] = \sprintf('default: %s → %s', $existingDefault ?? 'NULL', $expectedDefault ?? 'NULL');
        }

        return $differences;
    }

    /**
     * Create the access records table using Doctrine's schema tool.
     *
     * @param EntityManagerInterface $entityManager The entity manager
     * @param SymfonyStyle           $io            The Symfony style output
     */
    private function createRecordsTable(EntityManagerInterface $entityManager, SymfonyStyle $io): void
    {
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        // Filter to only RouteDataRecord entity
        $routeDataRecordMetadata = array_filter($metadata, static function ($meta) {
            return 'Nowo\PerformanceBundle\Entity\RouteDataRecord' === $meta->getName();
        });

        if (empty($routeDataRecordMetadata)) {
            $io->warning('RouteDataRecord entity metadata not found. Skipping records table creation.');

            return;
        }

        // Get the actual table name from entity metadata
        $recordMetadata = reset($routeDataRecordMetadata);
        // Get table name from metadata (compatible with different Doctrine versions)
        $actualTableName = isset($recordMetadata->table['name'])
            ? $recordMetadata->table['name']
            : ($this->tableName.'_records');

        $io->text(\sprintf('Table name: <info>%s</info>', $actualTableName));
        $io->text('Generating SQL statements...');

        $sql = $schemaTool->getCreateSchemaSql($routeDataRecordMetadata);

        if (empty($sql)) {
            $io->warning('No SQL statements to execute. Table might already exist.');

            return;
        }

        $connection = $entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        foreach ($sql as $statement) {
            // Ensure AUTO_INCREMENT is set for id column in MySQL/MariaDB
            $platformClass = $platform::class;
            $isMySQL = $platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform
                || str_contains(strtolower($platformClass), 'mysql')
                || str_contains(strtolower($platformClass), 'mariadb');

            if ($isMySQL) {
                // Check if this is a CREATE TABLE statement and id column exists
                if (preg_match('/CREATE\s+TABLE/i', $statement)
                    && preg_match('/`?id`?\s+INT/i', $statement)
                    && !preg_match('/AUTO_INCREMENT/i', $statement)) {
                    // Add AUTO_INCREMENT to the id column - handle both backticked and non-backticked column names
                    // Pattern: id INT or `id` INT followed by NOT NULL
                    $statement = preg_replace(
                        '/([`]?id[`]?\s+INT(?:EGER)?[^,)]*?)(\s+NOT\s+NULL)(?=\s*[,)]|$)/i',
                        '$1$2 AUTO_INCREMENT',
                        $statement
                    );
                }
            }

            // Fix invalid datetime defaults that MySQL might generate
            $statement = preg_replace(
                "/DEFAULT\s+'0000-00-00\s+00:00:00'/i",
                '',
                $statement
            );

            // Also remove DEFAULT '0000-00-00' for date columns
            $statement = preg_replace(
                "/DEFAULT\s+'0000-00-00'/i",
                '',
                $statement
            );

            $io->text(\sprintf('  <comment>%s</comment>', $statement));
            $connection->executeStatement($statement);
        }

        $io->success(\sprintf('Access records table "%s" created successfully!', $actualTableName));
    }

    /**
     * Update the access records table schema by adding missing columns.
     *
     * @param EntityManagerInterface $entityManager The entity manager
     * @param SymfonyStyle           $io            The Symfony style output
     */
    private function updateRecordsTable(EntityManagerInterface $entityManager, SymfonyStyle $io): void
    {
        $connection = $entityManager->getConnection();
        $schemaManager = $this->getSchemaManager($connection);

        // Get the actual table name from entity metadata
        $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteDataRecord');
        $actualTableName = method_exists($metadata, 'getTableName')
            ? $metadata->getTableName()
            : ($metadata->table['name'] ?? $this->tableName.'_records');

        // Verify table exists
        if (!$schemaManager->tablesExist([$actualTableName])) {
            $io->note(\sprintf('Access records table "%s" does not exist. Creating it...', $actualTableName));
            $this->createRecordsTable($entityManager, $io);

            return;
        }

        $io->text(\sprintf('Table name: <info>%s</info>', $actualTableName));

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

            // Check if this field is autoincremental (ID field with GeneratedValue)
            // In Doctrine, if a field is an identifier and is of type INTEGER, it's typically autoincremental
            $isAutoincrement = false;
            if ($metadata->isIdentifier($fieldName)) {
                $fieldType = $metadata->getTypeOfField($fieldName);
                // Check if it's an integer type (integer, smallint, bigint)
                if (\in_array(strtolower($fieldType), ['integer', 'int', 'smallint', 'bigint'], true)) {
                    // Check generator type if available
                    $generatorType = $metadata->generatorType ?? null;
                    // If generatorType is AUTO, IDENTITY, or SEQUENCE (for MySQL, AUTO and IDENTITY are the same)
                    // Or if generatorType is null/not set, assume AUTO for integer IDs
                    if (null === $generatorType
                        || \Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_AUTO === $generatorType
                        || \Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_IDENTITY === $generatorType
                        || isset($fieldMappingArray['generated']) && true === $fieldMappingArray['generated']) {
                        $isAutoincrement = true;
                    }
                }
            }

            $expectedColumns[strtolower($columnName)] = [
                'field' => $fieldName,
                'column' => $columnName,
                'type' => $metadata->getTypeOfField($fieldName),
                'nullable' => $metadata->isNullable($fieldName),
                'options' => $options,
                'length' => $fieldMappingArray['length'] ?? null,
                'default' => $defaultValue,
                'autoincrement' => $isAutoincrement,
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

            return;
        }

        // Add missing columns
        $io->section(\sprintf('Adding <info>%d</info> missing column(s):', \count($columnsToAdd)));
        foreach ($columnsToAdd as $columnName => $columnInfo) {
            $io->text(\sprintf('  - <comment>%s</comment> (%s)', $columnName, $columnInfo['type']));

            $columnDefinition = $this->getColumnDefinition($columnInfo, $platform);
                $sql = \sprintf(
                    'ALTER TABLE %s ADD COLUMN %s %s',
                    $this->quoteIdentifier($platform, $actualTableName),
                    $this->quoteIdentifier($platform, $columnName),
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

        $io->success(\sprintf('Access records table "%s" updated successfully!', $actualTableName));
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
        $options = $columnInfo['options'];
        $type = $columnInfo['type'];
        $isAutoincrement = $columnInfo['autoincrement'] ?? false;

        // Handle default values
        // Don't set defaults for datetime/datetime_immutable columns (especially nullable ones)
        // as they can cause issues with MySQL strict mode
        // Also skip defaults for autoincrement columns
        $default = '';
        if (!$isAutoincrement && (isset($options['default']) || isset($columnInfo['default']))) {
            $defaultValue = $columnInfo['default'] ?? $options['default'];

            // Skip default for datetime types (they should be nullable or use CURRENT_TIMESTAMP explicitly)
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

        // Add AUTO_INCREMENT for MySQL/MariaDB if column is autoincremental
        $autoincrement = '';
        if ($isAutoincrement) {
            $platformClass = $platform::class;
            $isMySQL = $platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform
                || str_contains(strtolower($platformClass), 'mysql')
                || str_contains(strtolower($platformClass), 'mariadb');

            if ($isMySQL) {
                $autoincrement = ' AUTO_INCREMENT';
            }
        }

        return $sqlType.$nullConstraint.$default.$autoincrement;
    }

    /**
     * Quote a single identifier (compatible with DBAL 2.x and 3.x).
     *
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform The database platform
     * @param string                                    $identifier The identifier to quote
     *
     * @return string The quoted identifier
     */
    private function quoteIdentifier(\Doctrine\DBAL\Platforms\AbstractPlatform $platform, string $identifier): string
    {
        // Use quoteSingleIdentifier() for DBAL 3.x compatibility
        if (method_exists($platform, 'quoteSingleIdentifier')) {
            return $platform->quoteSingleIdentifier($identifier);
        }

        // Fallback to quoteIdentifier() for DBAL 2.x
        return $platform->quoteIdentifier($identifier);
    }

    /**
     * Get column name from Column object (compatible with DBAL 2.x and 3.x).
     *
     * @param \Doctrine\DBAL\Schema\Column $column The column object
     * @param \Doctrine\DBAL\Connection    $connection The database connection
     *
     * @return string The column name
     */
    private function getColumnName(\Doctrine\DBAL\Schema\Column $column, \Doctrine\DBAL\Connection $connection): string
    {
        // Try getQuotedName() for DBAL 3.x compatibility
        if (method_exists($column, 'getQuotedName')) {
            return $column->getQuotedName($connection->getDatabasePlatform());
        }

        // Fallback to getName() for DBAL 2.x
        if (method_exists($column, 'getName')) {
            $name = $column->getName();
            // getName() might return a Name object in DBAL 3.x, convert to string
            return \is_string($name) ? $name : (string) $name;
        }

        // Last resort: try reflection to get name
        try {
            $reflection = new \ReflectionClass($column);
            $nameProperty = $reflection->getProperty('name');
            $nameProperty->setAccessible(true);
            $name = $nameProperty->getValue($column);
            return \is_string($name) ? $name : (string) $name;
        } catch (\Exception $e) {
            return '';
        }
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
        $schemaManager = $connection->createSchemaManager();

        // Get expected indexes from entity metadata
        $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteData');
        $expectedIndexes = [];

        // Get indexes from table metadata (Doctrine ORM 2.x style)
        if (isset($metadata->table['indexes']) && \is_array($metadata->table['indexes'])) {
            foreach ($metadata->table['indexes'] as $indexName => $indexDefinition) {
                $columns = $indexDefinition['columns'] ?? [];
                if (!empty($columns)) {
                    $expectedIndexes[$indexName] = $columns;
                }
            }
        }

        // Also check for Index attributes (Doctrine ORM 3.x style with PHP 8 attributes)
        if (\PHP_VERSION_ID >= 80000) {
            try {
                $reflection = new \ReflectionClass('Nowo\PerformanceBundle\Entity\RouteData');
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

        $io->text(\sprintf('Adding <info>%d</info> missing index(es):', \count($indexesToAdd)));

        foreach ($indexesToAdd as $indexName => $columns) {
            $quotedColumns = array_map(function ($col) use ($platform) {
                return $this->quoteIdentifier($platform, $col);
            }, $columns);

            // Get the actual table name from entity metadata
            $actualTableName = method_exists($metadata, 'getTableName')
                ? $metadata->getTableName()
                : ($metadata->table['name'] ?? $this->tableName);

            $sql = \sprintf(
                'CREATE INDEX %s ON %s (%s)',
                $this->quoteIdentifier($platform, $indexName),
                $this->quoteIdentifier($platform, $actualTableName),
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

    /**
     * Fix AUTO_INCREMENT for id column, handling foreign key constraints.
     *
     * @param \Doctrine\DBAL\Connection                   $connection    The database connection
     * @param \Doctrine\DBAL\Schema\AbstractSchemaManager $schemaManager The schema manager
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform   $platform      The database platform
     * @param string                                      $tableName     The table name
     * @param SymfonyStyle                                $io            The Symfony style output
     */
    private function fixAutoIncrementWithForeignKeys(
        \Doctrine\DBAL\Connection $connection,
        \Doctrine\DBAL\Schema\AbstractSchemaManager $schemaManager,
        \Doctrine\DBAL\Platforms\AbstractPlatform $platform,
        string $tableName,
        SymfonyStyle $io,
    ): void {
        $platformClass = $platform::class;
        $isMySQL = $platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform
            || str_contains(strtolower($platformClass), 'mysql')
            || str_contains(strtolower($platformClass), 'mariadb');

        if (!$isMySQL) {
            // Only MySQL/MariaDB need this special handling
            $alterSql = \sprintf(
                'ALTER TABLE %s MODIFY COLUMN %s INT NOT NULL AUTO_INCREMENT',
                $this->quoteIdentifier($platform, $tableName),
                $this->quoteIdentifier($platform, 'id')
            );
            $connection->executeStatement($alterSql);

            return;
        }

        // Get all foreign keys that reference the id column of this table
        $foreignKeysToRestore = [];

        try {
            // Query INFORMATION_SCHEMA to find foreign keys referencing this table's id column
            // Need to JOIN KEY_COLUMN_USAGE with REFERENTIAL_CONSTRAINTS to get UPDATE_RULE and DELETE_RULE
            $fkQuery = \sprintf(
                "SELECT 
                    kcu.CONSTRAINT_NAME,
                    kcu.TABLE_NAME,
                    kcu.COLUMN_NAME,
                    kcu.REFERENCED_TABLE_NAME,
                    kcu.REFERENCED_COLUMN_NAME,
                    rc.UPDATE_RULE,
                    rc.DELETE_RULE
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                INNER JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                    ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                    AND kcu.TABLE_SCHEMA = rc.CONSTRAINT_SCHEMA
                WHERE kcu.TABLE_SCHEMA = DATABASE()
                    AND kcu.REFERENCED_TABLE_NAME = %s
                    AND kcu.REFERENCED_COLUMN_NAME = 'id'
                    AND kcu.CONSTRAINT_NAME != 'PRIMARY'",
                $platform->quoteStringLiteral($tableName)
            );

            $foreignKeys = $connection->fetchAllAssociative($fkQuery);

            // Drop foreign keys that reference this table's id column
            foreach ($foreignKeys as $fk) {
                $fkName = $fk['CONSTRAINT_NAME'];
                $fkTable = $fk['TABLE_NAME'];

                // Store FK info for restoration
                $foreignKeysToRestore[] = [
                    'name' => $fkName,
                    'table' => $fkTable,
                    'column' => $fk['COLUMN_NAME'],
                    'referenced_table' => $fk['REFERENCED_TABLE_NAME'],
                    'referenced_column' => $fk['REFERENCED_COLUMN_NAME'],
                    'update_rule' => $fk['UPDATE_RULE'],
                    'delete_rule' => $fk['DELETE_RULE'],
                ];

                // Drop the foreign key
                $dropFkSql = \sprintf(
                    'ALTER TABLE %s DROP FOREIGN KEY %s',
                    $this->quoteIdentifier($platform, $fkTable),
                    $this->quoteIdentifier($platform, $fkName)
                );

                try {
                    $connection->executeStatement($dropFkSql);
                    $io->text(\sprintf('  Temporarily dropped foreign key <comment>%s</comment> from table <info>%s</info>', $fkName, $fkTable));
                } catch (\Exception $e) {
                    $io->warning(\sprintf('  Could not drop foreign key %s: %s', $fkName, $e->getMessage()));
                }
            }

            // Now modify the id column to add AUTO_INCREMENT
            $alterSql = \sprintf(
                'ALTER TABLE %s MODIFY COLUMN %s INT NOT NULL AUTO_INCREMENT',
                $this->quoteIdentifier($platform, $tableName),
                $this->quoteIdentifier($platform, 'id')
            );
            $connection->executeStatement($alterSql);

            // Restore foreign keys
            foreach ($foreignKeysToRestore as $fkInfo) {
                $restoreFkSql = \sprintf(
                    'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON UPDATE %s ON DELETE %s',
                    $this->quoteIdentifier($platform, $fkInfo['table']),
                    $this->quoteIdentifier($platform, $fkInfo['name']),
                    $this->quoteIdentifier($platform, $fkInfo['column']),
                    $this->quoteIdentifier($platform, $fkInfo['referenced_table']),
                    $this->quoteIdentifier($platform, $fkInfo['referenced_column']),
                    $fkInfo['update_rule'],
                    $fkInfo['delete_rule']
                );

                try {
                    $connection->executeStatement($restoreFkSql);
                    $io->text(\sprintf('  Restored foreign key <comment>%s</comment> on table <info>%s</info>', $fkInfo['name'], $fkInfo['table']));
                } catch (\Exception $e) {
                    $io->error(\sprintf('  Failed to restore foreign key %s: %s', $fkInfo['name'], $e->getMessage()));
                    throw $e;
                }
            }
        } catch (\Exception $e) {
            // If something went wrong, try to restore foreign keys before rethrowing
            foreach ($foreignKeysToRestore as $fkInfo) {
                try {
                    $restoreFkSql = \sprintf(
                        'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON UPDATE %s ON DELETE %s',
                        $platform->quoteIdentifier($fkInfo['table']),
                        $platform->quoteIdentifier($fkInfo['name']),
                        $platform->quoteIdentifier($fkInfo['column']),
                        $platform->quoteIdentifier($fkInfo['referenced_table']),
                        $platform->quoteIdentifier($fkInfo['referenced_column']),
                        $fkInfo['update_rule'],
                        $fkInfo['delete_rule']
                    );
                    $connection->executeStatement($restoreFkSql);
                } catch (\Exception $restoreException) {
                    // Log but don't throw - we want to throw the original exception
                    $io->error(\sprintf('  Failed to restore foreign key %s during error recovery: %s', $fkInfo['name'], $restoreException->getMessage()));
                }
            }
            throw $e;
        }
    }
}
