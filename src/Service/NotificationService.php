<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

use Exception;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function sprintf;

/**
 * Service for sending performance notifications.
 *
 * Manages multiple notification channels and dispatches alerts.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class NotificationService
{
    /**
     * Creates a new instance.
     *
     * @param iterable<NotificationChannelInterface> $channels Notification channels
     * @param bool $enabled Whether notifications are enabled
     */
    public function __construct(
        private readonly iterable $channels = [],
        #[Autowire('%nowo_performance.notifications.enabled%')]
        private readonly bool $enabled = false,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Send a performance alert to all enabled channels.
     *
     * @param PerformanceAlert $alert The alert to send
     * @param AfterMetricsRecordedEvent|RouteData $context The route data or event (event carries just-recorded metrics)
     *
     * @return array<string, bool> Results for each channel (channel name => success)
     */
    public function sendAlert(PerformanceAlert $alert, RouteData|AfterMetricsRecordedEvent $context): array
    {
        if (!$this->enabled) {
            return [];
        }

        $results = [];

        foreach ($this->channels as $channel) {
            if ($channel->isEnabled()) {
                try {
                    $results[$channel->getName()] = $channel->send($alert, $context);
                } catch (Exception $e) {
                    // Log error but don't throw (logging enabled by default for backward compatibility)
                    $this->bundleLog(
                        'warning',
                        'Failed to send notification via channel %s: %s',
                        $channel->getName(),
                        $e->getMessage(),
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

    /**
     * Logs through the injected PSR-3 logger (REQ-OBS-001 / REQ-DI-001).
     *
     * @param string $level PSR-3 level
     * @param string $format sprintf() format (used verbatim when no arguments are given)
     * @param mixed ...$args sprintf() arguments
     */
    private function bundleLog(string $level, string $format, mixed ...$args): void
    {
        $this->logger?->log($level, $args === [] ? $format : sprintf($format, ...$args), ['bundle' => 'performance']);
    }
}
