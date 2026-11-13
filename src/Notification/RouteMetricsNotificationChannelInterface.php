<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Notification;

use Nowo\PerformanceBundle\Model\RouteMetricsSnapshot;
use Nowo\PerformanceBundle\Service\NotificationService;

/**
 * Notification channel that receives a framework-free {@see RouteMetricsSnapshot} instead of the bundle entity
 * (since 3.17.0, REQ-ARCH-001 rules 1 and 3).
 *
 * When a channel implements this interface, {@see NotificationService} calls
 * sendRouteMetrics() and never send(). The inherited send() is still required by the parent interface until 4.0;
 * delegate it:
 *
 *     public function send(PerformanceAlert $alert, RouteData|AfterMetricsRecordedEvent $context): bool
 *     {
 *         return $this->sendRouteMetrics($alert, RouteMetricsSnapshot::fromContext($context));
 *     }
 *
 * In 4.0 this method replaces the entity form of send().
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface RouteMetricsNotificationChannelInterface extends NotificationChannelInterface
{
    /**
     * Sends the alert for the route described by $route.
     *
     * @return bool True if the notification was sent
     */
    public function sendRouteMetrics(PerformanceAlert $alert, RouteMetricsSnapshot $route): bool;
}
