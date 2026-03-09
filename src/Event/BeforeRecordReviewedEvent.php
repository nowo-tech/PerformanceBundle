<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
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

    public function getRouteData(): RouteData
    {
        return $this->routeData;
    }

    public function getQueriesImproved(): ?bool
    {
        return $this->queriesImproved;
    }

    public function setQueriesImproved(?bool $queriesImproved): void
    {
        $this->queriesImproved = $queriesImproved;
    }

    public function getTimeImproved(): ?bool
    {
        return $this->timeImproved;
    }

    public function setTimeImproved(?bool $timeImproved): void
    {
        $this->timeImproved = $timeImproved;
    }

    public function getReviewedBy(): ?string
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?string $reviewedBy): void
    {
        $this->reviewedBy = $reviewedBy;
    }

    /**
     * Prevent the review from happening.
     */
    public function preventReview(): void
    {
        $this->reviewPrevented = true;
    }

    public function isReviewPrevented(): bool
    {
        return $this->reviewPrevented;
    }
}
