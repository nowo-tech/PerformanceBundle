<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector;
use Nowo\PerformanceBundle\DBAL\QueryTrackingCounters;
use Nowo\PerformanceBundle\EventSubscriber\PerformanceMetricsSubscriber;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Sub-requests (render(controller()), ESI, ErrorListener error pages) must never clobber the
 * main request's metrics; with track_sub_requests they are recorded separately.
 */
final class PerformanceMetricsSubscriberSubRequestTest extends TestCase
{
    private MockObject&PerformanceMetricsService $metricsService;
    private HttpKernelInterface&MockObject $kernel;

    /** @var list<array{route: string, requestTime: float|null, queries: int|null, params: array<string, mixed>|null, status: int|null, requestId: string|null}> */
    private array $recorded = [];

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->kernel         = $this->createMock(HttpKernelInterface::class);
        $this->recorded       = [];

        $this->metricsService->method('recordMetrics')->willReturnCallback(
            function (string $route, string $env, ?float $requestTime, ?int $queries, ?float $queryTime, ?array $params, ?int $memory, ?string $method, ?int $status, array $codes, ?string $requestId): array {
                $this->recorded[] = ['route' => $route, 'requestTime' => $requestTime, 'queries' => $queries, 'params' => $params, 'status' => $status, 'requestId' => $requestId];

                return ['is_new' => true, 'was_updated' => false];
            },
        );
    }

    public function testSubscribesToResponseForSubRequests(): void
    {
        $this->assertSame(['onKernelResponse', -1024], PerformanceMetricsSubscriber::getSubscribedEvents()[KernelEvents::RESPONSE]);
        $this->assertInstanceOf(ResetInterface::class, $this->createSubscriber(new PerformanceDataCollector()));
    }

    public function testMainRequestRunningUntrackedSubRequestIsStillRecordedUnderItsOwnRoute(): void
    {
        $collector  = new PerformanceDataCollector();
        $subscriber = $this->createSubscriber($collector);

        $main = $this->request('main_route', ['id' => '7']);
        $subscriber->onKernelRequest($this->event($main, HttpKernelInterface::MAIN_REQUEST));

        // {{ render(controller(...)) }} inside the main template
        $fragment = $this->request('fragment_route', ['slot' => 'x']);
        $subscriber->onKernelRequest($this->event($fragment, HttpKernelInterface::SUB_REQUEST));
        $subscriber->onKernelResponse(new ResponseEvent($this->kernel, $fragment, HttpKernelInterface::SUB_REQUEST, new Response()));

        $this->assertTrue($collector->isEnabled());
        $this->assertSame('main_route', (new ReflectionProperty(PerformanceDataCollector::class, 'routeName'))->getValue($collector));

        $subscriber->onKernelTerminate(new TerminateEvent($this->kernel, $main, new Response()));

        $this->assertCount(1, $this->recorded);
        $this->assertSame('main_route', $this->recorded[0]['route']);
        $this->assertSame(['id' => '7'], $this->recorded[0]['params']);
        $this->assertTrue($collector->wasRecordNew());
    }

    public function testMainRequestFailingWithErrorPageSubRequestIsRecordedWith500(): void
    {
        $collector  = new PerformanceDataCollector();
        $subscriber = $this->createSubscriber($collector, trackSubRequests: true);

        $main = $this->request('failing_route');
        $subscriber->onKernelRequest($this->event($main, HttpKernelInterface::MAIN_REQUEST));

        // ErrorListener duplicates the request with _controller set: RouterListener does not match it, no _route.
        $error = Request::create('/failing_route');
        $error->server->set('APP_ENV', 'test');
        $error->attributes->set('_controller', 'error_controller');
        $subscriber->onKernelRequest($this->event($error, HttpKernelInterface::SUB_REQUEST));
        $subscriber->onKernelResponse(new ResponseEvent($this->kernel, $error, HttpKernelInterface::SUB_REQUEST, new Response('', 500)));

        $subscriber->onKernelTerminate(new TerminateEvent($this->kernel, $main, new Response('', 500)));

        $this->assertCount(1, $this->recorded);
        $this->assertSame('failing_route', $this->recorded[0]['route']);
        $this->assertSame(500, $this->recorded[0]['status']);
    }

    public function testTrackedSubRequestIsRecordedSeparatelyWithItsOwnState(): void
    {
        $counters   = new QueryTrackingCounters();
        $collector  = new PerformanceDataCollector();
        $subscriber = $this->createSubscriber($collector, trackSubRequests: true, trackQueries: true, counters: $counters);

        $main = $this->request('main_route');
        $subscriber->onKernelRequest($this->event($main, HttpKernelInterface::MAIN_REQUEST));
        $this->runQueries($counters, 2);

        $fragment = $this->request('fragment_route', ['slot' => 'sidebar']);
        $fragment->headers->set('Referer', 'http://localhost/main_route');
        $subscriber->onKernelRequest($this->event($fragment, HttpKernelInterface::SUB_REQUEST));
        $this->runQueries($counters, 3);
        $subscriber->onKernelResponse(new ResponseEvent($this->kernel, $fragment, HttpKernelInterface::SUB_REQUEST, new Response('', 404)));

        // The main response event is not a sub-request measurement.
        $subscriber->onKernelResponse(new ResponseEvent($this->kernel, $main, HttpKernelInterface::MAIN_REQUEST, new Response()));

        $this->assertSame('main_route', (new ReflectionProperty(PerformanceDataCollector::class, 'routeName'))->getValue($collector));

        $subscriber->onKernelTerminate(new TerminateEvent($this->kernel, $main, new Response()));

        $this->assertCount(2, $this->recorded);
        [$sub, $mainRecord] = $this->recorded;

        $this->assertSame('fragment_route', $sub['route']);
        $this->assertSame(['slot' => 'sidebar'], $sub['params']);
        $this->assertSame(3, $sub['queries']);
        $this->assertSame(404, $sub['status']);
        $this->assertNotNull($sub['requestTime']);

        $this->assertSame('main_route', $mainRecord['route']);
        $this->assertSame(5, $mainRecord['queries']);
        $this->assertSame(200, $mainRecord['status']);

        $this->assertNotSame($sub['requestId'], $mainRecord['requestId']);
        $this->assertSame($main->attributes->get('_performance_request_id'), $mainRecord['requestId']);
    }

    public function testUntrackableSubRequestsAreSkipped(): void
    {
        $subscriber = $this->createSubscriber(new PerformanceDataCollector(), trackSubRequests: true, ignoreRoutes: ['_wdt']);

        $main = $this->request('main_route');
        $subscriber->onKernelRequest($this->event($main, HttpKernelInterface::MAIN_REQUEST));

        $ignored = $this->request('_wdt');
        $noRoute = Request::create('/no-route');
        $noRoute->server->set('APP_ENV', 'test');
        $otherEnv = $this->request('other_env_route');
        $otherEnv->server->set('APP_ENV', 'prod');

        foreach ([$ignored, $noRoute] as $sub) {
            $subscriber->onKernelRequest($this->event($sub, HttpKernelInterface::SUB_REQUEST));
            $subscriber->onKernelResponse(new ResponseEvent($this->kernel, $sub, HttpKernelInterface::SUB_REQUEST, new Response()));
        }

        $subscriber->onKernelTerminate(new TerminateEvent($this->kernel, $main, new Response()));
        $this->assertSame(['main_route'], array_column($this->recorded, 'route'));

        // Environment not allowed (no kernel: env comes from the request)
        $this->recorded = [];
        $subscriber->onKernelRequest($this->event($main, HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onKernelRequest($this->event($otherEnv, HttpKernelInterface::SUB_REQUEST));
        $subscriber->onKernelResponse(new ResponseEvent($this->kernel, $otherEnv, HttpKernelInterface::SUB_REQUEST, new Response()));
        $subscriber->onKernelTerminate(new TerminateEvent($this->kernel, $main, new Response()));
        $this->assertSame(['main_route'], array_column($this->recorded, 'route'));
    }

    public function testSubRequestsAreNotTrackedWhenBundleIsDisabled(): void
    {
        $subscriber = $this->createSubscriber(new PerformanceDataCollector(), trackSubRequests: true, enabled: false);

        $main     = $this->request('main_route');
        $fragment = $this->request('fragment_route');
        $subscriber->onKernelRequest($this->event($main, HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onKernelRequest($this->event($fragment, HttpKernelInterface::SUB_REQUEST));
        $subscriber->onKernelResponse(new ResponseEvent($this->kernel, $fragment, HttpKernelInterface::SUB_REQUEST, new Response()));
        $subscriber->onKernelTerminate(new TerminateEvent($this->kernel, $main, new Response()));

        $this->assertSame([], $this->recorded);
    }

    public function testSubRequestRecordingFailureAndSamplingDoNotBreakTheMainRequest(): void
    {
        $service = $this->createMock(PerformanceMetricsService::class);
        // Sub-request then main request: both fail, neither breaks the request; sampled run adds no call.
        $service->expects($this->exactly(2))->method('recordMetrics')->willThrowException(new RuntimeException('db down'));
        $this->metricsService = $service;

        $subscriber = $this->createSubscriber(new PerformanceDataCollector(), trackSubRequests: true, trackRequestTime: false);

        $main     = $this->request('main_route');
        $fragment = $this->request('fragment_route');
        $subscriber->onKernelRequest($this->event($main, HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onKernelRequest($this->event($fragment, HttpKernelInterface::SUB_REQUEST));
        $subscriber->onKernelResponse(new ResponseEvent($this->kernel, $fragment, HttpKernelInterface::SUB_REQUEST, new Response()));
        $subscriber->onKernelTerminate(new TerminateEvent($this->kernel, $main, new Response()));

        // Sampling rate 0: neither the sub-request nor the main request is recorded.
        $sampled = $this->createSubscriber(new PerformanceDataCollector(), trackSubRequests: true, samplingRate: 0.0);
        $sampled->onKernelRequest($this->event($main, HttpKernelInterface::MAIN_REQUEST));
        $sampled->onKernelRequest($this->event($fragment, HttpKernelInterface::SUB_REQUEST));
        $sampled->onKernelResponse(new ResponseEvent($this->kernel, $fragment, HttpKernelInterface::SUB_REQUEST, new Response()));
        $sampled->onKernelTerminate(new TerminateEvent($this->kernel, $main, new Response()));
    }

    public function testResetClearsPerRequestState(): void
    {
        $subscriber = $this->createSubscriber(new PerformanceDataCollector(), trackSubRequests: true);

        $main     = $this->request('main_route');
        $fragment = $this->request('fragment_route');
        $subscriber->onKernelRequest($this->event($main, HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onKernelRequest($this->event($fragment, HttpKernelInterface::SUB_REQUEST));
        $subscriber->onKernelResponse(new ResponseEvent($this->kernel, $fragment, HttpKernelInterface::SUB_REQUEST, new Response()));

        $subscriber->reset();

        foreach (['startTime', 'startMemory', 'routeName', 'routeParams', 'requestId'] as $property) {
            $this->assertNull((new ReflectionProperty(PerformanceMetricsSubscriber::class, $property))->getValue($subscriber), $property);
        }
        $this->assertSame([], (new ReflectionProperty(PerformanceMetricsSubscriber::class, 'pendingSubRequestMetrics'))->getValue($subscriber));
        $this->assertCount(0, (new ReflectionProperty(PerformanceMetricsSubscriber::class, 'subRequestStates'))->getValue($subscriber));
    }

    private function createSubscriber(
        PerformanceDataCollector $collector,
        bool $trackSubRequests = false,
        bool $trackQueries = false,
        ?QueryTrackingCounters $counters = null,
        array $ignoreRoutes = [],
        bool $enabled = true,
        bool $trackRequestTime = true,
        float $samplingRate = 1.0,
    ): PerformanceMetricsSubscriber {
        return new PerformanceMetricsSubscriber(
            $this->metricsService,
            $collector,
            $enabled,
            ['test'],
            $ignoreRoutes,
            $trackQueries,
            $trackRequestTime,
            $trackSubRequests,
            false,
            $samplingRate,
            [200, 404, 500, 503],
            false,
            queryCounters: $counters,
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function request(string $route, array $params = []): Request
    {
        $request = Request::create('/' . $route);
        $request->server->set('APP_ENV', 'test');
        $request->attributes->set('_route', $route);
        $request->attributes->set('_route_params', $params);

        return $request;
    }

    private function event(Request $request, int $type): RequestEvent
    {
        return new RequestEvent($this->kernel, $request, $type);
    }

    private function runQueries(QueryTrackingCounters $counters, int $count): void
    {
        $connection = new stdClass();
        for ($i = 0; $i < $count; ++$i) {
            $id = $counters->nextQueryId($connection);
            $counters->startQuery($id);
            $counters->stopQuery($id);
        }
    }
}
