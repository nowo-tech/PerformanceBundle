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
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class PerformanceMetricsSubscriberTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;
    private ManagerRegistry|MockObject $registry;
    private PerformanceDataCollector|MockObject $dataCollector;
    private PerformanceMetricsSubscriber $subscriber;
    private HttpKernelInterface|MockObject $kernel;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->dataCollector = $this->createMock(PerformanceDataCollector::class);
        $this->kernel = $this->createMock(HttpKernelInterface::class);

        $this->subscriber = new PerformanceMetricsSubscriber(
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
            null,  // requestStack
            null   // kernel
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = PerformanceMetricsSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('kernel.request', $events);
        $this->assertArrayHasKey('kernel.terminate', $events);
    }

    public function testOnKernelRequestWhenDisabled(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            false, // disabled
            ['dev'],
            [],
            true,
            true,
            false, // trackSubRequests
            false, // async
            1.0,   // samplingRate
            [200, 404, 500, 503], // trackStatusCodes
            null,  // requestStack
            null   // kernel
        );

        $request = Request::create('/');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector
            ->expects($this->once())
            ->method('setEnabled')
            ->with(false);

        $subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestWhenNotMainRequest(): void
    {
        $request = Request::create('/');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $this->dataCollector
            ->expects($this->once())
            ->method('setEnabled')
            ->with(false);

        $this->subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestWhenSubRequestAndTrackSubRequestsEnabled(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,  // enabled
            ['dev', 'test'],
            [],     // no ignored routes
            true,   // trackQueries
            true,   // trackRequestTime
            true,   // trackSubRequests = ENABLED
            false,  // async
            1.0,    // samplingRate
            [200, 404, 500, 503], // trackStatusCodes
            null,   // requestStack
            null    // kernel
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $this->dataCollector
            ->expects($this->once())
            ->method('setEnabled')
            ->with(true);

        $this->dataCollector
            ->expects($this->once())
            ->method('setRouteName')
            ->with(null);

        $subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestWhenSubRequestAndTrackSubRequestsDisabled(): void
    {
        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $this->dataCollector
            ->expects($this->once())
            ->method('setEnabled')
            ->with(false);

        $this->dataCollector
            ->expects($this->once())
            ->method('setDisabledReason')
            ->with($this->stringContains('sub-request'));

        $this->subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestWhenEnvironmentNotEnabled(): void
    {
        $request = Request::create('/');
        $request->server->set('APP_ENV', 'prod');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector
            ->expects($this->once())
            ->method('setEnabled')
            ->with(false);

        $this->subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestWhenRouteIsIgnored(): void
    {
        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', '_wdt');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector
            ->expects($this->exactly(2))
            ->method('setEnabled')
            ->willReturnCallback(function ($enabled) {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    $this->assertTrue($enabled);
                } else {
                    $this->assertFalse($enabled);
                }
            });

        $this->subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestSuccess(): void
    {
        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $request->attributes->set('_route_params', ['id' => 123]);
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

        $this->subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestWithoutRequestTimeTracking(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev'],
            [],
            true,
            false, // track_request_time = false
            false, // trackSubRequests
            false, // async
            1.0,   // samplingRate
            [200, 404, 500, 503], // trackStatusCodes
            null,  // requestStack
            null   // kernel
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector
            ->expects($this->never())
            ->method('setStartTime');

        $subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestWithoutQueryTracking(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev'],
            [],
            false, // track_queries = false
            true,  // trackRequestTime
            false, // trackSubRequests
            false, // async
            1.0,   // samplingRate
            [200, 404, 500, 503], // trackStatusCodes
            null,  // requestStack
            null   // kernel
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);
        // Should not throw any exception
        $this->assertTrue(true);
    }

    public function testOnKernelTerminateWhenDisabled(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            false,
            ['dev'],
            [],
            true,
            true,
            false, // trackSubRequests
            false, // async
            1.0,   // samplingRate
            [200, 404, 500, 503], // trackStatusCodes
            null,  // requestStack
            null   // kernel
        );

        $request = Request::create('/');
        $event = new TerminateEvent($this->kernel, $request, new \Symfony\Component\HttpFoundation\Response());

        $this->metricsService
            ->expects($this->never())
            ->method('recordMetrics');

        $subscriber->onKernelTerminate($event);
    }

    public function testOnKernelTerminateWhenNoRouteName(): void
    {
        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $event = new TerminateEvent($this->kernel, $request, new \Symfony\Component\HttpFoundation\Response());

        $this->dataCollector
            ->method('isEnabled')
            ->willReturn(true);

        $this->metricsService
            ->expects($this->never())
            ->method('recordMetrics');

        $this->subscriber->onKernelTerminate($event);
    }

    public function testOnKernelTerminateSuccess(): void
    {
        // First set up the request
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

        $this->subscriber->onKernelRequest($requestEvent);

        // Now test terminate
        $this->dataCollector
            ->method('isEnabled')
            ->willReturn(true);

        $this->dataCollector
            ->expects($this->once())
            ->method('setRequestTime')
            ->with($this->isType('float'));

        $this->dataCollector
            ->expects($this->once())
            ->method('setQueryCount')
            ->with(0);

        $this->dataCollector
            ->expects($this->once())
            ->method('setQueryTime')
            ->with(0.0);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                $this->isType('float'),
                0,
                0.0,
                []
            );

        $terminateEvent = new TerminateEvent($this->kernel, $request, new \Symfony\Component\HttpFoundation\Response());
        $this->subscriber->onKernelTerminate($terminateEvent);
    }

    public function testOnKernelTerminateWithException(): void
    {
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

        $this->subscriber->onKernelRequest($requestEvent);

        $this->dataCollector
            ->method('isEnabled')
            ->willReturn(true);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->willThrowException(new \Exception('Database error'));

        // Should not throw exception, should fail silently
        $terminateEvent = new TerminateEvent($this->kernel, $request, new \Symfony\Component\HttpFoundation\Response());
        $this->subscriber->onKernelTerminate($terminateEvent);

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testOnKernelTerminateWhenEnvironmentNotEnabled(): void
    {
        $request = Request::create('/');
        $request->server->set('APP_ENV', 'prod'); // Not in enabled environments
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector
            ->method('setEnabled')
            ->willReturnSelf();

        $this->subscriber->onKernelRequest($requestEvent);

        $this->dataCollector
            ->method('isEnabled')
            ->willReturn(false);

        $this->metricsService
            ->expects($this->never())
            ->method('recordMetrics');

        $terminateEvent = new TerminateEvent($this->kernel, $request, new \Symfony\Component\HttpFoundation\Response());
        $this->subscriber->onKernelTerminate($terminateEvent);
    }

    public function testOnKernelRequestWithQueryTracking(): void
    {
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

        $this->subscriber->onKernelRequest($event);

        // Verify query tracking was initialized (we can't directly test private methods,
        // but we can verify the behavior through public methods)
        $this->assertTrue(true);
    }

    public function testOnKernelTerminateWithQueryTracking(): void
    {
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

        $this->subscriber->onKernelRequest($requestEvent);

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

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics');

        $terminateEvent = new TerminateEvent($this->kernel, $request, new \Symfony\Component\HttpFoundation\Response());
        $this->subscriber->onKernelTerminate($terminateEvent);
    }

    public function testOnKernelTerminateWithSamplingRateSkipsRecording(): void
    {
        // Create subscriber with sampling rate of 0.0 (never record)
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
            0.0,   // sampling rate = 0.0 means never record
            [200, 404, 500, 503], // trackStatusCodes
            null,  // requestStack
            null   // kernel
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        
        $subscriber->onKernelRequest($requestEvent);

        $this->dataCollector
            ->expects($this->once())
            ->method('setSavingToDatabase')
            ->with(false, $this->stringContains('Sampled out'));

        $this->metricsService
            ->expects($this->never())
            ->method('recordMetrics');

        $terminateEvent = new TerminateEvent($this->kernel, $request, new \Symfony\Component\HttpFoundation\Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testOnKernelTerminateWithSamplingRateOneRecordsAll(): void
    {
        // Create subscriber with sampling rate of 1.0 (always record)
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
            false,
            1.0 // sampling rate = 1.0 means always record
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        
        $subscriber->onKernelRequest($requestEvent);

        $this->dataCollector
            ->method('setSavingToDatabase')
            ->with($this->anything(), $this->anything());

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics');

        $terminateEvent = new TerminateEvent($this->kernel, $request, new \Symfony\Component\HttpFoundation\Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }
}
