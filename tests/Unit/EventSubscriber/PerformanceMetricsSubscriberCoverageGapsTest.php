<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector;
use Nowo\PerformanceBundle\EventSubscriber\PerformanceMetricsSubscriber;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Stopwatch\Stopwatch;

use const PHP_INT_MAX;

/**
 * REQ-TEST-003: Stopwatch query-time fallback across a whole request, negative memory delta, middleware counters of
 * getQueryMetrics() without a request, and the remaining isRouteIgnored() rules.
 */
final class PerformanceMetricsSubscriberCoverageGapsTest extends TestCase
{
    public function testStopwatchFallbackAndNegativeMemoryDeltaOverAWholeRequest(): void
    {
        $collector = $this->createMock(PerformanceDataCollector::class);
        $collector->method('isEnabled')->willReturn(true);
        $stopwatch  = new Stopwatch();
        $subscriber = $this->subscriber($collector, ['ignored'], $stopwatch);
        $kernel     = $this->createMock(HttpKernelInterface::class);
        $request    = Request::create('/');
        $request->server->set('APP_ENV', 'test');
        $request->attributes->set('_route', 'app_home');

        $subscriber->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        self::assertTrue($stopwatch->isStarted('doctrine.queries'));
        (new ReflectionProperty(PerformanceMetricsSubscriber::class, 'startMemory'))->setValue($subscriber, PHP_INT_MAX);

        $subscriber->onKernelTerminate(new TerminateEvent($kernel, $request, new Response()));

        self::assertFalse($stopwatch->isStarted('doctrine.queries'));
    }

    public function testGetQueryMetricsWithoutRequestReturnsTheMiddlewareCounters(): void
    {
        $result = (new ReflectionMethod(PerformanceMetricsSubscriber::class, 'getQueryMetrics'))
            ->invoke($this->subscriber($this->createMock(PerformanceDataCollector::class), [], new Stopwatch()), null);

        self::assertSame(['count' => 0, 'time' => 0.0], $result);
    }

    public function testGetQueryMetricsSwallowsStopwatchErrors(): void
    {
        $stopwatch = new class extends Stopwatch {
            public function isStarted(string $name): bool
            {
                throw new RuntimeException('stopwatch broken');
            }
        };

        $result = (new ReflectionMethod(PerformanceMetricsSubscriber::class, 'getQueryMetrics'))
            ->invoke($this->subscriber($this->createMock(PerformanceDataCollector::class), [], $stopwatch), Request::create('/'));

        self::assertSame(['count' => 0, 'time' => 0.0], $result);
    }

    public function testIsRouteIgnoredRules(): void
    {
        $method     = new ReflectionMethod(PerformanceMetricsSubscriber::class, 'isRouteIgnored');
        $subscriber = $this->subscriber($this->createMock(PerformanceDataCollector::class), ['', '_wdt', 'other']);

        self::assertFalse($method->invoke($subscriber, null));
        self::assertFalse($method->invoke($subscriber, ''));
        self::assertTrue($method->invoke($subscriber, '_wdt_open'));
        self::assertTrue($method->invoke($subscriber, 'web_profiler_wdt'));

        $other = $this->subscriber($this->createMock(PerformanceDataCollector::class), ['', 'other']);
        self::assertFalse($method->invoke($other, 'web_profiler_wdt'));
    }

    /**
     * @param list<string> $ignoreRoutes
     */
    private function subscriber(PerformanceDataCollector $collector, array $ignoreRoutes, ?Stopwatch $stopwatch = null): PerformanceMetricsSubscriber
    {
        return new PerformanceMetricsSubscriber(
            $this->createMock(PerformanceMetricsService::class),
            $collector,
            true,
            ['test'],
            $ignoreRoutes,
            true,
            true,
            false,
            false,
            1.0,
            [200],
            false,
            false,
            null,
            null,
            $stopwatch,
        );
    }
}
