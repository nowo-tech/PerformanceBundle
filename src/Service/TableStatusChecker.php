<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service to check the status of the performance metrics database table.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class TableStatusChecker
{
    /**
     * Cache service (optional, for caching table status).
     */
    private ?PerformanceCacheService $cacheService = null;

    /**
     * Creates a new instance.
     *
     * @param ManagerRegistry $registry             Doctrine registry
     * @param string          $connectionName       The name of the Doctrine connection to use
     * @param string          $tableName            The configured table name
     * @param bool            $enableAccessRecords  Whether access records (routes_data_records) are enabled
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
    }

    /**
     * Set the cache service (optional, for caching table status).
     *
     * @param PerformanceCacheService|null $cacheService The cache service
     */
    public function setCacheService(?PerformanceCacheService $cacheService): void
    {
        $this->cacheService = $cacheService;
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
        // Try to get from cache first
        if (null !== $this->cacheService) {
            $cacheKey = 'table_exists_'.$this->connectionName.'_'.$this->tableName;
            $cached = $this->cacheService->getCachedValue($cacheKey);
            if (null !== $cached) {
                return (bool) $cached;
            }
        }

        try {
            $connection = $this->registry->getConnection($this->connectionName);
            $schemaManager = $this->getSchemaManager($connection);

            // Get the actual table name from entity metadata (after TableNameSubscriber has processed it)
            $entityManager = $this->registry->getManager($this->connectionName);
            /** @var \Doctrine\ORM\Mapping\ClassMetadata $metadata */
            $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteData');
            // Use method_exists check to avoid linter errors and ensure compatibility
            $hasGetTableName = method_exists($metadata, 'getTableName');
            if ($hasGetTableName) {
                $actualTableName = $metadata->getTableName();
            } else {
                $actualTableName = $metadata->table['name'] ?? $this->tableName;
            }

            $exists = $schemaManager->tablesExist([$actualTableName]);

            // Cache the result for 5 minutes (table structure doesn't change frequently)
            if (null !== $this->cacheService) {
                $cacheKey = 'table_exists_'.$this->connectionName.'_'.$this->tableName;
                $this->cacheService->cacheValue($cacheKey, $exists, 300);
            }

            return $exists;
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
     * Whether access records (routes_data_records) are enabled.
     *
     * @return bool True if enable_access_records is true
     */
    public function isAccessRecordsEnabled(): bool
    {
        return $this->enableAccessRecords;
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

        // Try to get from cache first
        if (null !== $this->cacheService) {
            $cacheKey = 'table_complete_'.$this->connectionName.'_'.$this->tableName;
            $cached = $this->cacheService->getCachedValue($cacheKey);
            if (null !== $cached) {
                return (bool) $cached;
            }
        }

        $missingColumns = $this->getMissingColumns();
        $isComplete = empty($missingColumns);

        // Cache the result for 5 minutes (table structure doesn't change frequently)
        if (null !== $this->cacheService) {
            $cacheKey = 'table_complete_'.$this->connectionName.'_'.$this->tableName;
            $this->cacheService->cacheValue($cacheKey, $isComplete, 300);
        }

        return $isComplete;
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
            /** @var \Doctrine\ORM\Mapping\ClassMetadata $metadata */
            $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteData');
            // Use method_exists check to avoid linter errors and ensure compatibility
            $hasGetTableName = method_exists($metadata, 'getTableName');
            if ($hasGetTableName) {
                $actualTableName = $metadata->getTableName();
            } else {
                $actualTableName = $metadata->table['name'] ?? $this->tableName;
            }

            // Check if table exists
            if (!$schemaManager->tablesExist([$actualTableName])) {
                // If table doesn't exist, all columns are missing
                return $this->getExpectedColumns($metadata);
            }

            // Get existing columns from database
            $table = $schemaManager->introspectTable($actualTableName);
            $existingColumns = [];
            foreach ($table->getColumns() as $column) {
                // Use getName() directly - getQuotedName() is deprecated in DBAL 3.x
                // Column names from introspectTable() are already unquoted
                $columnName = method_exists($column, 'getName') ? $column->getName() : '';
                // Convert Name object to string if needed
                $columnName = \is_string($columnName) ? $columnName : (string) $columnName;
                // Remove quotes if present (shouldn't be, but just in case)
                $columnName = trim($columnName, '`"\'');
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

    /**
     * Check if the access records table exists (only when enable_access_records is true).
     *
     * @return bool True if the table exists or access records are disabled, false otherwise
     */
    public function recordsTableExists(): bool
    {
        if (!$this->enableAccessRecords) {
            return true; // N/A, consider "ok"
        }

        try {
            $connection = $this->registry->getConnection($this->connectionName);
            $schemaManager = $this->getSchemaManager($connection);
            $recordsTableName = $this->getRecordsTableName();

            return $schemaManager->tablesExist([$recordsTableName]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if the access records table is complete (exists and has all entity columns).
     *
     * @return bool True if table exists and has all required columns (or access records disabled)
     */
    public function recordsTableIsComplete(): bool
    {
        if (!$this->enableAccessRecords) {
            return true;
        }
        if (!$this->recordsTableExists()) {
            return false;
        }

        return empty($this->getRecordsMissingColumns());
    }

    /**
     * Get list of missing columns in the access records table.
     *
     * @return array<string> List of missing column names (empty if access records disabled or table missing)
     */
    public function getRecordsMissingColumns(): array
    {
        if (!$this->enableAccessRecords) {
            return [];
        }

        try {
            $connection = $this->registry->getConnection($this->connectionName);
            $schemaManager = $this->getSchemaManager($connection);
            $entityManager = $this->registry->getManager($this->connectionName);
            /** @var \Doctrine\ORM\Mapping\ClassMetadata $metadata */
            $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteDataRecord');
            $recordsTableName = $this->getRecordsTableName();

            if (!$schemaManager->tablesExist([$recordsTableName])) {
                return $this->getExpectedColumns($metadata);
            }

            $table = $schemaManager->introspectTable($recordsTableName);
            $existingColumns = [];
            foreach ($table->getColumns() as $column) {
                $columnName = method_exists($column, 'getName') ? $column->getName() : '';
                $columnName = \is_string($columnName) ? $columnName : (string) $columnName;
                $columnName = trim($columnName, '`"\'');
                $existingColumns[strtolower($columnName)] = true;
            }

            $expectedColumns = $this->getExpectedColumns($metadata);
            $missingColumns = [];
            foreach ($expectedColumns as $columnName) {
                if (!isset($existingColumns[strtolower($columnName)])) {
                    $missingColumns[] = $columnName;
                }
            }

            return $missingColumns;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get the access records table name (from RouteDataRecord entity metadata).
     *
     * @return string Table name (e.g. routes_data_records)
     */
    public function getRecordsTableName(): string
    {
        try {
            $entityManager = $this->registry->getManager($this->connectionName);
            /** @var \Doctrine\ORM\Mapping\ClassMetadata $metadata */
            $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteDataRecord');

            return method_exists($metadata, 'getTableName')
                ? $metadata->getTableName()
                : ($metadata->table['name'] ?? $this->tableName.'_records');
        } catch (\Exception $e) {
            return $this->tableName.'_records';
        }
    }
}
