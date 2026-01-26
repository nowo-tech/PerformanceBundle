<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Event subscriber to dynamically set the table name for RouteData entity.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class TableNameSubscriber implements EventSubscriber
{
    /**
     * Constructor.
     *
     * @param string $tableName The configured table name for RouteData entity
     */
    public function __construct(
        #[Autowire('%nowo_performance.table_name%')]
        private readonly string $tableName
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
     * @return void
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();
        
        // Only modify RouteData entity
        if ($classMetadata->getName() !== 'Nowo\PerformanceBundle\Entity\RouteData') {
            return;
        }

        // Override the table name with the configured value
        $classMetadata->setPrimaryTable([
            'name' => $this->tableName,
            'indexes' => [
                ['name' => 'idx_route_name', 'columns' => ['name']],
                ['name' => 'idx_route_env', 'columns' => ['env']],
            ],
        ]);
    }
}
