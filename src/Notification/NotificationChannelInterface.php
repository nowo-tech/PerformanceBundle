<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Notification;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;

/**
 * Interface for notification channels.
 *
 * Implement this interface to create custom notification channels.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface NotificationChannelInterface
{
    /**
     * Send a performance alert notification.
     *
     * @param PerformanceAlert                $alert     The performance alert
     * @param RouteData|AfterMetricsRecordedEvent $context   The route data or event (event carries just-recorded metrics)
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
