<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Notification;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Model\RouteMetricsSnapshot;

/**
 * Interface for notification channels.
 *
 * Implement this interface to create custom notification channels. Since 3.17.0, prefer
 * {@see RouteMetricsNotificationChannelInterface}: it receives a framework-free
 * {@see RouteMetricsSnapshot} instead of the bundle entity. The entity form of send()
 * is replaced by it in 4.0.
 *
 * @deprecated since nowo-tech/performance-bundle 3.19.0, implement {@see RouteMetricsNotificationChannelInterface}
 *             (`sendRouteMetrics(PerformanceAlert, RouteMetricsSnapshot)`) instead. In 4.0 the entity / event form of
 *             send() is removed and this interface is replaced by it. A container build with a host channel that only
 *             implements this interface triggers a deprecation notice.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface NotificationChannelInterface
{
    /**
     * Send a performance alert notification.
     *
     * @param PerformanceAlert $alert The performance alert
     * @param AfterMetricsRecordedEvent|RouteData $context The route data or event (event carries just-recorded metrics)
     *
     * @return bool True if notification was sent successfully, false otherwise
     */
    public function send(PerformanceAlert $alert, RouteData|AfterMetricsRecordedEvent $context): bool;

    /**
     * Check if this channel is enabled.
     *
     * @return bool True if enabled, false otherwise
     */
    public function isEnabled(): bool;

    /**
     * Get the channel name.
     *
     * @return string The channel name (e.g., 'email', 'slack', 'teams')
     */
    public function getName(): string;
}
