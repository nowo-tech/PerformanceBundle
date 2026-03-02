<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Event subscriber to dynamically set the table name for RouteData entity.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class TableNameSubscriber implements EventSubscriber
{
    /**
     * Creates a new instance.
     *
     * @param string $tableName The configured table name for RouteData entity
     */
    public function __construct(
        #[Autowire('%nowo_performance.table_name%')]
        private readonly string $tableName,
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
     * Dynamically sets the table name for RouteData entity based on configuration.
     *
     * @param LoadClassMetadataEventArgs $eventArgs The event arguments
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();

        // Only modify RouteData entity
        if ($classMetadata->getName() !== 'Nowo\PerformanceBundle\Entity\RouteData') {
            return;
        }

        // Always override the table name with the configured value
        // This ensures the table name matches the configuration, even if it's hardcoded in the entity
        $currentTableName = method_exists($classMetadata, 'getTableName')
            ? $classMetadata->getTableName()
            : ($classMetadata->table['name'] ?? 'route_data');

        if ($currentTableName !== $this->tableName) {
            // Get existing table configuration to preserve indexes
            $reflection    = new ReflectionClass($classMetadata);
            $tableProperty = $reflection->getProperty('table');
            $tableProperty->setAccessible(true);
            $existingTable = $tableProperty->getValue($classMetadata);

            // Preserve existing indexes if they exist
            $existingIndexes = $existingTable['indexes'] ?? [];

            // Set the table name with all existing indexes
            $classMetadata->setPrimaryTable([
                'name'    => $this->tableName,
                'indexes' => $existingIndexes,
            ]);
        }
    }
}
