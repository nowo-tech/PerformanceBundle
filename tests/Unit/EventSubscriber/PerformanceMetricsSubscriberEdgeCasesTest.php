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
 * Additional edge case tests for PerformanceMetricsSubscriber.
 */
final class PerformanceMetricsSubscriberEdgeCasesTest extends TestCase
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

    public function testOnKernelRequestWithVeryLongRouteName(): void
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

        $longRouteName = str_repeat('a', 1000);
        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', $longRouteName);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector
            ->method('setEnabled')
            ->willReturnSelf();

        $this->dataCollector
            ->expects($this->once())
            ->method('setRouteName')
            ->with($longRouteName);

        $subscriber->onKernelRequest($event);
        $this->assertTrue(true);
    }

    public function testOnKernelRequestWithSpecialCharactersInRouteName(): void
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

        $specialRouteName = 'app_route_with-special.chars@123';
        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', $specialRouteName);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector
            ->method('setEnabled')
            ->willReturnSelf();

        $this->dataCollector
            ->expects($this->once())
            ->method('setRouteName')
            ->with($specialRouteName);

        $subscriber->onKernelRequest($event);
        $this->assertTrue(true);
    }

    public function testOnKernelTerminateWithVeryLongRequestTime(): void
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

        // Simulate very long request time
        $reflection = new \ReflectionClass($subscriber);
        $startTimeProperty = $reflection->getProperty('startTime');
        $startTimeProperty->setAccessible(true);
        $startTimeProperty->setValue($subscriber, microtime(true) - 3600.0); // 1 hour ago

        $this->dataCollector
            ->method('isEnabled')
            ->willReturn(true);

        $this->dataCollector
            ->expects($this->once())
            ->method('setRequestTime')
            ->with($this->greaterThan(3600.0));

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
        $this->assertTrue(true);
    }

    public function testOnKernelTerminateWithNegativeRequestTime(): void
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

        // Simulate negative request time (shouldn't happen, but test edge case)
        $reflection = new \ReflectionClass($subscriber);
        $startTimeProperty = $reflection->getProperty('startTime');
        $startTimeProperty->setAccessible(true);
        $startTimeProperty->setValue($subscriber, microtime(true) + 1.0); // Future time

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

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
        $this->assertTrue(true);
    }

    public function testOnKernelTerminateWithSamplingRateZeroNeverRecords(): void
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
            0.0, // Sampling rate = 0 (never record)
            [200, 404, 500, 503],
            true,
            null,
            null
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
            ->expects($this->once())
            ->method('setRecordOperation')
            ->with(false, false);

        $this->metricsService
            ->expects($this->never())
            ->method('recordMetrics');

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testOnKernelTerminateWithSamplingRatePartial(): void
    {
        // With sampling rate < 1.0, we can't predict if it will record or not
        // But we can test that the logic works
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
            0.5, // 50% sampling rate
            [200, 404, 500, 503],
            true,
            null,
            null
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

        // May or may not call recordMetrics depending on random sampling
        $this->metricsService
            ->expects($this->atMost(1))
            ->method('recordMetrics');

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
        $this->assertTrue(true);
    }

    public function testOnKernelRequestWithMultipleIgnoreRoutes(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            ['_wdt', '_profiler', '_error', 'api_doc', 'admin_panel'],
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
        $request->attributes->set('_route', 'api_doc');
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

        $subscriber->onKernelRequest($event);
    }

    public function testOnKernelTerminateWithDifferentHttpMethods(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

        foreach ($methods as $method) {
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

            $request = Request::create('/', $method);
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
                ->with(
                    'app_home',
                    'dev',
                    $this->anything(),
                    $this->anything(),
                    $this->anything(),
                    $this->anything(),
                    $this->anything(),
                    $method
                )
                ->willReturn(['is_new' => true, 'was_updated' => false]);

            $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
            $subscriber->onKernelTerminate($terminateEvent);
        }

        $this->assertTrue(true);
    }

    public function testOnKernelTerminateWithDifferentStatusCodes(): void
    {
        $statusCodes = [200, 201, 301, 302, 400, 401, 403, 404, 500, 502, 503];

        foreach ($statusCodes as $statusCode) {
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

            $response = new Response();
            $response->setStatusCode($statusCode);

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
                    $this->anything(),
                    'GET',
                    $statusCode
                )
                ->willReturn(['is_new' => true, 'was_updated' => false]);

            $terminateEvent = new TerminateEvent($this->kernel, $request, $response);
            $subscriber->onKernelTerminate($terminateEvent);
        }

        $this->assertTrue(true);
    }
}
