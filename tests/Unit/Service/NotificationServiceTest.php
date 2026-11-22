<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Model\RouteMetricsSnapshot;
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
        $channel1 = $this->createMock(RouteMetricsNotificationChannelInterface::class);
        $channel1->method('isEnabled')->willReturn(true);
        $channel1->method('getName')->willReturn('channel1');
        $channel1->method('sendRouteMetrics')->willReturn(true);

        $channel2 = $this->createMock(RouteMetricsNotificationChannelInterface::class);
        $channel2->method('isEnabled')->willReturn(true);
        $channel2->method('getName')->willReturn('channel2');
        $channel2->method('sendRouteMetrics')->willReturn(false);

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
        $enabledChannel = $this->createMock(RouteMetricsNotificationChannelInterface::class);
        $enabledChannel->method('isEnabled')->willReturn(true);
        $enabledChannel->method('getName')->willReturn('enabled');
        $enabledChannel->method('sendRouteMetrics')->willReturn(true);

        $disabledChannel = $this->createMock(RouteMetricsNotificationChannelInterface::class);
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
        $channel = $this->createMock(RouteMetricsNotificationChannelInterface::class);
        $channel->method('isEnabled')->willReturn(true);
        $channel->method('getName')->willReturn('channel');
        $channel->method('sendRouteMetrics')->willThrowException(new RuntimeException('Channel error'));

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
        $channel1 = $this->createMock(RouteMetricsNotificationChannelInterface::class);
        $channel1->method('isEnabled')->willReturn(true);
        $channel1->method('getName')->willReturn('channel1');

        $channel2 = $this->createMock(RouteMetricsNotificationChannelInterface::class);
        $channel2->method('isEnabled')->willReturn(false);
        $channel2->method('getName')->willReturn('channel2');

        $channel3 = $this->createMock(RouteMetricsNotificationChannelInterface::class);
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
        $channel = $this->createMock(RouteMetricsNotificationChannelInterface::class);
        $channel->method('isEnabled')->willReturn(true);
        $channel->method('getName')->willReturn('email');

        $service = new NotificationService([$channel], true);

        $this->assertSame(['email'], $service->getEnabledChannels());
    }

    public function testSendAlertWithAfterMetricsRecordedEventContext(): void
    {
        $channel = $this->createMock(RouteMetricsNotificationChannelInterface::class);
        $channel->method('isEnabled')->willReturn(true);
        $channel->method('getName')->willReturn('webhook');
        $channel->expects($this->once())
            ->method('sendRouteMetrics')
            ->with(
                $this->isInstanceOf(PerformanceAlert::class),
                $this->callback(static fn (RouteMetricsSnapshot $route): bool => $route->routeName === 'api_slow' && $route->requestTime === 0.5 && $route->totalQueries === 50 && $route->isNew === true),
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

    public function testEveryChannelGetsTheSameSnapshotAndNeverTheEntity(): void
    {
        $route = (new RouteData())->setName('app_home')->setEnv('prod');
        $event = new AfterMetricsRecordedEvent($route, false, 1.5, 40, 1024);
        $alert = new PerformanceAlert(PerformanceAlert::TYPE_REQUEST_TIME, PerformanceAlert::SEVERITY_CRITICAL, 'Slow');

        $channel = static fn (string $name): RouteMetricsNotificationChannelInterface => new class($name) implements RouteMetricsNotificationChannelInterface {
            /** @var list<RouteMetricsSnapshot> */
            public array $received = [];

            public function __construct(private readonly string $name)
            {
            }

            public function sendRouteMetrics(PerformanceAlert $alert, RouteMetricsSnapshot $route): bool
            {
                $this->received[] = $route;

                return true;
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function getName(): string
            {
                return $this->name;
            }
        };
        $first  = $channel('first');
        $second = $channel('second');

        $results = (new NotificationService([$first, $second], true))->sendAlert($alert, $event);

        self::assertSame(['first' => true, 'second' => true], $results);
        self::assertCount(1, $first->received);
        self::assertSame($first->received[0], $second->received[0], 'one snapshot per alert, shared by the channels');
        self::assertEquals(RouteMetricsSnapshot::fromRecordedEvent($event), $first->received[0]);
        self::assertSame('app_home', $first->received[0]->routeName);
        self::assertSame(1.5, $first->received[0]->requestTime);
    }
}
