<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Notification;

use Nowo\PerformanceBundle\Model\RouteMetricsSnapshot;
use Nowo\PerformanceBundle\Service\NotificationService;

/**
 * Notification channel (SPI): receives a framework-free {@see RouteMetricsSnapshot}, never the bundle entity
 * (since 3.17.0, the only channel port since 4.0; REQ-ARCH-001 rules 1 and 3).
 *
 * Tag the service `nowo_performance.notification_channel`. {@see NotificationService} calls isEnabled() and then
 * sendRouteMetrics() for every alert. The 3.x `NotificationChannelInterface` (with `send(PerformanceAlert,
 * RouteData|AfterMetricsRecordedEvent)`) was removed in 4.0: a tagged service that still implements it fails the
 * container build.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface RouteMetricsNotificationChannelInterface
{
    /**
     * Sends the alert for the route described by $route.
     *
     * @return bool True if the notification was sent
     */
    public function sendRouteMetrics(PerformanceAlert $alert, RouteMetricsSnapshot $route): bool;

    /**
     * Whether this channel is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Channel name (e.g. 'email', 'slack', 'teams').
     */
    public function getName(): string;
}
