<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Model\RouteMetricsSnapshot;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched before a performance record is marked as reviewed.
 *
 * This event allows listeners to prevent review or modify review data.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class BeforeRecordReviewedEvent extends Event
{
    private bool $reviewPrevented = false;

    /**
     * Creates a new instance.
     *
     * @param RouteData $routeData The route data entity to be reviewed
     * @param bool|null $queriesImproved Whether queries improved
     * @param bool|null $timeImproved Whether time improved
     * @param string|null $reviewedBy The reviewer username
     */
    public function __construct(
        private readonly RouteData $routeData,
        private ?bool $queriesImproved,
        private ?bool $timeImproved,
        private ?string $reviewedBy,
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

    public function getQueriesImproved(): ?bool
    {
        return $this->queriesImproved;
    }

    public function setQueriesImproved(?bool $queriesImproved): void
    {
        // @igor-ignore - Request-scoped Event DTO; not a shared worker service.
        $this->queriesImproved = $queriesImproved;
    }

    public function getTimeImproved(): ?bool
    {
        return $this->timeImproved;
    }

    public function setTimeImproved(?bool $timeImproved): void
    {
        // @igor-ignore - Request-scoped Event DTO; not a shared worker service.
        $this->timeImproved = $timeImproved;
    }

    public function getReviewedBy(): ?string
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?string $reviewedBy): void
    {
        // @igor-ignore - Request-scoped Event DTO; not a shared worker service.
        $this->reviewedBy = $reviewedBy;
    }

    /**
     * Prevent the review from happening.
     */
    public function preventReview(): void
    {
        // @igor-ignore - Request-scoped Event DTO; not a shared worker service.
        $this->reviewPrevented = true;
    }

    public function isReviewPrevented(): bool
    {
        return $this->reviewPrevented;
    }
}
