<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

use Doctrine\Common\EventManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates the private entity manager the bundle records metrics with.
 *
 * The private manager shares the connection, configuration (mappings, metadata cache, repository factory) and event
 * manager of the configured one, but has its own unit of work: flushing it only writes the bundle's own entities, never
 * a change the host application left pending in its entity manager, and closing it never closes the host's manager.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class MetricsEntityManagerFactory
{
    /**
     * Creates a fresh entity manager on the connection of the given one.
     */
    public function create(EntityManagerInterface $source): EntityManagerInterface
    {
        // DoctrineBundle's ContainerAwareEventManager extends EventManager; any other implementation gets a fresh one.
        $eventManager = $source->getEventManager();

        return new EntityManager(
            $source->getConnection(),
            $source->getConfiguration(),
            $eventManager instanceof EventManager ? $eventManager : null,
        );
    }
}
