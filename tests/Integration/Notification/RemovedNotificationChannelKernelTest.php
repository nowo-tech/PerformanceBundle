<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Notification;

use LogicException;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Model\RouteMetricsSnapshot;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Nowo\PerformanceBundle\Tests\Fixtures\Notification\RecordingNotificationChannel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

use function sprintf;

/**
 * 4.0 channel port through the real NowoPerformanceBundle::build() (REQ-ARCH-001 rules 1 and 3, wave-1 lesson W1-1):
 * a channel that implements only RouteMetricsNotificationChannelInterface receives alerts end to end, and a channel
 * still written for the removed NotificationChannelInterface fails the container build instead of being dropped
 * silently (its alerts would simply stop).
 */
final class RemovedNotificationChannelKernelTest extends TestCase
{
    private ?NotificationChannelKernel $kernel = null;

    protected function tearDown(): void
    {
        if ($this->kernel instanceof NotificationChannelKernel) {
            $cacheDir = $this->kernel->getCacheDir();
            $this->kernel->shutdown();
            (new Filesystem())->remove($cacheDir);
        }
        $this->kernel = null;
    }

    public function testChannelOnTheNewPortOnlyReceivesAlertsEndToEnd(): void
    {
        $container = $this->boot('recording')->getContainer();

        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        (new CommandTester($app->find('nowo:performance:create-table')))->execute([]);
        (new CommandTester($app->find('nowo:performance:create-records-table')))->execute([]);

        $metrics = $container->get(PerformanceMetricsService::class);
        self::assertInstanceOf(PerformanceMetricsService::class, $metrics);
        // 5 s and 200 queries: above the default critical thresholds (1 s, 50 queries).
        $metrics->recordMetrics('e2e_alert_route', 'test', 5.0, 200, 0.5, null, 1024, 'GET');

        $channel = $container->get(RecordingNotificationChannel::class);
        self::assertInstanceOf(RecordingNotificationChannel::class, $channel);
        self::assertNotSame([], $channel->received, 'the channel received no alert');

        $types = [];
        foreach ($channel->received as ['alert' => $alert, 'route' => $route]) {
            self::assertInstanceOf(PerformanceAlert::class, $alert);
            self::assertInstanceOf(RouteMetricsSnapshot::class, $route);
            self::assertSame('e2e_alert_route', $route->routeName);
            self::assertSame('test', $route->env);
            self::assertSame('GET', $route->httpMethod);
            self::assertNotNull($route->routeDataId);
            self::assertSame(5.0, $route->requestTime);
            self::assertSame(200, $route->totalQueries);
            self::assertTrue($route->isNew);
            $types[] = $alert->getType();
        }
        self::assertContains(PerformanceAlert::TYPE_REQUEST_TIME, $types);
        self::assertContains(PerformanceAlert::TYPE_QUERY_COUNT, $types);
    }

    public function testDispatchedEventReachesTheChannelAsASnapshot(): void
    {
        $container = $this->boot('recording')->getContainer();

        $routeData = new RouteData();
        $routeData->setName('dispatched_route');
        $routeData->setEnv('test');

        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $dispatcher->dispatch(new AfterMetricsRecordedEvent($routeData, false, 2.0, 1, 10));

        $channel = $container->get(RecordingNotificationChannel::class);
        self::assertInstanceOf(RecordingNotificationChannel::class, $channel);
        self::assertCount(1, $channel->received);
        self::assertSame('dispatched_route', $channel->received[0]['route']->routeName);
        self::assertFalse($channel->received[0]['route']->isNew);
        self::assertSame(PerformanceAlert::SEVERITY_CRITICAL, $channel->received[0]['alert']->getSeverity());
    }

    public function testAutodiscoveredChannelOnTheRemovedInterfaceFailsTheBuild(): void
    {
        $e = $this->bootFailure('autodiscovered');

        self::assertStringContainsString('LegacyAutodiscoveredChannel', $e->getMessage());
        self::assertStringContainsString('"Nowo\PerformanceBundle\Notification\NotificationChannelInterface"', $e->getMessage());
        self::assertStringContainsString('deprecated since 3.19.0', $e->getMessage());
        self::assertStringContainsString('RouteMetricsNotificationChannelInterface', $e->getMessage());
        self::assertStringContainsString('UPGRADING.md "To 4.0.0"', $e->getMessage());
    }

    public function testTaggedChannelOnTheRemovedInterfaceFailsTheBuild(): void
    {
        $e = $this->bootFailure('tagged');

        self::assertStringContainsString('The notification channel', $e->getMessage());
        self::assertStringContainsString('LegacyTaggedChannel', $e->getMessage());
        self::assertStringContainsString('"Nowo\PerformanceBundle\Notification\NotificationChannelInterface"', $e->getMessage());
        self::assertStringContainsString('sendRouteMetrics(PerformanceAlert, RouteMetricsSnapshot)', $e->getMessage());
    }

    public function testTaggedServiceWithoutTheChannelPortFailsTheBuild(): void
    {
        $e = $this->bootFailure('not_a_channel');

        self::assertStringContainsString('NotAChannel', $e->getMessage());
        self::assertStringContainsString('does not implement "Nowo\PerformanceBundle\Notification\RouteMetricsNotificationChannelInterface"', $e->getMessage());
    }

    public function testChannelTaggedOnlyByAttributeOnTheRemovedInterfaceFailsTheBuild(): void
    {
        $e = $this->bootFailure('autoconfigure_tag');

        self::assertStringContainsString('LegacyAttributeTaggedChannel', $e->getMessage());
        self::assertStringContainsString('"Nowo\PerformanceBundle\Notification\NotificationChannelInterface"', $e->getMessage());
        self::assertStringContainsString('UPGRADING.md "To 4.0.0"', $e->getMessage());
    }

    public function testExplicitOverrideOfAnAutodiscoveredLegacyChannelFailsTheBuild(): void
    {
        $e = $this->bootFailure('resource_override');

        self::assertStringContainsString('LegacyOverriddenChannel', $e->getMessage());
        self::assertStringContainsString('"Nowo\PerformanceBundle\Notification\NotificationChannelInterface"', $e->getMessage());
        self::assertStringContainsString('UPGRADING.md "To 4.0.0"', $e->getMessage());
    }

    public function testLegacyDecoratorOfABuiltInChannelFailsWithTheHint(): void
    {
        $e = $this->bootFailure('legacy_decorator');

        self::assertStringContainsString('The service "app.slack_decorator" (decorator of the notification channel "nowo_performance.notification.channel.slack")', $e->getMessage());
        self::assertStringContainsString('UPGRADING.md "To 4.0.0"', $e->getMessage());
    }

    public function testUnrelatedMissingInterfaceStillCompiles(): void
    {
        $container = $this->boot('unrelated')->getContainer();

        self::assertTrue($container->has(PerformanceMetricsService::class));
    }

    private function boot(string $scenario): NotificationChannelKernel
    {
        $this->kernel = new NotificationChannelKernel($scenario);
        (new Filesystem())->remove($this->kernel->getCacheDir());
        $this->kernel->boot();

        return $this->kernel;
    }

    private function bootFailure(string $scenario): LogicException
    {
        try {
            $this->boot($scenario);
        } catch (LogicException $e) {
            return $e;
        } catch (Throwable $e) {
            self::fail(sprintf('Expected a LogicException, got %s: %s', $e::class, $e->getMessage()));
        }

        self::fail('The container build did not fail.');
    }
}
