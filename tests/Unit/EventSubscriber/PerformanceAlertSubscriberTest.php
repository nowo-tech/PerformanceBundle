<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\EventSubscriber\PerformanceAlertSubscriber;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use Nowo\PerformanceBundle\Service\NotificationService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class PerformanceAlertSubscriberTest extends TestCase
{
    private NotificationService&MockObject $notificationService;
    private PerformanceAlertSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->notificationService = $this->createMock(NotificationService::class);
        
        $this->subscriber = new PerformanceAlertSubscriber(
            $this->notificationService,
            0.5,  // requestTimeWarning
            1.0,  // requestTimeCritical
            20,   // queryCountWarning
            50,   // queryCountCritical
            20.0, // memoryUsageWarning
            50.0, // memoryUsageCritical
            true  // enabled
        );
    }

    public function testSendsCriticalAlertForRequestTime(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setRequestTime(1.5); // Above critical threshold (1.0)

        $this->notificationService->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->notificationService->expects($this->once())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $alert) {
                    return $alert->getType() === PerformanceAlert::TYPE_REQUEST_TIME &&
                           $alert->isCritical() &&
                           str_contains($alert->getMessage(), 'Critical');
                }),
                $routeData
            )
            ->willReturn(['email' => true]);

        $event = new AfterMetricsRecordedEvent($routeData, false);
        $this->subscriber->onAfterMetricsRecorded($event);
    }

    public function testSendsWarningAlertForRequestTime(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setRequestTime(0.6); // Above warning threshold (0.5) but below critical

        $this->notificationService->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->notificationService->expects($this->once())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $alert) {
                    return $alert->getType() === PerformanceAlert::TYPE_REQUEST_TIME &&
                           $alert->isWarning() &&
                           str_contains($alert->getMessage(), 'Warning');
                }),
                $routeData
            )
            ->willReturn(['email' => true]);

        $event = new AfterMetricsRecordedEvent($routeData, false);
        $this->subscriber->onAfterMetricsRecorded($event);
    }

    public function testSendsCriticalAlertForQueryCount(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setTotalQueries(60); // Above critical threshold (50)

        $this->notificationService->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->notificationService->expects($this->once())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $alert) {
                    return $alert->getType() === PerformanceAlert::TYPE_QUERY_COUNT &&
                           $alert->isCritical();
                }),
                $routeData
            )
            ->willReturn(['email' => true]);

        $event = new AfterMetricsRecordedEvent($routeData, false);
        $this->subscriber->onAfterMetricsRecorded($event);
    }

    public function testSendsWarningAlertForQueryCount(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setTotalQueries(25); // Above warning threshold (20) but below critical

        $this->notificationService->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->notificationService->expects($this->once())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $alert) {
                    return $alert->getType() === PerformanceAlert::TYPE_QUERY_COUNT &&
                           $alert->isWarning();
                }),
                $routeData
            )
            ->willReturn(['email' => true]);

        $event = new AfterMetricsRecordedEvent($routeData, false);
        $this->subscriber->onAfterMetricsRecorded($event);
    }

    public function testSendsCriticalAlertForMemoryUsage(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setMemoryUsage(60 * 1024 * 1024); // 60 MB, above critical threshold (50 MB)

        $this->notificationService->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->notificationService->expects($this->once())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $alert) {
                    return $alert->getType() === PerformanceAlert::TYPE_MEMORY_USAGE &&
                           $alert->isCritical();
                }),
                $routeData
            )
            ->willReturn(['email' => true]);

        $event = new AfterMetricsRecordedEvent($routeData, false);
        $this->subscriber->onAfterMetricsRecorded($event);
    }

    public function testSendsWarningAlertForMemoryUsage(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setMemoryUsage(25 * 1024 * 1024); // 25 MB, above warning threshold (20 MB) but below critical

        $this->notificationService->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->notificationService->expects($this->once())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $alert) {
                    return $alert->getType() === PerformanceAlert::TYPE_MEMORY_USAGE &&
                           $alert->isWarning();
                }),
                $routeData
            )
            ->willReturn(['email' => true]);

        $event = new AfterMetricsRecordedEvent($routeData, false);
        $this->subscriber->onAfterMetricsRecorded($event);
    }

    public function testDoesNotSendAlertWhenDisabled(): void
    {
        $subscriber = new PerformanceAlertSubscriber(
            $this->notificationService,
            0.5,
            1.0,
            20,
            50,
            20.0,
            50.0,
            false // disabled
        );

        $routeData = new RouteData();
        $routeData->setRequestTime(1.5);

        $this->notificationService->expects($this->never())
            ->method('sendAlert');

        $event = new AfterMetricsRecordedEvent($routeData, false);
        $subscriber->onAfterMetricsRecorded($event);
    }

    public function testDoesNotSendAlertWhenNotificationServiceDisabled(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(1.5);

        $this->notificationService->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);

        $this->notificationService->expects($this->never())
            ->method('sendAlert');

        $event = new AfterMetricsRecordedEvent($routeData, false);
        $this->subscriber->onAfterMetricsRecorded($event);
    }

    public function testDoesNotSendAlertWhenBelowThresholds(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.3); // Below warning threshold
        $routeData->setTotalQueries(10); // Below warning threshold
        $routeData->setMemoryUsage(10 * 1024 * 1024); // 10 MB, below warning threshold

        $this->notificationService->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->notificationService->expects($this->never())
            ->method('sendAlert');

        $event = new AfterMetricsRecordedEvent($routeData, false);
        $this->subscriber->onAfterMetricsRecorded($event);
    }

    public function testSendsMultipleAlertsForMultipleThresholds(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setRequestTime(1.5); // Critical
        $routeData->setTotalQueries(60); // Critical
        $routeData->setMemoryUsage(60 * 1024 * 1024); // Critical

        $this->notificationService->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->notificationService->expects($this->exactly(3))
            ->method('sendAlert')
            ->willReturn(['email' => true]);

        $event = new AfterMetricsRecordedEvent($routeData, false);
        $this->subscriber->onAfterMetricsRecorded($event);
    }
}
