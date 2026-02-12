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
     * Cache TTL in seconds.
     */
    private const CACHE_TTL_SECONDS = 300;

    /**
     * Cache service (optional, for caching table status).
     */
    private ?PerformanceCacheService $cacheService = null;

    /**
     * Creates a new instance.
     *
     * @param ManagerRegistry $registry            Doctrine registry
     * @param string          $connectionName      The name of the Doctrine connection to use
     * @param string          $tableName           The configured table name
     * @param bool            $enableAccessRecords Whether access records (routes_data_records) are enabled
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

            // Cache the result (table structure doesn't change frequently)
            if (null !== $this->cacheService) {
                $cacheKey = 'table_exists_'.$this->connectionName.'_'.$this->tableName;
                $this->cacheService->cacheValue($cacheKey, $exists, self::CACHE_TTL_SECONDS);
            }

            return $exists;
        } catch (\Exception $e) {
            // If there's any error (e.g., connection issue, metadata not loaded), assume table doesn't exist
            return false;
        }
    }

    /**
     * Get main table status in a single batch (avoids N+1 when collector needs exists, complete, name, missing columns).
     *
     * @return array{exists: bool, complete: bool, table_name: string, missing_columns: array<string>}
     */
    public function getMainTableStatus(): array
    {
        $tableName = $this->tableName;
        $exists = $this->tableExists();
        $missingColumns = $exists ? $this->getMissingColumns() : [];
        $complete = $exists && empty($missingColumns);

        return [
            'exists' => $exists,
            'complete' => $complete,
            'table_name' => $tableName,
            'missing_columns' => $missingColumns,
        ];
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

        // Cache the result (table structure doesn't change frequently)
        if (null !== $this->cacheService) {
            $cacheKey = 'table_complete_'.$this->connectionName.'_'.$this->tableName;
            $this->cacheService->cacheValue($cacheKey, $isComplete, self::CACHE_TTL_SECONDS);
        }

        return $isComplete;
    }

    /**
     * Get list of missing columns in the table.
     *
     * Result is cached (filesystem via PerformanceCacheService) for CACHE_TTL_SECONDS.
     *
     * @return array<string> List of missing column names
     */
    public function getMissingColumns(): array
    {
        $cacheKey = 'missing_columns_'.$this->connectionName.'_'.$this->tableName;

        if (null !== $this->cacheService) {
            $cached = $this->cacheService->getCachedValue($cacheKey);
            if (null !== $cached && \is_array($cached)) {
                return $cached;
            }
        }

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
                $missing = $this->getExpectedColumns($metadata);
                if (null !== $this->cacheService) {
                    $this->cacheService->cacheValue($cacheKey, $missing, self::CACHE_TTL_SECONDS);
                }

                return $missing;
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

            if (null !== $this->cacheService) {
                $this->cacheService->cacheValue($cacheKey, $missingColumns, self::CACHE_TTL_SECONDS);
            }

            return $missingColumns;
        } catch (\Exception $e) {
            // If there's any error, return empty array (assume we can't check). Do not cache errors.
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
     * Result is cached for CACHE_TTL_SECONDS to avoid repeated introspection.
     *
     * @return bool True if the table exists or access records are disabled, false otherwise
     */
    public function recordsTableExists(): bool
    {
        if (!$this->enableAccessRecords) {
            return true; // N/A, consider "ok"
        }

        if (null !== $this->cacheService) {
            $cacheKey = 'records_table_exists_'.$this->connectionName;
            $cached = $this->cacheService->getCachedValue($cacheKey);
            if (null !== $cached) {
                return (bool) $cached;
            }
        }

        try {
            $connection = $this->registry->getConnection($this->connectionName);
            $schemaManager = $this->getSchemaManager($connection);
            $recordsTableName = $this->getRecordsTableName();

            $exists = $schemaManager->tablesExist([$recordsTableName]);

            if (null !== $this->cacheService) {
                $cacheKey = 'records_table_exists_'.$this->connectionName;
                $this->cacheService->cacheValue($cacheKey, $exists, self::CACHE_TTL_SECONDS);
            }

            return $exists;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get records table status in a single batch (avoids N+1 when collector needs exists, complete, name, missing columns).
     *
     * @return array{exists: bool, complete: bool, table_name: string, missing_columns: array<string>}|null Null when access records are disabled
     */
    public function getRecordsTableStatus(): ?array
    {
        if (!$this->enableAccessRecords) {
            return null;
        }

        $tableName = $this->getRecordsTableName();
        $exists = $this->recordsTableExists();
        $missingColumns = $exists ? $this->getRecordsMissingColumns() : [];
        $complete = $exists && empty($missingColumns);

        return [
            'exists' => $exists,
            'complete' => $complete,
            'table_name' => $tableName,
            'missing_columns' => $missingColumns,
        ];
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
     * Result is cached for CACHE_TTL_SECONDS (same as getMissingColumns).
     *
     * @return array<string> List of missing column names (empty if access records disabled or table missing)
     */
    public function getRecordsMissingColumns(): array
    {
        if (!$this->enableAccessRecords) {
            return [];
        }

        $cacheKey = 'records_missing_columns_'.$this->connectionName;

        if (null !== $this->cacheService) {
            $cached = $this->cacheService->getCachedValue($cacheKey);
            if (null !== $cached && \is_array($cached)) {
                return $cached;
            }
        }

        try {
            $connection = $this->registry->getConnection($this->connectionName);
            $schemaManager = $this->getSchemaManager($connection);
            $entityManager = $this->registry->getManager($this->connectionName);
            /** @var \Doctrine\ORM\Mapping\ClassMetadata $metadata */
            $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteDataRecord');
            $recordsTableName = $this->getRecordsTableName();

            if (!$schemaManager->tablesExist([$recordsTableName])) {
                $missing = $this->getExpectedColumns($metadata);
                if (null !== $this->cacheService) {
                    $this->cacheService->cacheValue($cacheKey, $missing, self::CACHE_TTL_SECONDS);
                }

                return $missing;
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

            if (null !== $this->cacheService) {
                $this->cacheService->cacheValue($cacheKey, $missingColumns, self::CACHE_TTL_SECONDS);
            }

            return $missingColumns;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get the access records table name (from RouteDataRecord entity metadata).
     * Cached for CACHE_TTL_SECONDS to avoid repeated metadata lookups.
     *
     * @return string Table name (e.g. routes_data_records)
     */
    public function getRecordsTableName(): string
    {
        if (null !== $this->cacheService) {
            $cacheKey = 'records_table_name_'.$this->connectionName;
            $cached = $this->cacheService->getCachedValue($cacheKey);
            if (null !== $cached && \is_string($cached)) {
                return $cached;
            }
        }

        try {
            $entityManager = $this->registry->getManager($this->connectionName);
            /** @var \Doctrine\ORM\Mapping\ClassMetadata $metadata */
            $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteDataRecord');

            $name = method_exists($metadata, 'getTableName')
                ? $metadata->getTableName()
                : ($metadata->table['name'] ?? $this->tableName.'_records');

            if (null !== $this->cacheService) {
                $cacheKey = 'records_table_name_'.$this->connectionName;
                $this->cacheService->cacheValue($cacheKey, $name, self::CACHE_TTL_SECONDS);
            }

            return $name;
        } catch (\Exception $e) {
            return $this->tableName.'_records';
        }
    }
}
