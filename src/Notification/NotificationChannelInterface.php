<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Notification;

use Nowo\PerformanceBundle\Entity\RouteData;

/**
 * Interface for notification channels.
 *
 * Implement this interface to create custom notification channels.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
interface NotificationChannelInterface
{
    /**
     * Send a performance alert notification.
     *
     * @param PerformanceAlert $alert The performance alert
     * @param RouteData $routeData The route data that triggered the alert
     * @return bool True if notification was sent successfully, false otherwise
     */
    public function send(PerformanceAlert $alert, RouteData $routeData): bool;

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
