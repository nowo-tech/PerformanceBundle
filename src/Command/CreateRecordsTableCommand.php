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
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(
    name: 'nowo:performance:create-records-table',
    description: 'Create the access records database table for temporal analysis',
)]
class CreateRecordsTableCommand extends Command
{
    /**
     * Creates a new instance.
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
        $this->setHelp(<<<'HELP'
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

To sync schema: add missing, alter differing, and drop columns not in entity (use with --update):
<info>php %command.full_name% --update --drop-obsolete</info>

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
        );

        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force table creation even if it already exists')
            ->addOption('update', 'u', InputOption::VALUE_NONE, 'Add missing columns to existing table without losing data')
            ->addOption('drop-obsolete', null, InputOption::VALUE_NONE, 'Drop columns that exist in DB but not in entity (use with --update)');
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
            if (method_exists($metadata, 'getTableName')) {
                /** @var callable $getTableName */
                $getTableName = [$metadata, 'getTableName'];
                $actualTableName = $getTableName();
            } else {
                $actualTableName = $metadata->table['name'] ?? $this->mainTableName.'_records';
            }

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

                $this->updateTableSchema($entityManager, $io, $input->getOption('drop-obsolete'));

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
            // Ensure AUTO_INCREMENT is set for id column in MySQL/MariaDB
            $platformClass = $platform::class;
            $isMySQL = $platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform
                || str_contains(strtolower($platformClass), 'mysql')
                || str_contains(strtolower($platformClass), 'mariadb');

            if ($isMySQL) {
                if (preg_match('/CREATE\s+TABLE/i', $statement)
                    && preg_match('/`?id`?\s+INT/i', $statement)
                    && !preg_match('/AUTO_INCREMENT/i', $statement)) {
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

            $io->text(\sprintf('  <comment>%s</comment>', $statement));
            $connection->executeStatement($statement);
        }

        $io->text('✓ Table and indexes created.');
    }

    /**
     * Update the table schema by adding missing columns, updating existing ones, and optionally dropping obsolete columns.
     *
     * @param EntityManagerInterface $entityManager The entity manager
     * @param SymfonyStyle           $io            The Symfony style output
     * @param bool                   $dropObsolete  Whether to drop columns that exist in DB but not in entity
     */
    private function updateTableSchema(EntityManagerInterface $entityManager, SymfonyStyle $io, bool $dropObsolete = false): void
    {
        $connection = $entityManager->getConnection();
        $schemaManager = $this->getSchemaManager($connection);

        // Get the actual table name from entity metadata
        $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteDataRecord');
        // Get table name from metadata (compatible with different Doctrine versions)
        if (method_exists($metadata, 'getTableName')) {
            /** @var callable $getTableName */
            $getTableName = [$metadata, 'getTableName'];
            $actualTableName = $getTableName();
        } else {
            $actualTableName = $metadata->table['name'] ?? $this->mainTableName.'_records';
        }

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

        $platform = $connection->getDatabasePlatform();
        $platformClass = $platform::class;
        $isMySQL = $platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform
            || str_contains(strtolower($platformClass), 'mysql')
            || str_contains(strtolower($platformClass), 'mariadb');

        if ($isMySQL && isset($existingColumnsMap['id'])) {
            $idColumn = $existingColumnsMap['id'];
            $columnType = $idColumn->getType();
            $sqlDeclaration = strtolower($columnType->getSQLDeclaration([], $platform));
            $isIntegerType = str_contains($sqlDeclaration, 'int');

            if ($isIntegerType) {
                $checkSql = \sprintf(
                    "SELECT COLUMN_NAME, EXTRA, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'id'",
                    $platform->quoteStringLiteral($actualTableName)
                );
                try {
                    $result = $connection->fetchAssociative($checkSql);
                    if ($result && !str_contains(strtoupper($result['EXTRA'] ?? ''), 'AUTO_INCREMENT')) {
                        $io->warning('Column "id" does not have AUTO_INCREMENT. Fixing...');
                        $colType = $result['COLUMN_TYPE'] ?? 'INT';
                        if (!preg_match('/^(?:tinyint|smallint|mediumint|int|bigint)\b/i', $colType)) {
                            $colType = 'INT';
                        }
                        $q = $this->quoteIdentifier($platform, $actualTableName);
                        $sql = \sprintf('ALTER TABLE %s MODIFY COLUMN id %s NOT NULL AUTO_INCREMENT', $q, $colType);
                        $connection->executeStatement($sql);
                        $io->success('✓ Column "id" now has AUTO_INCREMENT');
                        $table = $schemaManager->introspectTable($actualTableName);
                        $existingColumnsMap = [];
                        foreach ($table->getColumns() as $column) {
                            $colName = $this->getColumnName($column, $connection);
                            $existingColumnsMap[strtolower($colName)] = $column;
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

            $defaultValue = $options['default'] ?? $fieldMappingArray['default'] ?? null;

            $isAutoincrement = false;
            if ($metadata->isIdentifier($fieldName)) {
                $fieldType = $metadata->getTypeOfField($fieldName);
                if (\in_array(strtolower($fieldType), ['integer', 'int', 'smallint', 'bigint'], true)) {
                    $generatorType = $metadata->generatorType ?? null;
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
        $columnsToDrop = [];
        $platform = $connection->getDatabasePlatform();

        // Compare each expected column with existing ones
        foreach ($expectedColumns as $columnNameLower => $expectedInfo) {
            $columnName = $expectedInfo['column'];

            if (!isset($existingColumnsMap[$columnNameLower])) {
                $columnsToAdd[$columnName] = $expectedInfo;
            } else {
                $existingColumn = $existingColumnsMap[$columnNameLower];
                if ($this->columnNeedsUpdate($existingColumn, $expectedInfo, $platform)) {
                    $columnsToUpdate[$columnName] = [
                        'expected' => $expectedInfo,
                        'existing' => $existingColumn,
                    ];
                }
            }
        }

        if ($dropObsolete) {
            foreach ($table->getColumns() as $column) {
                $columnName = $this->getColumnName($column, $connection);
                $columnNameLower = strtolower($columnName);
                if ('id' === $columnNameLower) {
                    continue;
                }
                if (!isset($expectedColumns[$columnNameLower])) {
                    $columnsToDrop[] = $columnName;
                }
            }
        }

        $hasChanges = !empty($columnsToAdd) || !empty($columnsToUpdate) || !empty($columnsToDrop);

        if (!$hasChanges) {
            $io->success('All columns are up to date. No changes needed.');
            $this->addMissingIndexes($entityManager, $io, $table);

            return;
        }

        $io->text(\sprintf('Using table name: <info>%s</info>', $actualTableName));
        $io->newLine();

        // Drop obsolete columns first (same order as CreateTableCommand)
        if (!empty($columnsToDrop)) {
            $io->section(\sprintf('Dropping <info>%d</info> obsolete column(s):', \count($columnsToDrop)));
            foreach ($columnsToDrop as $columnName) {
                $io->text(\sprintf('  - <comment>%s</comment>', $columnName));
                $sql = \sprintf(
                    'ALTER TABLE %s DROP COLUMN %s',
                    $this->quoteIdentifier($platform, $actualTableName),
                    $this->quoteIdentifier($platform, $columnName)
                );
                try {
                    $connection->executeStatement($sql);
                    $io->text(\sprintf('  ✓ Dropped column <info>%s</info>', $columnName));
                } catch (\Exception $e) {
                    $io->error(\sprintf('  ✗ Failed to drop column %s: %s', $columnName, $e->getMessage()));
                    throw $e;
                }
            }
            $io->newLine();
            $table = $schemaManager->introspectTable($actualTableName);
            $existingColumnsMap = [];
            foreach ($table->getColumns() as $column) {
                $columnName = $this->getColumnName($column, $connection);
                $existingColumnsMap[strtolower($columnName)] = $column;
            }
        }

        // Add missing columns (before Update, same order as CreateTableCommand)
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

        // Update existing columns
        if (!empty($columnsToUpdate)) {
            $io->section(\sprintf('Updating <info>%d</info> column(s) with differences:', \count($columnsToUpdate)));
            foreach ($columnsToUpdate as $columnName => $columnData) {
                $expected = $columnData['expected'];
                $io->text(\sprintf('  - <comment>%s</comment>', $columnName));
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
            $io->newLine();
        }

        // Add missing indexes
        $this->addMissingIndexes($entityManager, $io, $table);
    }

    /**
     * Quote identifier (DBAL 2.x / 3.x compatible).
     */
    private function quoteIdentifier(\Doctrine\DBAL\Platforms\AbstractPlatform $platform, string $identifier): string
    {
        if (method_exists($platform, 'quoteSingleIdentifier')) {
            return $platform->quoteSingleIdentifier($identifier);
        }

        return $platform->quoteIdentifier($identifier);
    }

    /**
     * Get column name from Column (DBAL 2.x / 3.x compatible).
     */
    private function getColumnName(\Doctrine\DBAL\Schema\Column $column, \Doctrine\DBAL\Connection $connection): string
    {
        if (method_exists($column, 'getQuotedName')) {
            return $column->getQuotedName($connection->getDatabasePlatform());
        }
        if (method_exists($column, 'getName')) {
            $name = $column->getName();

            return \is_string($name) ? $name : (string) $name;
        }
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
     * Check if a column needs to be updated.
     */
    private function columnNeedsUpdate(
        \Doctrine\DBAL\Schema\Column $existingColumn,
        array $expectedInfo,
        \Doctrine\DBAL\Platforms\AbstractPlatform $platform,
    ): bool {
        if ($existingColumn->getNotnull() !== !$expectedInfo['nullable']) {
            return true;
        }
        $expectedType = $this->getColumnSQLType($expectedInfo, $platform);
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
        $existingType = $existingColumn->getType()->getSQLDeclaration($columnArray, $platform);
        $expectedTypeNormalized = preg_replace('/\([^)]+\)/', '', $expectedType);
        $existingTypeNormalized = preg_replace('/\([^)]+\)/', '', $existingType);
        if (strtolower($expectedTypeNormalized) !== strtolower($existingTypeNormalized)) {
            return true;
        }
        if ('string' === $expectedInfo['type'] && null !== $expectedInfo['length']) {
            $existingLength = $existingColumn->getLength();
            if ($existingLength !== $expectedInfo['length']) {
                return true;
            }
        }
        $expectedDefault = $expectedInfo['default'] ?? null;
        $existingDefault = $existingColumn->getDefault();
        if (null === $expectedDefault && null === $existingDefault) {
            return false;
        }
        if (\is_bool($expectedDefault) && null !== $existingDefault) {
            $normalizedExisting = \in_array(strtolower((string) $existingDefault), ['1', 'true', 'yes'], true);

            return $normalizedExisting !== $expectedDefault;
        }
        if (is_numeric($expectedDefault) && null !== $existingDefault) {
            return (float) $expectedDefault !== (float) $existingDefault;
        }

        return $expectedDefault !== $existingDefault;
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
        $autoincrement = '';
        $platformClass = $platform::class;
        $isMySQL = $platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform
            || str_contains(strtolower($platformClass), 'mysql')
            || str_contains(strtolower($platformClass), 'mariadb');
        if (!empty($columnInfo['autoincrement']) && $isMySQL) {
            $autoincrement = ' AUTO_INCREMENT';
        }

        return $sqlType.$nullConstraint.$default.$autoincrement;
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
                if (!empty($columnInfo['autoincrement'])) {
                    $column['autoincrement'] = true;
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
                    $expectedIndexes[$indexName] = ['columns' => $columns, 'unique' => false];
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
                        $expectedIndexes[$indexName] = ['columns' => $columns, 'unique' => false];
                    }
                }
            } catch (\ReflectionException $e) {
                // Ignore reflection errors
            }
        }

        // Normalize expectedIndexes to array with 'columns' and 'unique'
        $normalizedExpected = [];
        foreach ($expectedIndexes as $indexName => $columns) {
            $normalizedExpected[$indexName] = \is_array($columns) && isset($columns['columns'])
                ? $columns
                : ['columns' => \is_array($columns) ? $columns : [], 'unique' => false];
        }

        // Get unique constraints from entity metadata
        $expectedUniqueConstraints = [];
        if (isset($metadata->table['uniqueConstraints']) && \is_array($metadata->table['uniqueConstraints'])) {
            foreach ($metadata->table['uniqueConstraints'] as $constraintName => $def) {
                $columns = $def['columns'] ?? [];
                if (!empty($columns)) {
                    $expectedUniqueConstraints[$constraintName] = $columns;
                }
            }
        }
        if (\PHP_VERSION_ID >= 80000) {
            try {
                $reflection = new \ReflectionClass('Nowo\PerformanceBundle\Entity\RouteDataRecord');
                $attributes = $reflection->getAttributes(\Doctrine\ORM\Mapping\UniqueConstraint::class);
                foreach ($attributes as $attribute) {
                    $constraint = $attribute->newInstance();
                    $constraintName = $constraint->name ?? null;
                    $columns = $constraint->columns ?? [];
                    if ($constraintName && !empty($columns)) {
                        $expectedUniqueConstraints[$constraintName] = $columns;
                    }
                }
            } catch (\ReflectionException $e) {
                // Ignore reflection errors
            }
        }
        foreach ($expectedUniqueConstraints as $constraintName => $columns) {
            $normalizedExpected[$constraintName] = ['columns' => $columns, 'unique' => true];
        }

        $existingIndexes = [];
        foreach ($table->getIndexes() as $index) {
            $existingIndexes[strtolower($index->getName())] = $index;
        }

        $indexesToAdd = [];
        foreach ($normalizedExpected as $indexName => $data) {
            $columns = $data['columns'];
            $isUnique = $data['unique'] ?? false;
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
                    $indexesToAdd[$indexName] = ['columns' => $columns, 'unique' => $isUnique];
                }
            }
        }

        if (empty($indexesToAdd)) {
            return;
        }

        $io->text(\sprintf('Adding <info>%d</info> missing index(es):', \count($indexesToAdd)));

        foreach ($indexesToAdd as $indexName => $data) {
            $columns = $data['columns'];
            $isUnique = $data['unique'] ?? false;
            $quotedColumns = array_map(fn ($col) => $this->quoteIdentifier($platform, $col), $columns);

            // Get the actual table name from entity metadata
            $actualTableName = method_exists($metadata, 'getTableName')
                ? $metadata->getTableName()
                : ($metadata->table['name'] ?? $this->mainTableName.'_records');

            $indexType = $isUnique ? 'UNIQUE INDEX' : 'INDEX';
            $sql = \sprintf(
                'CREATE %s %s ON %s (%s)',
                $indexType,
                $this->quoteIdentifier($platform, $indexName),
                $this->quoteIdentifier($platform, $actualTableName),
                implode(', ', $quotedColumns)
            );

            try {
                $connection->executeStatement($sql);
                $io->text(\sprintf('  ✓ Created %s <info>%s</info> on columns: %s', $isUnique ? 'unique index' : 'index', $indexName, implode(', ', $columns)));
            } catch (\Exception $e) {
                $io->warning(\sprintf('  ✗ Failed to create index %s: %s', $indexName, $e->getMessage()));
            }
        }
    }
}
