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
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests for ignore_routes behavior (literal, prefix, glob, web_profiler_ special case).
 * Subscriber must run after RouterListener (priority 31) so _route is set; these tests set _route explicitly.
 */
final class PerformanceMetricsSubscriberIgnoreRoutesTest extends TestCase
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
        $this->kernel->method('getEnvironment')->willReturn('dev');
    }

    public function testRouteMatchingGlobPatternDisablesTracking(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            ['_wdt*'], // glob: matches _wdt, _wdt_open, _wdt_open_file
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            null,
            null,
            $this->kernel
        );

        $request = Request::create('/');
        $request->attributes->set('_route', '_wdt_open');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->expects(self::atLeastOnce())->method('setEnabled');
        $this->dataCollector->expects(self::once())->method('setDisabledReason')
            ->with('Route "_wdt_open" is in ignore_routes list');
        $this->dataCollector->expects(self::once())->method('setRecordOperation')->with(false, false);

        $subscriber->onKernelRequest($event);
    }

    public function testWebProfilerWdtWhenWdtInIgnoreListDisablesTracking(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            ['_wdt'], // literal; web_profiler_wdt is also ignored via special case
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            null,
            null,
            $this->kernel
        );

        $request = Request::create('/');
        $request->attributes->set('_route', 'web_profiler_wdt');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->expects(self::atLeastOnce())->method('setEnabled');
        $this->dataCollector->expects(self::once())->method('setDisabledReason')
            ->with('Route "web_profiler_wdt" is in ignore_routes list');
        $this->dataCollector->expects(self::once())->method('setRecordOperation')->with(false, false);

        $subscriber->onKernelRequest($event);
    }

    public function testLiteralPrefixMatchDisablesTracking(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            ['_wdt'], // literal: exact or route name starting with _wdt_
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            null,
            null,
            $this->kernel
        );

        $request = Request::create('/');
        $request->attributes->set('_route', '_wdt_open_file');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->expects(self::atLeastOnce())->method('setEnabled');
        $this->dataCollector->expects(self::once())->method('setDisabledReason')
            ->with('Route "_wdt_open_file" is in ignore_routes list');
        $this->dataCollector->expects(self::once())->method('setRecordOperation')->with(false, false);

        $subscriber->onKernelRequest($event);
    }

    public function testRouteNotInIgnoreListKeepsTrackingEnabled(): void
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
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            null,
            null,
            $this->kernel
        );

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->expects(self::once())->method('setEnabled')->with(true);
        $this->dataCollector->expects(self::never())->method('setDisabledReason');
        $this->dataCollector->expects(self::never())->method('setRecordOperation');

        $subscriber->onKernelRequest($event);
    }

    public function testExactMatchDisablesTracking(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            ['_error'],
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            null,
            null,
            $this->kernel
        );

        $request = Request::create('/');
        $request->attributes->set('_route', '_error');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->expects(self::atLeastOnce())->method('setEnabled');
        $this->dataCollector->expects(self::once())->method('setDisabledReason')
            ->with('Route "_error" is in ignore_routes list');

        $subscriber->onKernelRequest($event);
    }

    public function testWebProfilerPatternMatchesWebProfilerProfiler(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            ['web_profiler*'],
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            null,
            null,
            $this->kernel
        );

        $request = Request::create('/');
        $request->attributes->set('_route', 'web_profiler_profiler');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->expects(self::atLeastOnce())->method('setEnabled');
        $this->dataCollector->expects(self::once())->method('setDisabledReason')
            ->with('Route "web_profiler_profiler" is in ignore_routes list');

        $subscriber->onKernelRequest($event);
    }
}
