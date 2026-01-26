<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched before a performance record is deleted.
 *
 * This event allows listeners to prevent deletion or perform actions before deletion.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
final class BeforeRecordDeletedEvent extends Event
{
    private bool $deletionPrevented = false;

    /**
     * Constructor.
     *
     * @param RouteData $routeData The route data entity to be deleted
     */
    public function __construct(
        private readonly RouteData $routeData
    ) {
    }

    public function getRouteData(): RouteData
    {
        return $this->routeData;
    }

    /**
     * Prevent the deletion from happening.
     *
     * @return void
     */
    public function preventDeletion(): void
    {
        $this->deletionPrevented = true;
    }

    public function isDeletionPrevented(): bool
    {
        return $this->deletionPrevented;
    }
}
