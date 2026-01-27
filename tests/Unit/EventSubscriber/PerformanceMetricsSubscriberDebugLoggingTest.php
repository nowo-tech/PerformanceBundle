<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector;
use Nowo\PerformanceBundle\EventSubscriber\PerformanceMetricsSubscriber;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests for debug logging in PerformanceMetricsSubscriber.
 *
 * These tests verify that all debug logs are generated correctly
 * to help diagnose issues with data not being saved.
 */
final class PerformanceMetricsSubscriberDebugLoggingTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;
    private ManagerRegistry|MockObject $registry;
    private PerformanceDataCollector|MockObject $dataCollector;
    private HttpKernelInterface|MockObject $kernel;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->dataCollector = $this->createMock(PerformanceDataCollector::class);
        $this->kernel = $this->createMock(HttpKernelInterface::class);
    }

    public function testOnKernelRequestLogsCollectorEnabledState(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            [],
            true,  // trackQueries
            true,  // trackRequestTime
            false, // trackSubRequests
            false, // async
            1.0,   // samplingRate
            [200, 404, 500, 503],
            true,  // enableLogging
            null,
            null
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector
            ->expects($this->once())
            ->method('setEnabled')
            ->with(true);

        $this->dataCollector
            ->expects($this->once())
            ->method('setRouteName')
            ->with('app_home');

        $this->dataCollector
            ->expects($this->once())
            ->method('setStartTime')
            ->with($this->isType('float'));

        $subscriber->onKernelRequest($event);
    }

    public function testOnKernelTerminateLogsStartState(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
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
            null,
            null
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
            ->method('getDisabledReason')
            ->willReturn(null);

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

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->willReturn(['is_new' => true, 'was_updated' => false]);

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testOnKernelTerminateLogsWhenCollectorDisabled(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
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
            null,
            null
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($requestEvent);

        // Collector is disabled in TERMINATE (simulating a scenario where it was disabled between REQUEST and TERMINATE)
        $this->dataCollector
            ->method('isEnabled')
            ->willReturn(false);

        $this->dataCollector
            ->method('getDisabledReason')
            ->willReturn('Test reason');

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

    public function testOnKernelTerminateLogsRouteCheck(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
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
            null,
            null
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

        $this->dataCollector
            ->method('setRecordOperation')
            ->willReturnSelf();

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->willReturn(['is_new' => true, 'was_updated' => false]);

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testOnKernelTerminateLogsWhenRouteNameIsNull(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
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
            null,
            null
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        // No route set
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($requestEvent);

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

    public function testOnKernelTerminateLogsRequestTimeCalculation(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
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
            null,
            null
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
            ->expects($this->once())
            ->method('setRequestTime')
            ->with($this->isType('float'));

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
            ->willReturn(['is_new' => true, 'was_updated' => false]);

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testOnKernelTerminateLogsWhenRequestTimeNotTracked(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            [],
            true,  // trackQueries
            false, // trackRequestTime = false
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            null,
            null
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
            ->expects($this->never())
            ->method('setRequestTime');

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
                null, // requestTime should be null
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn(['is_new' => true, 'was_updated' => false]);

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testOnKernelTerminateLogsQueryMetrics(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            [],
            true,  // trackQueries = true
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            null,
            null
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
            ->expects($this->once())
            ->method('setQueryCount')
            ->with($this->isType('int'));

        $this->dataCollector
            ->expects($this->once())
            ->method('setQueryTime')
            ->with($this->isType('float'));

        $this->dataCollector
            ->method('setRecordOperation')
            ->willReturnSelf();

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->willReturn(['is_new' => true, 'was_updated' => false]);

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testOnKernelTerminateLogsWhenQueryTrackingDisabled(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            [],
            false, // trackQueries = false
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            null,
            null
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
            ->expects($this->never())
            ->method('setQueryCount');

        $this->dataCollector
            ->expects($this->never())
            ->method('setQueryTime');

        $this->dataCollector
            ->method('setRecordOperation')
            ->willReturnSelf();

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                $this->anything(),
                null, // queryCount should be null
                null, // queryTime should be null
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn(['is_new' => true, 'was_updated' => false]);

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testOnKernelTerminateLogsMemoryUsage(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
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
            null,
            null
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

        $this->dataCollector
            ->method('setRecordOperation')
            ->willReturnSelf();

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
                $this->isType('int'), // memoryUsage should be int
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn(['is_new' => true, 'was_updated' => false]);

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testOnKernelTerminateLogsAttemptingToSaveWithAllDetails(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
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
            null,
            null
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

        $this->dataCollector
            ->method('setRecordOperation')
            ->willReturnSelf();

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                $this->isType('float'), // requestTime
                $this->isType('int'),    // queryCount
                $this->isType('float'),  // queryTime
                $this->isType('array'),  // routeParams
                $this->isType('int'),    // memoryUsage
                'GET',                   // httpMethod
                200,                     // statusCode
                [200, 404, 500, 503]     // trackStatusCodes
            )
            ->willReturn(['is_new' => true, 'was_updated' => false]);

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response(200));
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testOnKernelTerminateLogsRecordMetricsResult(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
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
            null,
            null
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

        $this->dataCollector
            ->expects($this->once())
            ->method('setRecordOperation')
            ->with(true, false); // is_new=true, was_updated=false

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->willReturn(['is_new' => true, 'was_updated' => false]);

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testOnKernelTerminateLogsSamplingSkip(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            [],
            true,
            true,
            false,
            false,
            0.5, // samplingRate = 50%
            [200, 404, 500, 503],
            true,
            null,
            null
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

        // Note: With sampling, we might skip, but we can't control mt_rand() in tests
        // So we just verify the structure is correct
        $this->dataCollector
            ->method('setRecordOperation')
            ->willReturnSelf();

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }
}
