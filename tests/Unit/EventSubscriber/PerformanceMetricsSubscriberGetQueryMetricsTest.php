<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Nowo\PerformanceBundle\EventSubscriber\PerformanceMetricsSubscriber;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Covers private branches of getQueryMetrics (profiler, stopwatch, RequestStack).
 */
final class PerformanceMetricsSubscriberGetQueryMetricsTest extends TestCase
{
    public function testGetQueryMetricsUsesProfilerDoctrineCollectorWhenMiddlewareReturnsZero(): void
    {
        QueryTrackingMiddleware::reset();

        $doctrineCollector = $this->createMock(DoctrineDataCollector::class);
        $doctrineCollector->method('getQueryCount')->willReturn(4);
        $doctrineCollector->method('getTime')->willReturn(2500.0);

        $profile = new class($doctrineCollector) {
            public function __construct(private readonly DoctrineDataCollector $c)
            {
            }

            public function get(string $name): ?DoctrineDataCollector
            {
                return $name === 'doctrine' ? $this->c : null;
            }
        };

        $request = Request::create('/');
        $request->attributes->set('_profiler', $profile);

        $subscriber = $this->createMinimalSubscriber();
        $m          = new ReflectionMethod(PerformanceMetricsSubscriber::class, 'getQueryMetrics');

        $result = $m->invoke($subscriber, $request);

        self::assertSame(4, $result['count']);
        self::assertEqualsWithDelta(2.5, $result['time'], 0.001);
    }

    public function testGetQueryMetricsUsesGetCollectorWhenGetReturnsNull(): void
    {
        QueryTrackingMiddleware::reset();

        $doctrineCollector = $this->createMock(DoctrineDataCollector::class);
        $doctrineCollector->method('getQueryCount')->willReturn(2);
        $doctrineCollector->method('getTime')->willReturn(1000.0);

        $profile = new class($doctrineCollector) {
            public function __construct(private readonly DoctrineDataCollector $c)
            {
            }

            public function get(string $name): mixed
            {
                return null;
            }

            public function getCollector(string $name): ?DoctrineDataCollector
            {
                return $name === 'doctrine' ? $this->c : null;
            }
        };

        $request = Request::create('/');
        $request->attributes->set('_profiler', $profile);

        $subscriber = $this->createMinimalSubscriber();
        $m          = new ReflectionMethod(PerformanceMetricsSubscriber::class, 'getQueryMetrics');

        $result = $m->invoke($subscriber, $request);

        self::assertSame(2, $result['count']);
        self::assertEqualsWithDelta(1.0, $result['time'], 0.001);
    }

    public function testGetQueryMetricsUsesGetCollectorsMap(): void
    {
        QueryTrackingMiddleware::reset();

        $doctrineCollector = $this->createMock(DoctrineDataCollector::class);
        $doctrineCollector->method('getQueryCount')->willReturn(1);
        $doctrineCollector->method('getTime')->willReturn(500.0);

        $profile = new class($doctrineCollector) {
            public function __construct(private readonly DoctrineDataCollector $c)
            {
            }

            public function get(string $name): mixed
            {
                return null;
            }

            public function getCollector(string $name): mixed
            {
                return null;
            }

            public function getCollectors(): array
            {
                return ['db' => $this->c];
            }
        };

        $request = Request::create('/');
        $request->attributes->set('_profiler', $profile);

        $subscriber = $this->createMinimalSubscriber();
        $m          = new ReflectionMethod(PerformanceMetricsSubscriber::class, 'getQueryMetrics');

        $result = $m->invoke($subscriber, $request);

        self::assertSame(1, $result['count']);
        self::assertEqualsWithDelta(0.5, $result['time'], 0.001);
    }

    public function testGetQueryMetricsFallsBackToStopwatchWhenProfilerEmpty(): void
    {
        QueryTrackingMiddleware::reset();

        $stopwatch = new Stopwatch();
        $stopwatch->start('doctrine.queries');
        usleep(2000);
        // Do not call stop(): getQueryMetrics checks isStarted('doctrine.queries').

        $subscriber = $this->createMinimalSubscriber($stopwatch);

        $m = new ReflectionMethod(PerformanceMetricsSubscriber::class, 'getQueryMetrics');

        $result = $m->invoke($subscriber, Request::create('/'));

        self::assertSame(0, $result['count']);
        self::assertGreaterThan(0.0, $result['time']);
    }

    public function testGetQueryMetricsReadsProfilerFromParentRequestOnStack(): void
    {
        QueryTrackingMiddleware::reset();

        $doctrineCollector = $this->createMock(DoctrineDataCollector::class);
        $doctrineCollector->method('getQueryCount')->willReturn(3);
        $doctrineCollector->method('getTime')->willReturn(3000.0);

        $profile = new class($doctrineCollector) {
            public function __construct(private readonly DoctrineDataCollector $c)
            {
            }

            public function get(string $name): ?DoctrineDataCollector
            {
                return $name === 'doctrine' ? $this->c : null;
            }
        };

        $main = Request::create('/parent');
        $main->attributes->set('_profiler', $profile);

        $sub   = Request::create('/child');
        $stack = new RequestStack();
        $stack->push($main);
        $stack->push($sub);

        $subscriber = $this->createMinimalSubscriber(null, $stack);
        $m          = new ReflectionMethod(PerformanceMetricsSubscriber::class, 'getQueryMetrics');

        $result = $m->invoke($subscriber, $sub);

        self::assertSame(3, $result['count']);
    }

    public function testGetQueryMetricsReadsProfilerProfileAttribute(): void
    {
        QueryTrackingMiddleware::reset();

        $doctrineCollector = $this->createMock(DoctrineDataCollector::class);
        $doctrineCollector->method('getQueryCount')->willReturn(5);
        $doctrineCollector->method('getTime')->willReturn(2000.0);

        $profile = new class($doctrineCollector) {
            public function __construct(private readonly DoctrineDataCollector $c)
            {
            }

            public function get(string $name): ?DoctrineDataCollector
            {
                return $name === 'doctrine' ? $this->c : null;
            }
        };

        $request = Request::create('/');
        $request->attributes->set('_profiler_profile', $profile);

        $subscriber = $this->createMinimalSubscriber();
        $m          = new ReflectionMethod(PerformanceMetricsSubscriber::class, 'getQueryMetrics');

        $result = $m->invoke($subscriber, $request);

        self::assertSame(5, $result['count']);
        self::assertEqualsWithDelta(2.0, $result['time'], 0.001);
    }

    private function createMinimalSubscriber(?Stopwatch $stopwatch = null, ?RequestStack $stack = null): PerformanceMetricsSubscriber
    {
        $metrics   = $this->createMock(PerformanceMetricsService::class);
        $collector = $this->createMock(PerformanceDataCollector::class);

        return new PerformanceMetricsSubscriber(
            $metrics,
            $collector,
            true,
            ['test'],
            [],
            true,
            true,
            false,
            false,
            1.0,
            [200],
            true,
            false,
            $stack,
            null,
            $stopwatch,
        );
    }
}
