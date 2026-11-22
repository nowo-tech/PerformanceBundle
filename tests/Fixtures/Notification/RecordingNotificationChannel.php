<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Fixtures\Notification;

use Nowo\PerformanceBundle\Model\RouteMetricsSnapshot;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use Nowo\PerformanceBundle\Notification\RouteMetricsNotificationChannelInterface;

/**
 * Host channel written for the 4.0 port only (no send()): records what it receives.
 */
final class RecordingNotificationChannel implements RouteMetricsNotificationChannelInterface
{
    /** @var list<array{alert: PerformanceAlert, route: RouteMetricsSnapshot}> */
    public array $received = [];

    public function sendRouteMetrics(PerformanceAlert $alert, RouteMetricsSnapshot $route): bool
    {
        // @igor-ignore - test fixture
        $this->received[] = ['alert' => $alert, 'route' => $route];

        return true;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'recording';
    }
}
