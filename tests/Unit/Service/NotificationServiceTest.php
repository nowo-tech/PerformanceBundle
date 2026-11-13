<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Model\RouteMetricsSnapshot;
use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use Nowo\PerformanceBundle\Notification\RouteMetricsNotificationChannelInterface;
use Nowo\PerformanceBundle\Service\NotificationService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NotificationServiceTest extends TestCase
{
    public function testSendAlertWhenDisabled(): void
    {
        $service = new NotificationService([], false);

        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_WARNING,
            'Test alert',
        );

        $routeData = new RouteData();

        $results = $service->sendAlert($alert, $routeData);

        $this->assertEmpty($results);
    }

    public function testSendAlertWithEnabledChannels(): void
    {
        $channel1 = $this->createMock(NotificationChannelInterface::class);
        $channel1->method('isEnabled')->willReturn(true);
        $channel1->method('getName')->willReturn('channel1');
        $channel1->method('send')->willReturn(true);

        $channel2 = $this->createMock(NotificationChannelInterface::class);
        $channel2->method('isEnabled')->willReturn(true);
        $channel2->method('getName')->willReturn('channel2');
        $channel2->method('send')->willReturn(false);

        $service = new NotificationService([$channel1, $channel2], true);

        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_WARNING,
            'Test alert',
        );

        $routeData = new RouteData();

        $results = $service->sendAlert($alert, $routeData);

        $this->assertArrayHasKey('channel1', $results);
        $this->assertArrayHasKey('channel2', $results);
        $this->assertTrue($results['channel1']);
        $this->assertFalse($results['channel2']);
    }

    public function testSendAlertSkipsDisabledChannels(): void
    {
        $enabledChannel = $this->createMock(NotificationChannelInterface::class);
        $enabledChannel->method('isEnabled')->willReturn(true);
        $enabledChannel->method('getName')->willReturn('enabled');
        $enabledChannel->method('send')->willReturn(true);

        $disabledChannel = $this->createMock(NotificationChannelInterface::class);
        $disabledChannel->method('isEnabled')->willReturn(false);
        $disabledChannel->method('getName')->willReturn('disabled');

        $service = new NotificationService([$enabledChannel, $disabledChannel], true);

        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_WARNING,
            'Test alert',
        );

        $routeData = new RouteData();

        $results = $service->sendAlert($alert, $routeData);

        $this->assertArrayHasKey('enabled', $results);
        $this->assertArrayNotHasKey('disabled', $results);
    }

    public function testSendAlertHandlesExceptions(): void
    {
        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->method('isEnabled')->willReturn(true);
        $channel->method('getName')->willReturn('channel');
        $channel->method('send')->willThrowException(new RuntimeException('Channel error'));

        $service = new NotificationService([$channel], true);

        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_WARNING,
            'Test alert',
        );

        $routeData = new RouteData();

        $results = $service->sendAlert($alert, $routeData);

        $this->assertArrayHasKey('channel', $results);
        $this->assertFalse($results['channel']);
    }

    public function testIsEnabled(): void
    {
        $enabledService  = new NotificationService([], true);
        $disabledService = new NotificationService([], false);

        $this->assertTrue($enabledService->isEnabled());
        $this->assertFalse($disabledService->isEnabled());
    }

    public function testGetEnabledChannels(): void
    {
        $channel1 = $this->createMock(NotificationChannelInterface::class);
        $channel1->method('isEnabled')->willReturn(true);
        $channel1->method('getName')->willReturn('channel1');

        $channel2 = $this->createMock(NotificationChannelInterface::class);
        $channel2->method('isEnabled')->willReturn(false);
        $channel2->method('getName')->willReturn('channel2');

        $channel3 = $this->createMock(NotificationChannelInterface::class);
        $channel3->method('isEnabled')->willReturn(true);
        $channel3->method('getName')->willReturn('channel3');

        $service = new NotificationService([$channel1, $channel2, $channel3], true);

        $enabled = $service->getEnabledChannels();

        $this->assertContains('channel1', $enabled);
        $this->assertContains('channel3', $enabled);
        $this->assertNotContains('channel2', $enabled);
        $this->assertCount(2, $enabled);
    }

    public function testGetEnabledChannelsReturnsEmptyWhenNoChannels(): void
    {
        $service = new NotificationService([], true);

        $this->assertSame([], $service->getEnabledChannels());
    }

    public function testGetEnabledChannelsWithSingleChannel(): void
    {
        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->method('isEnabled')->willReturn(true);
        $channel->method('getName')->willReturn('email');

        $service = new NotificationService([$channel], true);

        $this->assertSame(['email'], $service->getEnabledChannels());
    }

    public function testSendAlertWithAfterMetricsRecordedEventContext(): void
    {
        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->method('isEnabled')->willReturn(true);
        $channel->method('getName')->willReturn('webhook');
        $channel->expects($this->once())
            ->method('send')
            ->with(
                $this->isInstanceOf(PerformanceAlert::class),
                $this->isInstanceOf(AfterMetricsRecordedEvent::class),
            )
            ->willReturn(true);

        $service = new NotificationService([$channel], true);
        $alert   = new PerformanceAlert(
            PerformanceAlert::TYPE_QUERY_COUNT,
            PerformanceAlert::SEVERITY_CRITICAL,
            'High query count',
        );
        $routeData = new RouteData();
        $routeData->setName('api_slow')->setEnv('prod');
        $event = new AfterMetricsRecordedEvent($routeData, true, 0.5, 50);

        $results = $service->sendAlert($alert, $event);

        $this->assertArrayHasKey('webhook', $results);
        $this->assertTrue($results['webhook']);
    }

    public function testSnapshotChannelGetsSnapshotAndLegacyChannelGetsTheSameContextAsBefore(): void
    {
        $route = (new RouteData())->setName('app_home')->setEnv('prod');
        $event = new AfterMetricsRecordedEvent($route, false, 1.5, 40, 1024);
        $alert = new PerformanceAlert(PerformanceAlert::TYPE_REQUEST_TIME, PerformanceAlert::SEVERITY_CRITICAL, 'Slow');

        $legacy = $this->createMock(NotificationChannelInterface::class);
        $legacy->method('isEnabled')->willReturn(true);
        $legacy->method('getName')->willReturn('legacy');
        $legacy->expects(self::once())->method('send')->with($alert, self::identicalTo($event))->willReturn(true);

        $modern = new class implements RouteMetricsNotificationChannelInterface {
            /** @var list<RouteMetricsSnapshot> */
            public array $received = [];

            public function sendRouteMetrics(PerformanceAlert $alert, RouteMetricsSnapshot $route): bool
            {
                $this->received[] = $route;

                return true;
            }

            public function send(PerformanceAlert $alert, RouteData|AfterMetricsRecordedEvent $context): bool
            {
                throw new RuntimeException('send() must not be called for a snapshot channel');
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function getName(): string
            {
                return 'modern';
            }
        };

        $results = (new NotificationService([$legacy, $modern], true))->sendAlert($alert, $event);

        self::assertSame(['legacy' => true, 'modern' => true], $results);
        $received = $modern->received;
        self::assertCount(1, $received);
        self::assertEquals(RouteMetricsSnapshot::fromRecordedEvent($event), $received[0]);
        self::assertSame('app_home', $received[0]->routeName);
        self::assertSame(1.5, $received[0]->requestTime);
    }
}
