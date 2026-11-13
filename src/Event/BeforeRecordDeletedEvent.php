<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Model\RouteMetricsSnapshot;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched before a performance record is deleted.
 *
 * This event allows listeners to prevent deletion or perform actions before deletion.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class BeforeRecordDeletedEvent extends Event
{
    private bool $deletionPrevented = false;

    /**
     * Creates a new instance.
     *
     * @param RouteData $routeData The route data entity to be deleted
     */
    public function __construct(
        private readonly RouteData $routeData,
    ) {
    }

    /**
     * Returns the route entity.
     *
     * @deprecated since 3.17.0, removed in 4.0: use getRouteDataId(), getRouteName(), getEnv() or getRouteMetrics()
     */
    public function getRouteData(): RouteData
    {
        return $this->routeData;
    }

    /**
     * Returns the id of the routes_data row (since 3.17.0; null only for a row never flushed).
     */
    public function getRouteDataId(): ?int
    {
        return $this->routeData->getId();
    }

    /**
     * Returns the route name (since 3.17.0).
     */
    public function getRouteName(): ?string
    {
        return $this->routeData->getName();
    }

    /**
     * Returns the environment (since 3.17.0).
     */
    public function getEnv(): ?string
    {
        return $this->routeData->getEnv();
    }

    /**
     * Returns a framework-free copy of the route row (since 3.17.0), taken when called. Prefer it over getRouteData().
     */
    public function getRouteMetrics(): RouteMetricsSnapshot
    {
        return RouteMetricsSnapshot::fromRouteData($this->routeData);
    }

    /**
     * Prevent the deletion from happening.
     */
    public function preventDeletion(): void
    {
        // @igor-ignore - Request-scoped Event DTO; not a shared worker service.
        $this->deletionPrevented = true;
    }

    public function isDeletionPrevented(): bool
    {
        return $this->deletionPrevented;
    }
}
