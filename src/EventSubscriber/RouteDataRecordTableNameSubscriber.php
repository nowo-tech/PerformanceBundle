<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Event subscriber to dynamically set the table name for RouteDataRecord entity.
 *
 * The table name is derived from the main table name + '_records'.
 * For example, if the main table is 'routes_data', this will be 'routes_data_records'.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class RouteDataRecordTableNameSubscriber implements EventSubscriber
{
    /**
     * Creates a new instance.
     *
     * @param string $mainTableName The configured table name for RouteData entity
     */
    public function __construct(
        #[Autowire('%nowo_performance.table_name%')]
        private readonly string $mainTableName,
    ) {
    }

    /**
     * Get the subscribed Doctrine events.
     *
     * @return string[] Array of event names
     */
    public function getSubscribedEvents(): array
    {
        return [Events::loadClassMetadata];
    }

    /**
     * Handle the loadClassMetadata event.
     *
     * Dynamically sets the table name for RouteDataRecord entity based on the main table name.
     *
     * @param LoadClassMetadataEventArgs $eventArgs The event arguments
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();

        // Only modify RouteDataRecord entity
        if ($classMetadata->getName() !== 'Nowo\PerformanceBundle\Entity\RouteDataRecord') {
            return;
        }

        // Table name is main table name + '_records'
        $recordsTableName = $this->mainTableName . '_records';

        // Get existing table configuration to preserve indexes
        $currentTableName = method_exists($classMetadata, 'getTableName')
            ? $classMetadata->getTableName()
            : ($classMetadata->table['name'] ?? 'routes_data_records');

        if ($currentTableName !== $recordsTableName) {
            // Get existing indexes
            $reflection    = new ReflectionClass($classMetadata);
            $tableProperty = $reflection->getProperty('table');
            $tableProperty->setAccessible(true);
            $existingTable   = $tableProperty->getValue($classMetadata);
            $existingIndexes = $existingTable['indexes'] ?? [];

            // Set the table name with all existing indexes
            $classMetadata->setPrimaryTable([
                'name'    => $recordsTableName,
                'indexes' => $existingIndexes,
            ]);
        }
    }
}
