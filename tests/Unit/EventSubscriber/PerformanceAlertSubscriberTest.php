<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\EventSubscriber\PerformanceAlertSubscriber;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use Nowo\PerformanceBundle\Service\NotificationService;
use PHPUnit\Framework\TestCase;

final class PerformanceAlertSubscriberTest extends TestCase
{
    public function testOnAfterMetricsRecordedDoesNothingWhenDisabled(): void
    {
        $notification = $this->createMock(NotificationService::class);
        $notification->expects($this->never())->method('sendAlert');

        $subscriber = new PerformanceAlertSubscriber($notification, 0.5, 1.0, 20, 50, 20.0, 50.0, false);
        $route = new RouteData();
        $route->setName('app_home')->setEnv('dev');
        $event = new AfterMetricsRecordedEvent($route, true, 2.0, 100, 100 * 1024 * 1024);

        $subscriber->onAfterMetricsRecorded($event);
    }

    public function testOnAfterMetricsRecordedDoesNothingWhenNotificationServiceNull(): void
    {
        $subscriber = new PerformanceAlertSubscriber(null, 0.5, 1.0, 20, 50, 20.0, 50.0, true);
        $route = new RouteData();
        $route->setName('app_home')->setEnv('dev');
        $event = new AfterMetricsRecordedEvent($route, true, 2.0, 100, 100 * 1024 * 1024);

        $subscriber->onAfterMetricsRecorded($event);

        $this->addToAssertionCount(1);
    }

    public function testOnAfterMetricsRecordedDoesNothingWhenNotificationServiceNotEnabled(): void
    {
        $notification = $this->createMock(NotificationService::class);
        $notification->method('isEnabled')->willReturn(false);
        $notification->expects($this->never())->method('sendAlert');

        $subscriber = new PerformanceAlertSubscriber($notification, 0.5, 1.0, 20, 50, 20.0, 50.0, true);
        $route = new RouteData();
        $route->setName('app_home')->setEnv('dev');
        $event = new AfterMetricsRecordedEvent($route, true, 2.0, 100, 100 * 1024 * 1024);

        $subscriber->onAfterMetricsRecorded($event);
    }

    public function testOnAfterMetricsRecordedSendsAlertWhenRequestTimeCritical(): void
    {
        $notification = $this->createMock(NotificationService::class);
        $notification->method('isEnabled')->willReturn(true);
        $notification->expects($this->atLeastOnce())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $a): bool {
                    return $a->getType() === PerformanceAlert::TYPE_REQUEST_TIME
                        && $a->getSeverity() === PerformanceAlert::SEVERITY_CRITICAL
                        && $a->isCritical();
                }),
                $this->isInstanceOf(AfterMetricsRecordedEvent::class)
            );

        $subscriber = new PerformanceAlertSubscriber($notification, 0.5, 1.0, 20, 50, 20.0, 50.0, true);
        $route = new RouteData();
        $route->setName('api_slow')->setEnv('prod');
        $event = new AfterMetricsRecordedEvent($route, true, 1.5, null, null);

        $subscriber->onAfterMetricsRecorded($event);
    }

    public function testOnAfterMetricsRecordedSendsAlertWhenRequestTimeWarning(): void
    {
        $notification = $this->createMock(NotificationService::class);
        $notification->method('isEnabled')->willReturn(true);
        $notification->expects($this->atLeastOnce())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $a): bool {
                    return $a->getType() === PerformanceAlert::TYPE_REQUEST_TIME
                        && $a->getSeverity() === PerformanceAlert::SEVERITY_WARNING
                        && $a->isWarning();
                }),
                $this->isInstanceOf(AfterMetricsRecordedEvent::class)
            );

        $subscriber = new PerformanceAlertSubscriber($notification, 0.5, 1.0, 20, 50, 20.0, 50.0, true);
        $route = new RouteData();
        $route->setName('api_warn')->setEnv('dev');
        $event = new AfterMetricsRecordedEvent($route, true, 0.6, null, null);

        $subscriber->onAfterMetricsRecorded($event);
    }

    public function testOnAfterMetricsRecordedSendsAlertWhenQueryCountCritical(): void
    {
        $notification = $this->createMock(NotificationService::class);
        $notification->method('isEnabled')->willReturn(true);
        $notification->expects($this->atLeastOnce())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $a): bool {
                    return $a->getType() === PerformanceAlert::TYPE_QUERY_COUNT
                        && $a->getSeverity() === PerformanceAlert::SEVERITY_CRITICAL;
                }),
                $this->anything()
            );

        $subscriber = new PerformanceAlertSubscriber($notification, 0.5, 1.0, 20, 50, 20.0, 50.0, true);
        $route = new RouteData();
        $route->setName('api_n1')->setEnv('prod');
        $event = new AfterMetricsRecordedEvent($route, true, null, 60, null);

        $subscriber->onAfterMetricsRecorded($event);
    }

    public function testOnAfterMetricsRecordedSendsAlertWhenQueryCountWarning(): void
    {
        $notification = $this->createMock(NotificationService::class);
        $notification->method('isEnabled')->willReturn(true);
        $notification->expects($this->atLeastOnce())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $a): bool {
                    return $a->getType() === PerformanceAlert::TYPE_QUERY_COUNT
                        && $a->getSeverity() === PerformanceAlert::SEVERITY_WARNING;
                }),
                $this->anything()
            );

        $subscriber = new PerformanceAlertSubscriber($notification, 0.5, 1.0, 20, 50, 20.0, 50.0, true);
        $route = new RouteData();
        $route->setName('api_queries')->setEnv('dev');
        $event = new AfterMetricsRecordedEvent($route, true, null, 25, null);

        $subscriber->onAfterMetricsRecorded($event);
    }

    public function testOnAfterMetricsRecordedSendsAlertWhenMemoryUsageCritical(): void
    {
        $notification = $this->createMock(NotificationService::class);
        $notification->method('isEnabled')->willReturn(true);
        $notification->expects($this->atLeastOnce())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $a): bool {
                    return $a->getType() === PerformanceAlert::TYPE_MEMORY_USAGE
                        && $a->getSeverity() === PerformanceAlert::SEVERITY_CRITICAL;
                }),
                $this->anything()
            );

        $subscriber = new PerformanceAlertSubscriber($notification, 0.5, 1.0, 20, 50, 20.0, 50.0, true);
        $route = new RouteData();
        $route->setName('api_mem')->setEnv('prod');
        $event = new AfterMetricsRecordedEvent($route, true, null, null, 60 * 1024 * 1024);

        $subscriber->onAfterMetricsRecorded($event);
    }

    public function testOnAfterMetricsRecordedSendsAlertWhenMemoryUsageWarning(): void
    {
        $notification = $this->createMock(NotificationService::class);
        $notification->method('isEnabled')->willReturn(true);
        $notification->expects($this->atLeastOnce())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $a): bool {
                    return $a->getType() === PerformanceAlert::TYPE_MEMORY_USAGE
                        && $a->getSeverity() === PerformanceAlert::SEVERITY_WARNING;
                }),
                $this->anything()
            );

        $subscriber = new PerformanceAlertSubscriber($notification, 0.5, 1.0, 20, 50, 20.0, 50.0, true);
        $route = new RouteData();
        $route->setName('api_mem')->setEnv('dev');
        $event = new AfterMetricsRecordedEvent($route, true, null, null, 25 * 1024 * 1024);

        $subscriber->onAfterMetricsRecorded($event);
    }

    public function testOnAfterMetricsRecordedNoAlertWhenBelowThresholds(): void
    {
        $notification = $this->createMock(NotificationService::class);
        $notification->method('isEnabled')->willReturn(true);
        $notification->expects($this->never())->method('sendAlert');

        $subscriber = new PerformanceAlertSubscriber($notification, 0.5, 1.0, 20, 50, 20.0, 50.0, true);
        $route = new RouteData();
        $route->setName('app_fast')->setEnv('dev');
        $event = new AfterMetricsRecordedEvent($route, true, 0.1, 5, 10 * 1024 * 1024);

        $subscriber->onAfterMetricsRecorded($event);
    }
}
