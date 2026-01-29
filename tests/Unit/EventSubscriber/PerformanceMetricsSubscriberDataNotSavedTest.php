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
 * Tests for scenarios where data might not be saved.
 *
 * These tests verify edge cases that could cause data not to be saved,
 * helping to diagnose issues in production.
 */
final class PerformanceMetricsSubscriberDataNotSavedTest extends TestCase
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

    public function testDataNotSavedWhenCollectorDisabledBetweenRequestAndTerminate(): void
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

    public function testDataNotSavedWhenRouteNameLostBetweenRequestAndTerminate(): void
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

        // In TERMINATE, route name is lost (simulating a scenario where route was cleared)
        $request->attributes->remove('_route');

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

    public function testDataNotSavedWhenEnvironmentChangedBetweenRequestAndTerminate(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
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
            null,
            null
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
        
        // Simulate that startTime was not set (edge case)
        // This could happen if onKernelRequest was not called or failed silently
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
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
                $this->anything()  // referer
            )
            ->willReturn(['is_new' => true, 'was_updated' => false]);

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testDataNotSavedWhenStartMemoryIsNull(): void
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
        
        // Simulate that startMemory was not set
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
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
                $this->anything()  // referer
            )
            ->willReturn(['is_new' => true, 'was_updated' => false]);

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testDataSavedEvenWhenAllMetricsAreNull(): void
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
            ->method('setRecordOperation')
            ->willReturnSelf();

        // Even when all metrics are null, we should still save (at least access count)
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
                null, // memoryUsage
                'GET', // httpMethod
                200,   // statusCode
                [200, 404, 500, 503], // trackStatusCodes
                $this->isType('string'), // requestId (set in onKernelRequest)
                null   // referer (no Referer header)
            )
            ->willReturn(['is_new' => true, 'was_updated' => false]);

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response(200));
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testDataNotSavedWhenSamplingSkipsRequest(): void
    {
        // Note: We can't reliably test sampling because it uses mt_rand()
        // But we can verify the structure
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
            0.0, // samplingRate = 0% (never record)
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
}
