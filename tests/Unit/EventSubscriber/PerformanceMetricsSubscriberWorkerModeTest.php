<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Error;
use ErrorException;
use Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector;
use Nowo\PerformanceBundle\EventSubscriber\PerformanceMetricsSubscriber;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use function strlen;

use const E_USER_NOTICE;

/**
 * Long-running worker (FrankenPHP) without kernel.reset: the same subscriber and collector
 * instances serve consecutive requests and must not leak state between them.
 */
final class PerformanceMetricsSubscriberWorkerModeTest extends TestCase
{
    private MockObject&PerformanceMetricsService $metricsService;
    private HttpKernelInterface&MockObject $kernel;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->kernel         = $this->createMock(HttpKernelInterface::class);
    }

    public function testMemoryPeakOfPreviousRequestDoesNotLeakIntoNextRequest(): void
    {
        $collector  = new PerformanceDataCollector();
        $subscriber = $this->createSubscriber($collector);

        $recorded = [];
        $this->metricsService->method('recordMetrics')
            ->willReturnCallback(static function (string $route, string $env, ?float $requestTime, ?int $queries, ?float $queryTime, ?array $params, ?int $memoryUsage) use (&$recorded): array {
                $recorded[$route] = $memoryUsage;

                return ['is_new' => false, 'was_updated' => true];
            });

        $heavy = $this->request('heavy_route');
        $subscriber->onKernelRequest($this->requestEvent($heavy));
        $blob = str_repeat('x', 16 * 1024 * 1024);
        $this->assertSame(16 * 1024 * 1024, strlen($blob));
        unset($blob);
        $subscriber->onKernelTerminate(new TerminateEvent($this->kernel, $heavy, new Response()));

        $light = $this->request('light_route');
        $subscriber->onKernelRequest($this->requestEvent($light));
        $subscriber->onKernelTerminate(new TerminateEvent($this->kernel, $light, new Response()));

        $this->assertGreaterThanOrEqual(16 * 1024 * 1024, $recorded['heavy_route']);
        $this->assertLessThan(4 * 1024 * 1024, $recorded['light_route']);
    }

    public function testCollectorStateIsResetAtEachMainRequestWithoutKernelReset(): void
    {
        $collector  = new PerformanceDataCollector();
        $subscriber = $this->createSubscriber($collector, async: true);

        $this->metricsService->method('recordMetrics')->willReturn(['is_new' => true, 'was_updated' => false]);

        $first = $this->request('first_route');
        $subscriber->onKernelRequest($this->requestEvent($first));
        $subscriber->onKernelTerminate(new TerminateEvent($this->kernel, $first, new Response()));
        $this->assertTrue($collector->wasRecordNew());

        // Scenario A: services_resetter resets the collector but not the subscriber.
        $collector->reset();

        $second = $this->request('second_route');
        $subscriber->onKernelRequest($this->requestEvent($second));

        $this->assertNull($collector->wasRecordNew());
        $this->assertNull($collector->wasRecordUpdated());
        $this->assertTrue((new ReflectionProperty(PerformanceDataCollector::class, 'async'))->getValue($collector));

        $subscriber->onKernelTerminate(new TerminateEvent($this->kernel, $second, new Response()));

        // Scenario B: no reset at all between requests.
        $third = $this->request('third_route');
        $subscriber->onKernelRequest($this->requestEvent($third));

        $this->assertNull($collector->wasRecordNew());
        $this->assertTrue($collector->isEnabled());
        $this->assertTrue((new ReflectionProperty(PerformanceDataCollector::class, 'async'))->getValue($collector));
    }

    public function testSubRequestDoesNotResetCollectorOfMainRequest(): void
    {
        $collector  = new PerformanceDataCollector();
        $subscriber = $this->createSubscriber($collector);

        $main = $this->request('main_route');
        $subscriber->onKernelRequest($this->requestEvent($main));
        $collector->setRecordOperation(true, false);

        $subscriber->onKernelRequest(new RequestEvent($this->kernel, $this->request('fragment_route'), HttpKernelInterface::SUB_REQUEST));

        $this->assertTrue($collector->wasRecordNew());
    }

    public function testErrorWhileRecordingRestoresErrorReportingAndOutputBuffer(): void
    {
        $collector  = new PerformanceDataCollector();
        $subscriber = $this->createSubscriber($collector);

        $this->metricsService->method('recordMetrics')->willThrowException(new Error('listener failure'));

        $errorReportingBefore = error_reporting();
        $obLevelBefore        = ob_get_level();

        $request = $this->request('failing_route');
        $subscriber->onKernelRequest($this->requestEvent($request));
        $subscriber->onKernelTerminate(new TerminateEvent($this->kernel, $request, new Response()));

        $this->assertSame($errorReportingBefore, error_reporting());
        $this->assertSame($obLevelBefore, ob_get_level());
        $this->assertFalse($collector->wasRecordNew());
        $this->assertFalse($collector->wasRecordUpdated());
    }

    public function testOutputBufferOpenedByBundleIsClosedWhenNoBufferWasActive(): void
    {
        $collector  = new PerformanceDataCollector();
        $subscriber = $this->createSubscriber($collector);

        $levels = [];
        $this->metricsService->method('recordMetrics')
            ->willReturnCallback(static function () use (&$levels): array {
                $levels[] = ob_get_level();

                return ['is_new' => false, 'was_updated' => true];
            });

        // PHPUnit wraps each test in its own buffer; close it temporarily to exercise the level-0 path.
        $buffers = [];
        while (ob_get_level() > 0) {
            $buffers[] = ob_get_clean();
        }

        try {
            $request = $this->request('unbuffered_route');
            $subscriber->onKernelRequest($this->requestEvent($request));
            $subscriber->onKernelTerminate(new TerminateEvent($this->kernel, $request, new Response()));
            $levelAfter = ob_get_level();
        } finally {
            foreach (array_reverse($buffers) as $content) {
                ob_start();
                echo $content;
            }
        }

        $this->assertSame([1], $levels);
        $this->assertSame(0, $levelAfter);
    }

    /**
     * FrankenPHP worker: after the response is sent the output layer is closed and ob_start() emits
     * "Failed to create buffer"; the debug ErrorHandler turns it into an ErrorException. Metrics must
     * still be recorded and no buffer may be left behind.
     */
    public function testMetricsAreRecordedWhenOutputBufferCannotBeCreated(): void
    {
        $this->assertMetricsRecordedWhenObStartFails(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
            // Symfony ErrorHandler semantics: silenced (@) notices are not thrown.
            if ((error_reporting() & $errno) === 0) {
                return true;
            }

            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
    }

    public function testMetricsAreRecordedWhenErrorHandlerThrowsEvenForSilencedNotices(): void
    {
        $this->assertMetricsRecordedWhenObStartFails(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
    }

    /**
     * @param callable(int, string, string, int): bool $errorHandler
     */
    private function assertMetricsRecordedWhenObStartFails(callable $errorHandler): void
    {
        $collector  = new PerformanceDataCollector();
        $subscriber = new class($this->metricsService, $collector, true, ['test'], [], false, true, false, false, 1.0, [200, 404, 500, 503], false) extends PerformanceMetricsSubscriber {
            protected function startOutputBuffer(): bool
            {
                trigger_error('ob_start(): Failed to create buffer', E_USER_NOTICE);

                return false;
            }
        };

        $routes = [];
        $this->metricsService->method('recordMetrics')
            ->willReturnCallback(static function (string $route) use (&$routes): array {
                $routes[] = $route;

                return ['is_new' => true, 'was_updated' => false];
            });

        $buffers = [];
        while (ob_get_level() > 0) {
            $buffers[] = ob_get_clean();
        }

        set_error_handler($errorHandler);
        $errorReportingBefore = error_reporting();

        try {
            foreach (['first_route', 'second_route'] as $route) {
                $request = $this->request($route);
                $subscriber->onKernelRequest($this->requestEvent($request));
                $subscriber->onKernelTerminate(new TerminateEvent($this->kernel, $request, new Response()));
                $subscriber->reset();
            }
            $levelAfter = ob_get_level();
        } finally {
            restore_error_handler();
            foreach (array_reverse($buffers) as $content) {
                ob_start();
                echo $content;
            }
        }

        $this->assertSame(['first_route', 'second_route'], $routes);
        $this->assertSame(0, $levelAfter);
        $this->assertSame($errorReportingBefore, error_reporting());
        $this->assertTrue($collector->wasRecordNew());
    }

    public function testResetClearsPerRequestState(): void
    {
        $subscriber = $this->createSubscriber(new PerformanceDataCollector());
        $request    = $this->request('stateful_route');
        $subscriber->onKernelRequest($this->requestEvent($request));

        $subscriber->reset();

        foreach (['startTime', 'startMemory', 'routeName', 'routeParams', 'requestId'] as $property) {
            $this->assertNull((new ReflectionProperty(PerformanceMetricsSubscriber::class, $property))->getValue($subscriber), $property);
        }
        $this->assertSame([], (new ReflectionProperty(PerformanceMetricsSubscriber::class, 'pendingSubRequestMetrics'))->getValue($subscriber));
    }

    private function createSubscriber(PerformanceDataCollector $collector, bool $async = false): PerformanceMetricsSubscriber
    {
        return new PerformanceMetricsSubscriber(
            $this->metricsService,
            $collector,
            true,
            ['test'],
            [],
            false,
            true,
            false,
            $async,
            1.0,
            [200, 404, 500, 503],
            false,
        );
    }

    private function request(string $route): Request
    {
        $request = Request::create('/' . $route);
        $request->server->set('APP_ENV', 'test');
        $request->attributes->set('_route', $route);

        return $request;
    }

    private function requestEvent(Request $request): RequestEvent
    {
        return new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
