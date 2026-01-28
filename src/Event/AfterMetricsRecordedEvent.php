<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched after performance metrics are recorded.
 *
 * Carries the route and the metrics that were just recorded (for alerts).
 * RouteData no longer stores metrics; they are in RouteDataRecord.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class AfterMetricsRecordedEvent extends Event
{
    /**
     * @param RouteData    $routeData    The route data entity
     * @param bool         $isNew        Whether this is a new record (true) or an update (false)
     * @param float|null   $requestTime  The request time just recorded (for alerts)
     * @param int|null     $totalQueries The total queries just recorded (for alerts)
     * @param int|null     $memoryUsage  The memory usage just recorded (for alerts)
     */
    public function __construct(
        private readonly RouteData $routeData,
        private readonly bool $isNew,
        private readonly ?float $requestTime = null,
        private readonly ?int $totalQueries = null,
        private readonly ?int $memoryUsage = null,
    ) {
    }

    public function getRouteData(): RouteData
    {
        return $this->routeData;
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
}
