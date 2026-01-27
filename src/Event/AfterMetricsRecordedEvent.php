<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched after performance metrics are recorded.
 *
 * This event allows listeners to perform actions after metrics are saved.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
final class AfterMetricsRecordedEvent extends Event
{
    /**
     * Constructor.
     *
     * @param RouteData $routeData The route data entity that was saved
     * @param bool      $isNew     Whether this is a new record (true) or an update (false)
     */
    public function __construct(
        private readonly RouteData $routeData,
        private readonly bool $isNew,
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
}
