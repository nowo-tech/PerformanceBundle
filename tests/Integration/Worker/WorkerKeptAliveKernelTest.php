<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Worker;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function gc_collect_cycles;
use function memory_get_usage;
use function sprintf;
use function str_contains;

/**
 * REQ-RUNTIME-002: ONE booted production-mode kernel (`debug` false, SQLite in memory), `services_resetter` / `kernel.reset` never called (FrankenPHP
 * worker, kernel reset false). N iterations alternate two users through `http_kernel` `handle()` + `terminate()`: each
 * one opens the dashboard filtered by its own route name (and sends its own `Referer` / client IP). Every request is
 * also tracked by the bundle itself (`kernel.terminate` writes a `RouteDataRecord` through the private entity
 * manager). A debug kernel is not used on purpose: Twig's debug profiler keeps one profile per render for the life of the
 * process, which is a development-only host setting and not bundle state. Each response must show only its own route filter, and memory growth after warm-up must stay below 4 MiB.
 */
final class WorkerKeptAliveKernelTest extends TestCase
{
    private const ITERATIONS = 1200;
    private const WARM_UP    = 100;
    private const MARGIN     = 4 * 1024 * 1024;

    private ?TestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testAlternatingUsersInOneKernelLeakNothingAndKeepMemoryFlat(): void
    {
        $this->kernel = new TestKernel('test', false);
        $this->kernel->boot();

        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        (new CommandTester($app->find('nowo:performance:create-table')))->execute([]);
        (new CommandTester($app->find('nowo:performance:create-records-table')))->execute([]);

        $metrics = $this->kernel->getContainer()->get(PerformanceMetricsService::class);
        self::assertInstanceOf(PerformanceMetricsService::class, $metrics);
        $metrics->recordMetrics('worker_route_alice', 'test', 0.1, 3, 0.02);
        $metrics->recordMetrics('worker_route_bob', 'test', 0.2, 5, 0.04);

        $ids = [];
        foreach (['worker_route_alice', 'worker_route_bob'] as $name) {
            $row = $metrics->getRepository()->findOneBy(['name' => $name, 'env' => 'test']);
            self::assertInstanceOf(RouteData::class, $row);
            $ids[$name] = (int) $row->getId();
        }

        $baseline = 0;
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            if ($i === self::WARM_UP) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }

            [$mine, $other, $ip] = $i % 2 === 0
                ? ['worker_route_alice', 'worker_route_bob', '203.0.113.1']
                : ['worker_route_bob', 'worker_route_alice', '203.0.113.2'];

            $request = Request::create('/performance/?env=test&route=' . $mine, 'GET', [], [], [], ['REMOTE_ADDR' => $ip]);
            $request->headers->set('Referer', 'https://example.test/' . $mine);

            $response = $this->kernel->handle($request);
            $content  = (string) $response->getContent();
            $context  = sprintf('iteration %d', $i);

            self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $context);
            self::assertTrue(str_contains($content, $mine), $context . ': own route filter missing');
            self::assertTrue(str_contains($content, 'performance-review-form-' . $ids[$mine] . '"'), $context . ': own record row missing');
            self::assertFalse(str_contains($content, 'performance-review-form-' . $ids[$other] . '"'), $context . ': other user record row present');
            $this->kernel->terminate($request, $response);
        }

        gc_collect_cycles();
        $growth = memory_get_usage() - $baseline;
        self::assertLessThan(self::MARGIN, $growth, sprintf('memory grew by %d bytes over %d iterations', $growth, self::ITERATIONS - self::WARM_UP));

        // The seeded rows are still the same two rows: the tracked dashboard requests wrote their own route.
        foreach ($ids as $name => $id) {
            $row = $metrics->getRepository()->findOneBy(['name' => $name, 'env' => 'test']);
            self::assertInstanceOf(RouteData::class, $row);
            self::assertSame($id, $row->getId());
        }
    }
}
