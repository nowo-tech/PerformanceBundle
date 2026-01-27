<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Helper\LogHelper;
use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;

/**
 * Service for sending performance notifications.
 *
 * Manages multiple notification channels and dispatches alerts.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class NotificationService
{
    /**
     * Constructor.
     *
     * @param iterable<NotificationChannelInterface> $channels Notification channels
     * @param bool                                   $enabled  Whether notifications are enabled
     */
    public function __construct(
        private readonly iterable $channels = [],
        private readonly bool $enabled = false,
    ) {
    }

    /**
     * Send a performance alert to all enabled channels.
     *
     * @param PerformanceAlert $alert     The alert to send
     * @param RouteData        $routeData The route data that triggered the alert
     *
     * @return array<string, bool> Results for each channel (channel name => success)
     */
    public function sendAlert(PerformanceAlert $alert, RouteData $routeData): array
    {
        if (!$this->enabled) {
            return [];
        }

        $results = [];

        foreach ($this->channels as $channel) {
            if ($channel->isEnabled()) {
                try {
                    $results[$channel->getName()] = $channel->send($alert, $routeData);
                } catch (\Exception $e) {
                    // Log error but don't throw (logging enabled by default for backward compatibility)
                    LogHelper::logf(
                        'Failed to send notification via channel %s: %s',
                        null,
                        $channel->getName(),
                        $e->getMessage()
                    );
                    $results[$channel->getName()] = false;
                }
            }
        }

        return $results;
    }

    /**
     * Check if notifications are enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get enabled channels.
     *
     * @return array<string> Array of enabled channel names
     */
    public function getEnabledChannels(): array
    {
        $enabled = [];
        foreach ($this->channels as $channel) {
            if ($channel->isEnabled()) {
                $enabled[] = $channel->getName();
            }
        }

        return $enabled;
    }
}
