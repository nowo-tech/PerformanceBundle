<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Model;

use DateTimeImmutable;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;

/**
 * Immutable, framework-free copy of a `routes_data` row (plus the metrics just recorded, when known).
 *
 * Carried by the bundle events and the notification SPI next to the entity (REQ-ARCH-001 rules 1, 3 and 4): listeners
 * and channels of a DDD host read ids and values from it and never touch the bundle's Doctrine entity. The entity
 * accessors (`getRouteData()`, the `RouteData` form of `NotificationChannelInterface::send()`) stay for standard
 * hosts and are removed in 4.0.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class RouteMetricsSnapshot
{
    /**
     * @param int|null $routeDataId routes_data id (null only for a row never flushed)
     * @param string|null $routeName Route name
     * @param string|null $env Environment (dev, prod, …)
     * @param string|null $httpMethod HTTP method of the route
     * @param bool $reviewed Whether the route was reviewed
     * @param DateTimeImmutable|null $reviewedAt Review time
     * @param string|null $reviewedBy Reviewer name
     * @param bool|null $queriesImproved Review verdict on queries
     * @param bool|null $timeImproved Review verdict on time
     * @param DateTimeImmutable|null $lastAccessedAt Last access time
     * @param bool|null $isNew Recording created the row (only from AfterMetricsRecordedEvent)
     * @param float|null $requestTime Request time just recorded, seconds (only from AfterMetricsRecordedEvent)
     * @param int|null $totalQueries Query count just recorded (only from AfterMetricsRecordedEvent)
     * @param int|null $memoryUsage Memory just recorded, bytes (only from AfterMetricsRecordedEvent)
     */
    public function __construct(
        public ?int $routeDataId,
        public ?string $routeName,
        public ?string $env,
        public ?string $httpMethod = null,
        public bool $reviewed = false,
        public ?DateTimeImmutable $reviewedAt = null,
        public ?string $reviewedBy = null,
        public ?bool $queriesImproved = null,
        public ?bool $timeImproved = null,
        public ?DateTimeImmutable $lastAccessedAt = null,
        public ?bool $isNew = null,
        public ?float $requestTime = null,
        public ?int $totalQueries = null,
        public ?int $memoryUsage = null,
    ) {
    }

    /**
     * Copies the current state of the entity (later changes to the entity are not reflected).
     */
    public static function fromRouteData(RouteData $routeData): self
    {
        return new self(
            routeDataId: $routeData->getId(),
            routeName: $routeData->getName(),
            env: $routeData->getEnv(),
            httpMethod: $routeData->getHttpMethod(),
            reviewed: $routeData->isReviewed(),
            reviewedAt: $routeData->getReviewedAt(),
            reviewedBy: $routeData->getReviewedBy(),
            queriesImproved: $routeData->getQueriesImproved(),
            timeImproved: $routeData->getTimeImproved(),
            lastAccessedAt: $routeData->getLastAccessedAt(),
        );
    }

    /**
     * The route state plus the metrics an {@see AfterMetricsRecordedEvent} carries.
     */
    public static function fromRecordedEvent(AfterMetricsRecordedEvent $event): self
    {
        $route = self::fromRouteData($event->getRouteData());

        return new self(
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
            isNew: $event->isNew(),
            requestTime: $event->getRequestTime(),
            totalQueries: $event->getTotalQueries(),
            memoryUsage: $event->getMemoryUsage(),
        );
    }

    /**
     * Snapshot of a notification context (the two forms {@see NotificationChannelInterface::send()} receives).
     */
    public static function fromContext(RouteData|AfterMetricsRecordedEvent $context): self
    {
        return $context instanceof AfterMetricsRecordedEvent ? self::fromRecordedEvent($context) : self::fromRouteData($context);
    }
}
