<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\EventSubscriber;

use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use Nowo\PerformanceBundle\Service\NotificationService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Event subscriber for sending performance alerts.
 *
 * Listens to AfterMetricsRecordedEvent and sends notifications when thresholds are exceeded.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
final class PerformanceAlertSubscriber
{
    /**
     * Constructor.
     *
     * @param NotificationService $notificationService Notification service
     * @param float               $requestTimeWarning  Warning threshold for request time
     * @param float               $requestTimeCritical Critical threshold for request time
     * @param int                 $queryCountWarning   Warning threshold for query count
     * @param int                 $queryCountCritical  Critical threshold for query count
     * @param float               $memoryUsageWarning  Warning threshold for memory usage (MB)
     * @param float               $memoryUsageCritical Critical threshold for memory usage (MB)
     * @param bool                $enabled             Whether alerts are enabled
     */
    public function __construct(
        private readonly ?NotificationService $notificationService,
        private readonly float $requestTimeWarning = 0.5,
        private readonly float $requestTimeCritical = 1.0,
        private readonly int $queryCountWarning = 20,
        private readonly int $queryCountCritical = 50,
        private readonly float $memoryUsageWarning = 20.0,
        private readonly float $memoryUsageCritical = 50.0,
        private readonly bool $enabled = false,
    ) {
    }

    /**
     * Handle AfterMetricsRecordedEvent.
     *
     * @param AfterMetricsRecordedEvent $event The event
     */
    #[AsEventListener]
    public function onAfterMetricsRecorded(AfterMetricsRecordedEvent $event): void
    {
        if (!$this->enabled || null === $this->notificationService || !$this->notificationService->isEnabled()) {
            return;
        }

        $routeData = $event->getRouteData();

        // Check request time
        if (null !== $routeData->getRequestTime()) {
            if ($routeData->getRequestTime() >= $this->requestTimeCritical) {
                $alert = new PerformanceAlert(
                    PerformanceAlert::TYPE_REQUEST_TIME,
                    PerformanceAlert::SEVERITY_CRITICAL,
                    \sprintf(
                        'Critical: Route "%s" has request time of %.4fs (threshold: %.2fs)',
                        $routeData->getName() ?? 'Unknown',
                        $routeData->getRequestTime(),
                        $this->requestTimeCritical
                    ),
                    [
                        'value' => $routeData->getRequestTime(),
                        'threshold' => $this->requestTimeCritical,
                    ]
                );
                $this->notificationService->sendAlert($alert, $routeData);
            } elseif ($routeData->getRequestTime() >= $this->requestTimeWarning) {
                $alert = new PerformanceAlert(
                    PerformanceAlert::TYPE_REQUEST_TIME,
                    PerformanceAlert::SEVERITY_WARNING,
                    \sprintf(
                        'Warning: Route "%s" has request time of %.4fs (threshold: %.2fs)',
                        $routeData->getName() ?? 'Unknown',
                        $routeData->getRequestTime(),
                        $this->requestTimeWarning
                    ),
                    [
                        'value' => $routeData->getRequestTime(),
                        'threshold' => $this->requestTimeWarning,
                    ]
                );
                $this->notificationService->sendAlert($alert, $routeData);
            }
        }

        // Check query count
        if (null !== $routeData->getTotalQueries()) {
            if ($routeData->getTotalQueries() >= $this->queryCountCritical) {
                $alert = new PerformanceAlert(
                    PerformanceAlert::TYPE_QUERY_COUNT,
                    PerformanceAlert::SEVERITY_CRITICAL,
                    \sprintf(
                        'Critical: Route "%s" has %d queries (threshold: %d)',
                        $routeData->getName() ?? 'Unknown',
                        $routeData->getTotalQueries(),
                        $this->queryCountCritical
                    ),
                    [
                        'value' => $routeData->getTotalQueries(),
                        'threshold' => $this->queryCountCritical,
                    ]
                );
                $this->notificationService->sendAlert($alert, $routeData);
            } elseif ($routeData->getTotalQueries() >= $this->queryCountWarning) {
                $alert = new PerformanceAlert(
                    PerformanceAlert::TYPE_QUERY_COUNT,
                    PerformanceAlert::SEVERITY_WARNING,
                    \sprintf(
                        'Warning: Route "%s" has %d queries (threshold: %d)',
                        $routeData->getName() ?? 'Unknown',
                        $routeData->getTotalQueries(),
                        $this->queryCountWarning
                    ),
                    [
                        'value' => $routeData->getTotalQueries(),
                        'threshold' => $this->queryCountWarning,
                    ]
                );
                $this->notificationService->sendAlert($alert, $routeData);
            }
        }

        // Check memory usage
        if (null !== $routeData->getMemoryUsage()) {
            $memoryMB = $routeData->getMemoryUsage() / 1024 / 1024;
            if ($memoryMB >= $this->memoryUsageCritical) {
                $alert = new PerformanceAlert(
                    PerformanceAlert::TYPE_MEMORY_USAGE,
                    PerformanceAlert::SEVERITY_CRITICAL,
                    \sprintf(
                        'Critical: Route "%s" uses %.2f MB of memory (threshold: %.2f MB)',
                        $routeData->getName() ?? 'Unknown',
                        $memoryMB,
                        $this->memoryUsageCritical
                    ),
                    [
                        'value' => $memoryMB,
                        'threshold' => $this->memoryUsageCritical,
                    ]
                );
                $this->notificationService->sendAlert($alert, $routeData);
            } elseif ($memoryMB >= $this->memoryUsageWarning) {
                $alert = new PerformanceAlert(
                    PerformanceAlert::TYPE_MEMORY_USAGE,
                    PerformanceAlert::SEVERITY_WARNING,
                    \sprintf(
                        'Warning: Route "%s" uses %.2f MB of memory (threshold: %.2f MB)',
                        $routeData->getName() ?? 'Unknown',
                        $memoryMB,
                        $this->memoryUsageWarning
                    ),
                    [
                        'value' => $memoryMB,
                        'threshold' => $this->memoryUsageWarning,
                    ]
                );
                $this->notificationService->sendAlert($alert, $routeData);
            }
        }
    }
}
