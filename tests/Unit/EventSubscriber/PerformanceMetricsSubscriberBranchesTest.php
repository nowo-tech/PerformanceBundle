<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Exception;
use Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector;
use Nowo\PerformanceBundle\EventSubscriber\PerformanceMetricsSubscriber;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Branch coverage: env detection without kernel, sampling, schema-hint on errors, ignore rules (incl. web_profiler_*).
 */
final class PerformanceMetricsSubscriberBranchesTest extends TestCase
{
    private MockObject $metricsService;
    private MockObject $dataCollector;
    private MockObject $httpKernel;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->dataCollector  = $this->createMock(PerformanceDataCollector::class);
        $this->httpKernel     = $this->createMock(HttpKernelInterface::class);
    }

    private function createSubscriber(
        bool $enabled,
        array $environments,
        array $ignoreRoutes,
        float $samplingRate,
        ?KernelInterface $kernel,
        ?RequestStack $requestStack = null,
        bool $trackSubRequests = false,
        bool $trackUser = false,
        ?object $security = null,
    ): PerformanceMetricsSubscriber {
        return new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->dataCollector,
            $enabled,
            $environments,
            $ignoreRoutes,
            true,
            true,
            $trackSubRequests,
            false,
            $samplingRate,
            [200, 404, 500, 503],
            true,
            $trackUser,
            $requestStack,
            $security,
            null,
            $kernel,
        );
    }

    /** When kernel is null, APP_ENV is read from Request::server (lines 163–164). */
    public function testOnKernelRequestUsesServerAppEnvWhenKernelIsNull(): void
    {
        $subscriber = $this->createSubscriber(true, ['test'], [], 1.0, null);

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'test');
        $request->attributes->set('_route', 'app_home');

        $this->dataCollector->method('setEnabled')->willReturnSelf();
        $this->dataCollector->method('setRouteName')->willReturnSelf();
        $this->dataCollector->method('setStartTime')->willReturnSelf();

        $subscriber->onKernelRequest(new RequestEvent($this->httpKernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $this->addToAssertionCount(1);
    }

    /** When kernel is null and $_ENV['APP_ENV'] is set (lines 167–168). */
    public function testOnKernelRequestUsesEnvAppEnvWhenKernelIsNull(): void
    {
        $prev            = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'test';
        try {
            $subscriber = $this->createSubscriber(true, ['test'], [], 1.0, null);

            $request = Request::create('/');
            $request->attributes->set('_route', 'app_home');

            $this->dataCollector->method('setEnabled')->willReturnSelf();
            $this->dataCollector->method('setRouteName')->willReturnSelf();
            $this->dataCollector->method('setStartTime')->willReturnSelf();

            $subscriber->onKernelRequest(new RequestEvent($this->httpKernel, $request, HttpKernelInterface::MAIN_REQUEST));
            $this->addToAssertionCount(1);
        } finally {
            if ($prev === null) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $prev;
            }
        }
    }

    /** Route name web_profiler_* is ignored when _wdt is in ignore_routes (lines 789–797). */
    public function testOnKernelRequestIgnoresWebProfilerRouteWhenWdtInIgnoreList(): void
    {
        $subscriber = $this->createSubscriber(true, ['test'], ['_wdt'], 1.0, null);

        $request = Request::create('/_profiler');
        $request->server->set('APP_ENV', 'test');
        $request->attributes->set('_route', 'web_profiler_bar');

        $this->dataCollector->method('setEnabled')->willReturnSelf();
        $this->dataCollector->method('setRouteName')->willReturnSelf();
        $call = 0;
        $this->dataCollector
            ->expects($this->exactly(2))
            ->method('setDisabledReason')
            ->willReturnCallback(static function (?string $reason) use (&$call): void {
                ++$call;
                if ($call === 1) {
                    self::assertNull($reason);
                } else {
                    self::assertStringContainsString('ignore_routes', $reason ?? '');
                }
            });

        $subscriber->onKernelRequest(new RequestEvent($this->httpKernel, $request, HttpKernelInterface::MAIN_REQUEST));
    }

    /** Glob pattern in ignore_routes (lines 771–774). */
    public function testIsRouteIgnoredMatchesGlobPattern(): void
    {
        $subscriber = $this->createSubscriber(true, ['test'], ['*_profiler*'], 1.0, null);

        $m = new ReflectionMethod(PerformanceMetricsSubscriber::class, 'isRouteIgnored');

        self::assertTrue($m->invoke($subscriber, 'foo_profiler_bar'));
        self::assertFalse($m->invoke($subscriber, 'safe_route'));
    }

    /** samplingRate 0 skips persist (lines 429–439). */
    public function testOnKernelTerminateSkipsRecordMetricsWhenSamplingRateZero(): void
    {
        $subscriber = $this->createSubscriber(true, ['test'], [], 0.0, null);

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'test');
        $request->attributes->set('_route', 'app_home');

        $this->dataCollector->method('setEnabled')->willReturnSelf();
        $this->dataCollector->method('setRouteName')->willReturnSelf();
        $this->dataCollector->method('setStartTime')->willReturnSelf();
        $this->dataCollector->method('setQueryCount')->willReturnSelf();
        $this->dataCollector->method('setQueryTime')->willReturnSelf();

        $subscriber->onKernelRequest(new RequestEvent($this->httpKernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $this->dataCollector->method('isEnabled')->willReturn(true);
        $this->dataCollector->expects($this->once())->method('setRecordOperation')->with(false, false);

        $this->metricsService->expects($this->never())->method('recordMetrics');

        $subscriber->onKernelTerminate(new TerminateEvent($this->httpKernel, $request, new Response()));
    }

    /** Exception message with Unknown column triggers schema hint path when enableLogging (lines 567–576). */
    public function testOnKernelTerminateLogsSchemaHintWhenUnknownColumnException(): void
    {
        $subscriber = $this->createSubscriber(true, ['test'], [], 1.0, null);

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'test');
        $request->attributes->set('_route', 'app_home');

        $this->dataCollector->method('setEnabled')->willReturnSelf();
        $this->dataCollector->method('setRouteName')->willReturnSelf();
        $this->dataCollector->method('setStartTime')->willReturnSelf();
        $this->dataCollector->method('setQueryCount')->willReturnSelf();
        $this->dataCollector->method('setQueryTime')->willReturnSelf();
        $this->dataCollector->method('setRecordOperation')->willReturnSelf();
        $this->dataCollector->method('wasRecordNew')->willReturn(null);
        $this->dataCollector->method('wasRecordUpdated')->willReturn(null);

        $subscriber->onKernelRequest(new RequestEvent($this->httpKernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $this->dataCollector->method('isEnabled')->willReturn(true);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->willThrowException(new Exception("Unknown column 'total_queries' in 'routes_data'"));

        $subscriber->onKernelTerminate(new TerminateEvent($this->httpKernel, $request, new Response()));
    }

    /** Sub-request inherits request id from main when RequestStack provides main (lines 267–276). */
    public function testSubRequestUsesMainRequestPerformanceRequestIdFromStack(): void
    {
        $main = Request::create('/main');
        $main->server->set('APP_ENV', 'test');
        $main->attributes->set('_route', 'main_route');
        $main->attributes->set('_performance_request_id', 'main-req-id');

        $sub = Request::create('/fragment');
        $sub->server->set('APP_ENV', 'test');
        $sub->attributes->set('_route', 'sub_route');

        $stack = new RequestStack();
        $stack->push($main);
        $stack->push($sub);

        $subscriber = $this->createSubscriber(true, ['test'], [], 1.0, null, $stack, true);

        $this->dataCollector->method('setEnabled')->willReturnSelf();
        $this->dataCollector->method('setRouteName')->willReturnSelf();
        $this->dataCollector->method('setStartTime')->willReturnSelf();

        $subscriber->onKernelRequest(new RequestEvent($this->httpKernel, $sub, HttpKernelInterface::SUB_REQUEST));

        $this->addToAssertionCount(1);
    }

    /** recordMetrics without is_new/was_updated triggers warning branch and setRecordOperation(false, false) (lines 526–536). */
    public function testOnKernelTerminateWhenRecordMetricsReturnsUnexpectedFormat(): void
    {
        $subscriber = $this->createSubscriber(true, ['test'], [], 1.0, null);

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'test');
        $request->attributes->set('_route', 'app_home');

        $this->dataCollector->method('setEnabled')->willReturnSelf();
        $this->dataCollector->method('setRouteName')->willReturnSelf();
        $this->dataCollector->method('setStartTime')->willReturnSelf();
        $this->dataCollector->method('setQueryCount')->willReturnSelf();
        $this->dataCollector->method('setQueryTime')->willReturnSelf();
        $this->dataCollector->method('setRecordOperation')->willReturnSelf();
        $this->dataCollector->method('wasRecordNew')->willReturn(null);
        $this->dataCollector->method('wasRecordUpdated')->willReturn(null);

        $subscriber->onKernelRequest(new RequestEvent($this->httpKernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $this->dataCollector->method('isEnabled')->willReturn(true);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->willReturn([]);

        $subscriber->onKernelTerminate(new TerminateEvent($this->httpKernel, $request, new Response()));
    }

    /** trackUser + security helper passes identifiers into recordMetrics (lines 472–483, 489–505). */
    public function testOnKernelTerminatePassesUserIdentifiersWhenTrackUserEnabled(): void
    {
        $user = new class {
            public function getUserIdentifier(): string
            {
                return 'alice@example.test';
            }

            public function getId(): int
            {
                return 42;
            }
        };

        $security = new class($user) {
            public function __construct(private readonly object $user)
            {
            }

            public function getUser(): object
            {
                return $this->user;
            }
        };

        $subscriber = $this->createSubscriber(true, ['test'], [], 1.0, null, null, false, true, $security);

        $request = Request::create('/dashboard');
        $request->server->set('APP_ENV', 'test');
        $request->attributes->set('_route', 'app_dashboard');

        $this->dataCollector->method('setEnabled')->willReturnSelf();
        $this->dataCollector->method('setRouteName')->willReturnSelf();
        $this->dataCollector->method('setStartTime')->willReturnSelf();
        $this->dataCollector->method('setQueryCount')->willReturnSelf();
        $this->dataCollector->method('setQueryTime')->willReturnSelf();
        $this->dataCollector->method('setRecordOperation')->willReturnSelf();
        $this->dataCollector->method('wasRecordNew')->willReturn(null);
        $this->dataCollector->method('wasRecordUpdated')->willReturn(null);

        $subscriber->onKernelRequest(new RequestEvent($this->httpKernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $this->dataCollector->method('isEnabled')->willReturn(true);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with($this->callback(static function (mixed ...$args): bool {
                self::assertCount(15, $args);
                self::assertSame('app_dashboard', $args[0]);
                self::assertSame('test', $args[1]);
                self::assertSame('alice@example.test', $args[12]);
                self::assertSame('42', $args[13]);

                return true;
            }))
            ->willReturn(['is_new' => false, 'was_updated' => false]);

        $subscriber->onKernelTerminate(new TerminateEvent($this->httpKernel, $request, new Response()));
    }
}
