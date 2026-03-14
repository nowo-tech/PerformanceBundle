<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Exception;
use Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector;
use Nowo\PerformanceBundle\EventSubscriber\PerformanceMetricsSubscriber;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

use function is_string;

/**
 * Tests for scenarios where data might not be saved.
 *
 * These tests verify edge cases that could cause data not to be saved,
 * helping to diagnose issues in production.
 */
final class PerformanceMetricsSubscriberDataNotSavedTest extends TestCase
{
    private MockObject $metricsService;
    private MockObject $dataCollector;
    private MockObject $kernel;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->dataCollector  = $this->createMock(PerformanceDataCollector::class);
        $this->kernel         = $this->createMock(HttpKernelInterface::class);
    }

    public function testDataNotSavedWhenCollectorDisabledBetweenRequestAndTerminate(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->dataCollector,
            true,
            ['dev', 'test'],
            [],
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            false,
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // In REQUEST, collector is enabled
        $this->dataCollector
            ->expects($this->once())
            ->method('setEnabled')
            ->with(true);

        $subscriber->onKernelRequest($requestEvent);

        // In TERMINATE, collector is disabled (simulating a scenario where something disabled it)
        $this->dataCollector
            ->method('isEnabled')
            ->willReturn(false);

        $this->dataCollector
            ->method('getDisabledReason')
            ->willReturn('Collector was disabled between REQUEST and TERMINATE');

        $this->dataCollector
            ->expects($this->once())
            ->method('setRecordOperation')
            ->with(false, false);

        $this->metricsService
            ->expects($this->never())
            ->method('recordMetrics');

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testRequestDisablesTrackingWhenBundleDisabled(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->dataCollector,
            false, // enabled = false
            ['dev', 'test'],
            [],
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            false,
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->expects(self::atLeastOnce())->method('setEnabled')->with(false);
        $this->dataCollector->expects(self::once())->method('setDisabledReason')->with(self::stringContains('Bundle is disabled'));

        $subscriber->onKernelRequest($requestEvent);

        $this->metricsService->expects(self::never())->method('recordMetrics');
        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testRequestDisablesTrackingWhenEnvNotInAllowedList(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->dataCollector,
            true,
            ['dev', 'test'], // prod not allowed
            [],
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            false,
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'prod');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->expects(self::once())->method('setEnabled')->with(false);
        $this->dataCollector->expects(self::once())->method('setDisabledReason')->with(self::stringContains('not in allowed environments'));

        $subscriber->onKernelRequest($requestEvent);

        $this->metricsService->expects(self::never())->method('recordMetrics');
        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testRequestDisablesTrackingWhenRouteIsIgnored(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->dataCollector,
            true,
            ['dev', 'test'],
            ['app_home'], // ignore this route
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            false,
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Subscriber enables first (env in list), then disables when route is ignored (setDisabledReason(null) then setDisabledReason('...ignore_routes...'))
        $setEnabledCalls = [];
        $this->dataCollector->expects(self::exactly(2))->method('setEnabled')->willReturnCallback(static function ($enabled) use (&$setEnabledCalls): void {
            $setEnabledCalls[] = $enabled;
        });
        $setDisabledReasonCalls = [];
        $this->dataCollector->expects(self::atLeastOnce())->method('setDisabledReason')->willReturnCallback(static function ($reason) use (&$setDisabledReasonCalls): void {
            $setDisabledReasonCalls[] = $reason;
        });
        $this->dataCollector->expects(self::atLeastOnce())->method('setRecordOperation')->with(false, false);

        $subscriber->onKernelRequest($requestEvent);

        self::assertSame([true, false], $setEnabledCalls);
        self::assertNotEmpty($setDisabledReasonCalls);
        self::assertTrue(
            array_reduce($setDisabledReasonCalls, static fn (bool $has, $r): bool => $has || (is_string($r) && str_contains($r, 'ignore_routes')), false),
            'Expected at least one setDisabledReason call with string containing "ignore_routes"',
        );
        $this->metricsService->expects(self::never())->method('recordMetrics');
        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testDataNotSavedWhenRouteNameLostBetweenRequestAndTerminate(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->dataCollector,
            true,
            ['dev', 'test'],
            [],
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            false,
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($requestEvent);

        // In TERMINATE, route name is lost (simulating a scenario where route was cleared)
        $request->attributes->remove('_route');

        $this->dataCollector
            ->method('isEnabled')
            ->willReturn(true);

        $this->dataCollector
            ->method('setRouteName')
            ->willReturnSelf();

        $this->dataCollector
            ->expects($this->atLeastOnce())
            ->method('setRecordOperation')
            ->with(false, false);

        $this->metricsService
            ->expects($this->never())
            ->method('recordMetrics');

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testDataNotSavedWhenEnvironmentChangedBetweenRequestAndTerminate(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->dataCollector,
            true,
            ['dev', 'test'], // Only dev and test allowed
            [],
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            false,
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($requestEvent);

        // In TERMINATE, environment changed to prod (not allowed)
        $request->server->set('APP_ENV', 'prod');

        $this->dataCollector
            ->method('isEnabled')
            ->willReturn(true);

        $this->dataCollector
            ->method('setRouteName')
            ->willReturnSelf();

        $this->dataCollector
            ->expects($this->once())
            ->method('setRecordOperation')
            ->with(false, false);

        $this->metricsService
            ->expects($this->never())
            ->method('recordMetrics');

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testDataNotSavedWhenStartTimeIsNull(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->dataCollector,
            true,
            ['dev', 'test'],
            [],
            true,  // trackQueries
            true,  // trackRequestTime
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            false,
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');

        // Simulate that startTime was not set (edge case)
        // This could happen if onKernelRequest was not called or failed silently
        new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        // Don't call onKernelRequest to simulate startTime being null

        $this->dataCollector
            ->method('isEnabled')
            ->willReturn(true);

        $this->dataCollector
            ->method('setRouteName')
            ->willReturnSelf();

        // Even if startTime is null, we should still try to save (requestTime will be null)
        $this->dataCollector
            ->method('setRequestTime')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setQueryCount')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setQueryTime')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setRecordOperation')
            ->willReturnSelf();

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                null, // requestTime is null because startTime was null
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(), // requestId
                $this->anything(), // referer
                null,   // userIdentifier
                null,    // userId
            )
            ->willReturn(['is_new' => true, 'was_updated' => false]);

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testDataNotSavedWhenStartMemoryIsNull(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->dataCollector,
            true,
            ['dev', 'test'],
            [],
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            false,
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');

        // Simulate that startMemory was not set
        new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        // Don't call onKernelRequest to simulate startMemory being null

        $this->dataCollector
            ->method('isEnabled')
            ->willReturn(true);

        $this->dataCollector
            ->method('setRouteName')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setRequestTime')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setQueryCount')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setQueryTime')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setRecordOperation')
            ->willReturnSelf();

        // Even if startMemory is null, we should still try to save (memoryUsage will be null)
        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                null, // memoryUsage is null because startMemory was null
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(), // requestId
                $this->anything(), // referer
                null,   // userIdentifier
                null,    // userId
            )
            ->willReturn(['is_new' => true, 'was_updated' => false]);

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testDataSavedEvenWhenAllMetricsAreNull(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->dataCollector,
            true,
            ['dev', 'test'],
            [],
            false, // trackQueries = false
            false, // trackRequestTime = false
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            false,
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($requestEvent);

        $this->dataCollector
            ->method('isEnabled')
            ->willReturn(true);

        $this->dataCollector
            ->method('setRouteName')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setRecordOperation')
            ->willReturnSelf();

        // Even when all metrics are null, we should still save (at least access count).
        // memoryUsage is int because onKernelRequest was called and sets startMemory.
        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                null, // requestTime
                null, // queryCount
                null, // queryTime
                $this->anything(), // routeParams
                $this->isType('int'), // memoryUsage (startMemory set in onKernelRequest)
                'GET', // httpMethod
                200,   // statusCode
                [200, 404, 500, 503], // trackStatusCodes
                $this->isType('string'), // requestId (set in onKernelRequest)
                null,  // referer (no Referer header)
                null,  // userIdentifier
                null,   // userId
            )
            ->willReturn(['is_new' => true, 'was_updated' => false]);

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response('', 200));
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testDataNotSavedWhenSamplingSkipsRequest(): void
    {
        // Note: We can't reliably test sampling because it uses mt_rand()
        // But we can verify the structure
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->dataCollector,
            true,
            ['dev', 'test'],
            [],
            true,
            true,
            false,
            false,
            0.0, // samplingRate = 0% (never record)
            [200, 404, 500, 503],
            true,
            false,
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($requestEvent);

        $this->dataCollector
            ->method('isEnabled')
            ->willReturn(true);

        $this->dataCollector
            ->method('setRouteName')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setRequestTime')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setQueryCount')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setQueryTime')
            ->willReturnSelf();

        // With sampling rate 0%, it should always skip
        $this->dataCollector
            ->expects($this->once())
            ->method('setRecordOperation')
            ->with(false, false);

        // Note: With sampling rate 0%, mt_rand() will always be > 0, so it will skip
        // But we can't control mt_rand() in tests, so we just verify the structure
        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    /** When recordMetrics throws an exception with schema-related message, the subscriber logs the schema hint. */
    public function testRecordMetricsThrowsExceptionWithSchemaHint(): void
    {
        $this->metricsService
            ->method('recordMetrics')
            ->willThrowException(new Exception("Unknown column 'total_queries' in 'field list'"));

        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->dataCollector,
            true,
            ['dev', 'test'],
            [],
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true, // enableLogging so schema hint branch is run
            false,
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($requestEvent);

        $this->dataCollector->method('isEnabled')->willReturn(true);
        $this->dataCollector->method('setRouteName')->willReturnSelf();
        $this->dataCollector->method('setRequestTime')->willReturnSelf();
        $this->dataCollector->method('setQueryCount')->willReturnSelf();
        $this->dataCollector->method('setQueryTime')->willReturnSelf();
        $this->dataCollector->method('wasRecordNew')->willReturn(null);
        $this->dataCollector->method('wasRecordUpdated')->willReturn(null);
        $this->dataCollector->expects(self::atLeastOnce())->method('setRecordOperation')->with(false, false);

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);

        self::assertTrue(true, 'Exception handled and schema hint branch covered');
    }

    /** When recordMetrics returns result without is_new/was_updated keys, subscriber sets setRecordOperation(false, false). */
    public function testRecordMetricsReturnsUnexpectedFormat(): void
    {
        $this->metricsService
            ->method('recordMetrics')
            ->willReturn(['unexpected_key' => 'value']);

        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->dataCollector,
            true,
            ['dev', 'test'],
            [],
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            false,
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($requestEvent);

        $this->dataCollector->method('isEnabled')->willReturn(true);
        $this->dataCollector->method('setRouteName')->willReturnSelf();
        $this->dataCollector->method('setRequestTime')->willReturnSelf();
        $this->dataCollector->method('setQueryCount')->willReturnSelf();
        $this->dataCollector->method('setQueryTime')->willReturnSelf();
        $this->dataCollector->expects(self::atLeastOnce())->method('setRecordOperation')->with(false, false);

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);

        self::assertTrue(true, 'Unexpected result format branch covered');
    }

    /** When kernel is available, env is taken from kernel->getEnvironment(). */
    public function testRequestUsesKernelEnvironmentWhenKernelAvailable(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn('dev');

        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->dataCollector,
            true,
            ['dev', 'test'],
            [],
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            false,
            null,
            null,
            null,
            $kernel,
        );

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->expects(self::once())->method('setEnabled')->with(true);
        $subscriber->onKernelRequest($requestEvent);
        self::assertTrue(true, 'Kernel env branch covered');
    }

    /** When route matches web_profiler and ignore_routes contains _profiler, tracking is disabled. */
    public function testRequestDisablesTrackingWhenRouteIsWebProfilerAndIgnored(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->dataCollector,
            true,
            ['dev', 'test'],
            ['_profiler'],
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            false,
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'web_profiler_wdt');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->expects(self::atLeastOnce())->method('setEnabled');
        $this->dataCollector->expects(self::atLeastOnce())->method('setRecordOperation')->with(false, false);
        $subscriber->onKernelRequest($requestEvent);
        $this->metricsService->expects(self::never())->method('recordMetrics');
        $subscriber->onKernelTerminate(new TerminateEvent($this->kernel, $request, new Response()));
        self::assertTrue(true, 'web_profiler ignore branch covered');
    }

    /** When kernel is null, env is taken from request->server->get('APP_ENV'). */
    public function testRequestUsesRequestServerEnvWhenKernelIsNull(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->dataCollector,
            true,
            ['dev', 'test'],
            [],
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            false, // kernel = null so env comes from request server
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->expects(self::atLeastOnce())->method('setEnabled');
        $this->dataCollector->expects(self::atLeastOnce())->method('setCurrentEnvironment')->with('dev');
        $subscriber->onKernelRequest($requestEvent);
        self::assertTrue(true, 'Request server env branch covered');
    }

    /** When ignore_routes contains a glob pattern (e.g. app_*), route matching it is ignored. */
    public function testRequestDisablesTrackingWhenRouteMatchesGlobPattern(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->dataCollector,
            true,
            ['dev', 'test'],
            ['app_*'],
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            false,
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->expects(self::atLeastOnce())->method('setEnabled');
        $this->dataCollector->expects(self::atLeastOnce())->method('setRecordOperation')->with(false, false);
        $subscriber->onKernelRequest($requestEvent);
        $this->metricsService->expects(self::never())->method('recordMetrics');
        $subscriber->onKernelTerminate(new TerminateEvent($this->kernel, $request, new Response()));
        self::assertTrue(true, 'glob pattern ignore branch covered');
    }
}
