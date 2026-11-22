<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Model\RouteMetricsSnapshot;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched after performance metrics are recorded.
 *
 * Carries the route and the metrics that were just recorded (for alerts).
 * RouteData no longer stores metrics; they are in RouteDataRecord.
 *
 * Dispatched after the bundle's private write entity manager was flushed. Read getRouteDataId(), getRouteName(),
 * getEnv() or getRouteMetrics(); the entity accessor getRouteData() was removed in 4.0 (deprecated since 3.17.0). To
 * change bundle data from a listener, run a DQL / DBAL UPDATE by getRouteDataId() or dispatch a message carrying the
 * id; never flush your application's entity manager here (in sync mode this event runs at kernel.terminate).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class AfterMetricsRecordedEvent extends Event
{
    /**
     * @param RouteData $routeData The route data entity
     * @param bool $isNew Whether this is a new record (true) or an update (false)
     * @param float|null $requestTime The request time just recorded (for alerts)
     * @param int|null $totalQueries The total queries just recorded (for alerts)
     * @param int|null $memoryUsage The memory usage just recorded (for alerts)
     */
    public function __construct(
        private readonly RouteData $routeData,
        private readonly bool $isNew,
        private readonly ?float $requestTime = null,
        private readonly ?int $totalQueries = null,
        private readonly ?int $memoryUsage = null,
    ) {
    }

    /**
     * Returns the id of the routes_data row just written (null only if the entity was never flushed).
     */
    public function getRouteDataId(): ?int
    {
        return $this->routeData->getId();
    }

    /**
     * Returns the route name just recorded.
     */
    public function getRouteName(): ?string
    {
        return $this->routeData->getName();
    }

    /**
     * Returns the environment the route was recorded for.
     */
    public function getEnv(): ?string
    {
        return $this->routeData->getEnv();
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    /** Request time (s) just recorded. */
    public function getRequestTime(): ?float
    {
        return $this->requestTime;
    }

    /** Total queries just recorded. */
    public function getTotalQueries(): ?int
    {
        return $this->totalQueries;
    }

    /** Memory usage (bytes) just recorded. */
    public function getMemoryUsage(): ?int
    {
        return $this->memoryUsage;
    }

    /**
     * Returns a framework-free copy of the route row plus the metrics just recorded (since 3.17.0), taken when called.
     */
    public function getRouteMetrics(): RouteMetricsSnapshot
    {
        $route = RouteMetricsSnapshot::fromRouteData($this->routeData);

        return new RouteMetricsSnapshot(
            routeDataId: $route->routeDataId,
            routeName: $route->routeName,
            env: $route->env,
            httpMethod: $route->httpMethod,
            reviewed: $route->reviewed,
            reviewedAt: $route->reviewedAt,
            reviewedBy: $route->reviewedBy,
            queriesImproved: $route->queriesImproved,
            timeImproved: $route->timeImproved,
            lastAccessedAt: $route->lastAccessedAt,
            isNew: $this->isNew,
            requestTime: $this->requestTime,
            totalQueries: $this->totalQueries,
            memoryUsage: $this->memoryUsage,
        );
    }
}
