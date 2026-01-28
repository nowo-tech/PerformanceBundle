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

/**
 * Advanced edge-case tests for PerformanceAlertSubscriber thresholds.
 */
final class PerformanceAlertSubscriberAdvancedTest extends TestCase
{
    private NotificationService&MockObject $notificationService;

    protected function setUp(): void
    {
        $this->notificationService = $this->createMock(NotificationService::class);
    }

    public function testRequestTimeExactlyAtWarningThresholdSendsWarning(): void
    {
        $subscriber = new PerformanceAlertSubscriber(
            $this->notificationService,
            0.5,  // requestTimeWarning
            1.0,  // requestTimeCritical
            20,
            50,
            20.0,
            50.0,
            true
        );

        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setRequestTime(0.5); // exactly warning threshold

        $this->notificationService->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->notificationService->expects($this->once())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $alert) {
                    return $alert->getType() === PerformanceAlert::TYPE_REQUEST_TIME
                        && $alert->isWarning()
                        && str_contains($alert->getMessage(), 'Warning');
                }),
                $routeData
            )
            ->willReturn(['email' => true]);

        $event = new AfterMetricsRecordedEvent($routeData, false);
        $subscriber->onAfterMetricsRecorded($event);
    }

    public function testRequestTimeExactlyAtCriticalThresholdSendsCritical(): void
    {
        $subscriber = new PerformanceAlertSubscriber(
            $this->notificationService,
            0.5,
            1.0,
            20,
            50,
            20.0,
            50.0,
            true
        );

        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setRequestTime(1.0); // exactly critical threshold

        $this->notificationService->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->notificationService->expects($this->once())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $alert) {
                    return $alert->getType() === PerformanceAlert::TYPE_REQUEST_TIME
                        && $alert->isCritical()
                        && str_contains($alert->getMessage(), 'Critical');
                }),
                $routeData
            )
            ->willReturn(['email' => true]);

        $event = new AfterMetricsRecordedEvent($routeData, false);
        $subscriber->onAfterMetricsRecorded($event);
    }

    public function testQueryCountExactlyAtWarningThresholdSendsWarning(): void
    {
        $subscriber = new PerformanceAlertSubscriber(
            $this->notificationService,
            0.5,
            1.0,
            20,
            50,
            20.0,
            50.0,
            true
        );

        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setTotalQueries(20); // exactly warning threshold

        $this->notificationService->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->notificationService->expects($this->once())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $alert) {
                    return $alert->getType() === PerformanceAlert::TYPE_QUERY_COUNT
                        && $alert->isWarning();
                }),
                $routeData
            )
            ->willReturn(['email' => true]);

        $event = new AfterMetricsRecordedEvent($routeData, false);
        $subscriber->onAfterMetricsRecorded($event);
    }

    public function testQueryCountExactlyAtCriticalThresholdSendsCritical(): void
    {
        $subscriber = new PerformanceAlertSubscriber(
            $this->notificationService,
            0.5,
            1.0,
            20,
            50,
            20.0,
            50.0,
            true
        );

        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setTotalQueries(50); // exactly critical threshold

        $this->notificationService->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->notificationService->expects($this->once())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $alert) {
                    return $alert->getType() === PerformanceAlert::TYPE_QUERY_COUNT
                        && $alert->isCritical();
                }),
                $routeData
            )
            ->willReturn(['email' => true]);

        $event = new AfterMetricsRecordedEvent($routeData, false);
        $subscriber->onAfterMetricsRecorded($event);
    }

    public function testMemoryUsageExactlyAtWarningThresholdSendsWarning(): void
    {
        $subscriber = new PerformanceAlertSubscriber(
            $this->notificationService,
            0.5,
            1.0,
            20,
            50,
            20.0,
            50.0,
            true
        );

        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setMemoryUsage(20 * 1024 * 1024); // exactly 20 MB warning

        $this->notificationService->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->notificationService->expects($this->once())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $alert) {
                    return $alert->getType() === PerformanceAlert::TYPE_MEMORY_USAGE
                        && $alert->isWarning();
                }),
                $routeData
            )
            ->willReturn(['email' => true]);

        $event = new AfterMetricsRecordedEvent($routeData, false);
        $subscriber->onAfterMetricsRecorded($event);
    }

    public function testMemoryUsageExactlyAtCriticalThresholdSendsCritical(): void
    {
        $subscriber = new PerformanceAlertSubscriber(
            $this->notificationService,
            0.5,
            1.0,
            20,
            50,
            20.0,
            50.0,
            true
        );

        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setMemoryUsage(50 * 1024 * 1024); // exactly 50 MB critical

        $this->notificationService->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->notificationService->expects($this->once())
            ->method('sendAlert')
            ->with(
                $this->callback(function (PerformanceAlert $alert) {
                    return $alert->getType() === PerformanceAlert::TYPE_MEMORY_USAGE
                        && $alert->isCritical();
                }),
                $routeData
            )
            ->willReturn(['email' => true]);

        $event = new AfterMetricsRecordedEvent($routeData, false);
        $subscriber->onAfterMetricsRecorded($event);
    }

    public function testDoesNothingWhenNotificationServiceIsNull(): void
    {
        $subscriber = new PerformanceAlertSubscriber(
            null,
            0.5,
            1.0,
            20,
            50,
            20.0,
            50.0,
            true
        );

        $routeData = new RouteData();
        $routeData->setRequestTime(2.0);
        $routeData->setTotalQueries(100);
        $routeData->setMemoryUsage(100 * 1024 * 1024);

        // Original mock should never be used
        $this->notificationService->expects($this->never())
            ->method('sendAlert');

        $event = new AfterMetricsRecordedEvent($routeData, false);
        $subscriber->onAfterMetricsRecorded($event);

        $this->addToAssertionCount(1); // Ensure test is not marked as risky
    }
}

