<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched after a performance record is marked as reviewed.
 *
 * This event allows listeners to perform actions after review.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class AfterRecordReviewedEvent extends Event
{
    /**
     * Creates a new instance.
     *
     * @param RouteData $routeData The route data entity that was reviewed
     */
    public function __construct(
        private readonly RouteData $routeData,
    ) {
    }

    public function getRouteData(): RouteData
    {
        return $this->routeData;
    }
}
