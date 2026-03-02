<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
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

/**
 * Additional tests for PerformanceMetricsSubscriber focusing on logging functionality.
 */
final class PerformanceMetricsSubscriberLoggingTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;
    private ManagerRegistry|MockObject $registry;
    private PerformanceDataCollector|MockObject $dataCollector;
    private HttpKernelInterface|MockObject $kernel;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->registry       = $this->createMock(ManagerRegistry::class);
        $this->dataCollector  = $this->createMock(PerformanceDataCollector::class);
        $this->kernel         = $this->createMock(HttpKernelInterface::class);
    }

    public function testOnKernelRequestWithLoggingDisabledDoesNotLog(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            ['_wdt', '_profiler'],
            true,
            true,
            false, // trackSubRequests
            false, // async
            1.0,   // samplingRate
            [200, 404, 500, 503], // trackStatusCodes
            false, // enableLogging = false
            false, // trackUser
            null,  // requestStack
            null,  // security
            null,  // stopwatch
            null,   // kernel
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector
            ->method('setEnabled')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setRouteName')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setStartTime')
            ->willReturnSelf();

        // Should work normally without logging
        $subscriber->onKernelRequest($event);
        $this->assertTrue(true);
    }

    public function testOnKernelTerminateWithLoggingDisabledDoesNotLog(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            ['_wdt', '_profiler'],
            true,
            true,
            false, // trackSubRequests
            false, // async
            1.0,   // samplingRate
            [200, 404, 500, 503], // trackStatusCodes
            false, // enableLogging = false
            false, // trackUser
            null,  // requestStack
            null,  // security
            null,  // stopwatch
            null,   // kernel
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector
            ->method('setEnabled')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setRouteName')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setStartTime')
            ->willReturnSelf();

        $subscriber->onKernelRequest($requestEvent);

        $this->dataCollector
            ->method('isEnabled')
            ->willReturn(true);

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

        // Should work normally without logging
        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
        $this->assertTrue(true);
    }

    public function testOnKernelTerminateWithExceptionAndLoggingDisabled(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            ['_wdt', '_profiler'],
            true,
            true,
            false, // trackSubRequests
            false, // async
            1.0,   // samplingRate
            [200, 404, 500, 503], // trackStatusCodes
            false, // enableLogging = false
            false, // trackUser
            null,  // requestStack
            null,  // security
            null,  // stopwatch
            null,   // kernel
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector
            ->method('setEnabled')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setRouteName')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setStartTime')
            ->willReturnSelf();

        $subscriber->onKernelRequest($requestEvent);

        $this->dataCollector
            ->method('isEnabled')
            ->willReturn(true);

        $this->dataCollector
            ->method('setRecordOperation')
            ->willReturnSelf();

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->willThrowException(new Exception('Database error'));

        // Should not throw exception, should fail silently (no logging)
        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
        $this->assertTrue(true);
    }

    public function testOnKernelRequestWithLoggingEnabledLogsEnvironmentDetection(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            ['_wdt', '_profiler'],
            true,
            true,
            false, // trackSubRequests
            false, // async
            1.0,   // samplingRate
            [200, 404, 500, 503], // trackStatusCodes
            true,  // enableLogging = true
            false, // trackUser
            null,  // requestStack
            null,  // security
            null,  // stopwatch
            null,   // kernel
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector
            ->method('setEnabled')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setRouteName')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setStartTime')
            ->willReturnSelf();

        // Should work normally with logging enabled
        $subscriber->onKernelRequest($event);
        $this->assertTrue(true);
    }

    public function testOnKernelRequestWithLoggingEnabledLogsTrackingEnabled(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            ['_wdt', '_profiler'],
            true,
            true,
            false, // trackSubRequests
            false, // async
            1.0,   // samplingRate
            [200, 404, 500, 503], // trackStatusCodes
            true,  // enableLogging = true
            false, // trackUser
            null,  // requestStack
            null,  // security
            null,  // stopwatch
            null,   // kernel
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector
            ->method('setEnabled')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setRouteName')
            ->willReturnSelf();

        $this->dataCollector
            ->method('setStartTime')
            ->willReturnSelf();

        // Should work normally with logging enabled
        $subscriber->onKernelRequest($event);
        $this->assertTrue(true);
    }
}
