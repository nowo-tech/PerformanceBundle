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
     * Check if the performance metrics table exists.
     *
     * @return bool True if the table exists, false otherwise
     */
    public function tableExists(): bool
    {
        try {
            $connection = $this->registry->getConnection($this->connectionName);
            $schemaManager = $connection->createSchemaManager();

            // Get the actual table name from entity metadata (after TableNameSubscriber has processed it)
            $entityManager = $this->registry->getManager($this->connectionName);
            $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteData');
            $actualTableName = method_exists($metadata, 'getTableName')
                ? $metadata->getTableName()
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
}
