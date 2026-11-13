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
 * Dispatched after the bundle's private write entity manager was flushed. Prefer getRouteDataId(), getRouteName() and
 * getEnv() over the entity: getRouteData() is read-only (see its description).
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
     * Returns the route entity just written.
     *
     * Since 3.16.0 it belongs to the bundle's private write entity manager, which is cleared right after this event:
     * treat it as read-only. Do not persist() / flush() it through your entity manager (persist() would insert a
     * second routes_data row) and do not expect changes to it to be saved. To change bundle data, run a DQL / DBAL
     * UPDATE by getRouteDataId(), or dispatch a message carrying the id. Never flush your application's entity manager
     * from a listener of this event: in sync mode it runs at kernel.terminate and would commit the changes the request
     * left pending.
     *
     * @deprecated since 3.17.0, removed in 4.0: use getRouteDataId(), getRouteName(), getEnv() or getRouteMetrics()
     */
    public function getRouteData(): RouteData
    {
        return $this->routeData;
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
        return RouteMetricsSnapshot::fromRecordedEvent($this);
    }
}
