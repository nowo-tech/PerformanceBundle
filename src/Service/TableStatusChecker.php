<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service to check the status of the performance metrics database table.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class TableStatusChecker
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
    ) {
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
     * Check if the performance metrics table exists.
     *
     * @return bool True if the table exists, false otherwise
     */
    public function tableExists(): bool
    {
        try {
            $connection = $this->registry->getConnection($this->connectionName);
            $schemaManager = $this->getSchemaManager($connection);

            // Get the actual table name from entity metadata (after TableNameSubscriber has processed it)
            $entityManager = $this->registry->getManager($this->connectionName);
            $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteData');
            // Use method_exists check to avoid linter errors and ensure compatibility
            $hasGetTableName = method_exists($metadata, 'getTableName');
            $actualTableName = $hasGetTableName
                ? $metadata->getTableName() // @phpstan-ignore-line
                : ($metadata->table['name'] ?? $this->tableName);

            return $schemaManager->tablesExist([$actualTableName]);
        } catch (\Exception $e) {
            // If there's any error (e.g., connection issue, metadata not loaded), assume table doesn't exist
            return false;
        }
    }

    /**
     * Get the configured table name.
     *
     * @return string The configured table name
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * Check if the table is complete (exists and has all required columns).
     *
     * @return bool True if the table exists and has all required columns, false otherwise
     */
    public function tableIsComplete(): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        $missingColumns = $this->getMissingColumns();

        return empty($missingColumns);
    }

    /**
     * Get list of missing columns in the table.
     *
     * @return array<string> List of missing column names
     */
    public function getMissingColumns(): array
    {
        try {
            $connection = $this->registry->getConnection($this->connectionName);
            $schemaManager = $this->getSchemaManager($connection);

            // Get the actual table name from entity metadata
            $entityManager = $this->registry->getManager($this->connectionName);
            $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteData');
            $actualTableName = method_exists($metadata, 'getTableName')
                ? $metadata->getTableName()
                : ($metadata->table['name'] ?? $this->tableName);

            // Check if table exists
            if (!$schemaManager->tablesExist([$actualTableName])) {
                // If table doesn't exist, all columns are missing
                return $this->getExpectedColumns($metadata);
            }

            // Get existing columns from database
            $table = $schemaManager->introspectTable($actualTableName);
            $existingColumns = [];
            $connection = $this->registry->getConnection($this->connectionName);
            foreach ($table->getColumns() as $column) {
                // Use getQuotedName() for DBAL 3.x compatibility, fallback to getName() for DBAL 2.x
                $columnName = method_exists($column, 'getQuotedName') 
                    ? $column->getQuotedName($connection->getDatabasePlatform())
                    : (method_exists($column, 'getName') ? $column->getName() : '');
                // Convert Name object to string if needed
                $columnName = \is_string($columnName) ? $columnName : (string) $columnName;
                $existingColumns[strtolower($columnName)] = true;
            }

            // Get expected columns from entity metadata
            $expectedColumns = $this->getExpectedColumns($metadata);
            $missingColumns = [];

            foreach ($expectedColumns as $columnName) {
                if (!isset($existingColumns[strtolower($columnName)])) {
                    $missingColumns[] = $columnName;
                }
            }

            return $missingColumns;
        } catch (\Exception $e) {
            // If there's any error, return empty array (assume we can't check)
            return [];
        }
    }

    /**
     * Get list of expected column names from entity metadata.
     *
     * @param \Doctrine\ORM\Mapping\ClassMetadata $metadata Entity metadata
     *
     * @return array<string> List of expected column names
     */
    private function getExpectedColumns($metadata): array
    {
        $expectedColumns = [];

        // Get all field names from entity metadata
        foreach ($metadata->getFieldNames() as $fieldName) {
            $columnName = $metadata->getColumnName($fieldName);
            $expectedColumns[] = $columnName;
        }

        return $expectedColumns;
    }
}
