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
 * Tests for PerformanceMetricsSubscriber environment filtering.
 *
 * Tests that the subscriber correctly filters requests based on allowed environments,
 * including the new default that includes 'prod'.
 */
final class PerformanceMetricsSubscriberEnvironmentsTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;
    private ManagerRegistry|MockObject $registry;
    private PerformanceDataCollector|MockObject $dataCollector;
    private KernelInterface|MockObject $kernel;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->dataCollector = $this->createMock(PerformanceDataCollector::class);
        $this->kernel = $this->createMock(KernelInterface::class);
    }

    public function testProdEnvironmentAllowedByDefault(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true, // enabled
            ['prod', 'dev', 'test'], // environments (new default)
            [], // ignore_routes
            true, // track_queries
            true, // track_request_time
            false, // track_sub_requests
            false, // async
            1.0, // sampling_rate
            [200, 404, 500, 503], // track_status_codes
            true, // enable_logging
            null, // requestStack
            null, // stopwatch
            $this->kernel
        );

        $this->kernel->method('getEnvironment')->willReturn('prod');

        $request = new Request();
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->dataCollector
            ->expects($this->once())
            ->method('setEnabled')
            ->with(true);

        $subscriber->onKernelRequest($event);
    }

    public function testProdEnvironmentNotAllowedWhenExcluded(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true, // enabled
            ['dev', 'test'], // environments (old default, without prod)
            [], // ignore_routes
            true, // track_queries
            true, // track_request_time
            false, // track_sub_requests
            false, // async
            1.0, // sampling_rate
            [200, 404, 500, 503], // track_status_codes
            true, // enable_logging
            null, // requestStack
            null, // stopwatch
            $this->kernel
        );

        $this->kernel->method('getEnvironment')->willReturn('prod');

        $request = new Request();
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->dataCollector
            ->expects($this->once())
            ->method('setEnabled')
            ->with(false);

        $this->dataCollector
            ->expects($this->once())
            ->method('setDisabledReason')
            ->with($this->stringContains('not in allowed environments'));

        $subscriber->onKernelRequest($event);
    }

    public function testAllDefaultEnvironmentsAllowed(): void
    {
        $environments = ['prod', 'dev', 'test'];
        
        foreach ($environments as $env) {
            $subscriber = new PerformanceMetricsSubscriber(
                $this->metricsService,
                $this->registry,
                'default',
                $this->dataCollector,
                true, // enabled
                $environments, // environments
                [], // ignore_routes
                true, // track_queries
                true, // track_request_time
                false, // track_sub_requests
                false, // async
                1.0, // sampling_rate
                [200, 404, 500, 503], // track_status_codes
                true, // enable_logging
                null, // requestStack
                null, // stopwatch
                $this->kernel
            );

            $this->kernel->method('getEnvironment')->willReturn($env);

            $request = new Request();
            $event = new RequestEvent(
                $this->createMock(HttpKernelInterface::class),
                $request,
                HttpKernelInterface::MAIN_REQUEST
            );

            $this->dataCollector
                ->expects($this->once())
                ->method('setEnabled')
                ->with(true);

            $subscriber->onKernelRequest($event);
            
            // Reset mocks for next iteration
            $this->setUp();
        }
    }

    public function testCustomEnvironmentAllowed(): void
    {
        $subscriber = new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true, // enabled
            ['prod', 'stage'], // environments
            [], // ignore_routes
            true, // track_queries
            true, // track_request_time
            false, // track_sub_requests
            false, // async
            1.0, // sampling_rate
            [200, 404, 500, 503], // track_status_codes
            true, // enable_logging
            null, // requestStack
            null, // stopwatch
            $this->kernel
        );

        $this->kernel->method('getEnvironment')->willReturn('stage');

        $request = new Request();
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->dataCollector
            ->expects($this->once())
            ->method('setEnabled')
            ->with(true);

        $subscriber->onKernelRequest($event);
    }
}
