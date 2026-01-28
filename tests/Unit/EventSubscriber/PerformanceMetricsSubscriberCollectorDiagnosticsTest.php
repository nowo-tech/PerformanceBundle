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
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Tests that the collector receives env, route and configured environments
 * even when tracking is disabled (for diagnostics in toolbar panel).
 */
final class PerformanceMetricsSubscriberCollectorDiagnosticsTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;
    private ManagerRegistry|MockObject $registry;
    private PerformanceDataCollector|MockObject $dataCollector;
    private HttpKernelInterface|MockObject $httpKernel;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->dataCollector = $this->createMock(PerformanceDataCollector::class);
        $this->httpKernel = $this->createMock(HttpKernelInterface::class);
    }

    public function testWhenBundleDisabledCollectorReceivesEnvAndRouteBeforeDisable(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
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
            null,
            null // no kernel, use request
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $event = new RequestEvent($this->httpKernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->expects(self::once())->method('setConfiguredEnvironments')->with(['dev', 'test']);
        $this->dataCollector->expects(self::once())->method('setCurrentEnvironment')->with('dev');
        $this->dataCollector->expects(self::once())->method('setRouteName')->with('app_home');
        $this->dataCollector->expects(self::once())->method('setEnabled')->with(false);
        $this->dataCollector->expects(self::once())->method('setDisabledReason')
            ->with('Bundle is disabled in configuration (nowo_performance.enabled: false)');

        $subscriber->onKernelRequest($event);
    }

    public function testWhenSubRequestCollectorReceivesEnvAndRouteBeforeDisable(): void
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
            false, // trackSubRequests = false
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            null,
            null
        );

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', '_fragment');
        $event = new RequestEvent($this->httpKernel, $request, HttpKernelInterface::SUB_REQUEST);

        $this->dataCollector->expects(self::once())->method('setConfiguredEnvironments')->with(['dev', 'test']);
        $this->dataCollector->expects(self::once())->method('setCurrentEnvironment')->with('dev');
        $this->dataCollector->expects(self::once())->method('setRouteName')->with('_fragment');
        $this->dataCollector->expects(self::once())->method('setEnabled')->with(false);
        $this->dataCollector->expects(self::once())->method('setDisabledReason')
            ->with(self::stringContains('sub-request'));

        $subscriber->onKernelRequest($event);
    }

    public function testWhenEnvNotAllowedCollectorReceivesEnvAndRouteBeforeDisable(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn('prod');

        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
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
            null,  // requestStack
            null,  // stopwatch
            $kernel
        );

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $event = new RequestEvent($this->httpKernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->expects(self::once())->method('setConfiguredEnvironments')->with(['dev', 'test']);
        $this->dataCollector->expects(self::once())->method('setCurrentEnvironment')->with('prod');
        $this->dataCollector->expects(self::once())->method('setRouteName')->with('app_home');
        $this->dataCollector->expects(self::once())->method('setEnabled')->with(false);
        $this->dataCollector->expects(self::once())->method('setDisabledReason')
            ->with(self::stringContains('not in allowed environments'));

        $subscriber->onKernelRequest($event);
    }

    public function testWhenRouteIgnoredCollectorReceivesEnvAndRouteThenDisabled(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn('dev');

        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            ['_wdt', '_profiler'], // ignore_routes
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            null,  // requestStack
            null,  // stopwatch
            $kernel
        );

        $request = Request::create('/');
        $request->attributes->set('_route', '_profiler');
        $event = new RequestEvent($this->httpKernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->expects(self::once())->method('setConfiguredEnvironments')->with(['dev', 'test']);
        $this->dataCollector->expects(self::once())->method('setCurrentEnvironment')->with('dev');
        $this->dataCollector->expects(self::once())->method('setRouteName')->with('_profiler');
        $this->dataCollector->expects(self::atLeastOnce())->method('setEnabled');
        $this->dataCollector->expects(self::once())->method('setDisabledReason')
            ->with('Route "_profiler" is in ignore_routes list');
        $this->dataCollector->expects(self::once())->method('setRecordOperation')->with(false, false);

        $subscriber->onKernelRequest($event);
    }
}
