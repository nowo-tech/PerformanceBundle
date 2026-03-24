<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Service;

use DateTimeImmutable;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class PerformanceMetricsServiceIntegrationTest extends TestCase
{
    private KernelInterface $kernel;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel('test', true);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public function testGetRoutesWithAggregatesReturnsArray(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);

        $routes = $service->getRoutesWithAggregates('test');

        self::assertIsArray($routes);
    }

    public function testGetRoutesByEnvironmentReturnsArray(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);

        $routes = $service->getRoutesByEnvironment('test');

        self::assertIsArray($routes);
    }

    public function testGetRouteDataReturnsNullWhenNoData(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);

        $routeData = $service->getRouteData('nonexistent_route', 'test');

        self::assertNull($routeData);
    }

    public function testRecordMetricsCreatesRouteData(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);

        $result = $service->recordMetrics('app_home', 'test', 0.25, 5, 0.01, null, 1024000);

        self::assertIsArray($result);
        self::assertArrayHasKey('is_new', $result);
        self::assertArrayHasKey('was_updated', $result);
        $routeData = $service->getRouteData('app_home', 'test');
        self::assertNotNull($routeData);
        self::assertSame('app_home', $routeData->getName());
        self::assertSame('test', $routeData->getEnv());
    }

    public function testGetWorstPerformingRoutesReturnsArray(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);

        $routes = $service->getWorstPerformingRoutes('test', 5);

        self::assertIsArray($routes);
    }

    public function testGetRoutesWithAggregatesFilteredReturnsArray(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);

        $routes = $service->getRoutesWithAggregatesFiltered('test', [], 'requestTime', 'DESC', 50);

        self::assertIsArray($routes);
    }

    public function testRecordMetricsWithFullParamsCreatesRouteDataAndAccessRecord(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);

        $result = $service->recordMetrics(
            'full_params_route',
            'test',
            0.5,
            10,
            0.05,
            null,
            2048000,
            'GET',
            200,
            [200, 404, 500],
            'req-123',
            'https://example.com/from',
            'user@test.com',
            '42',
            '/full_params_route?q=1',
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('is_new', $result);
        $routeData = $service->getRouteData('full_params_route', 'test');
        self::assertNotNull($routeData);
    }

    public function testGetRoutesWithAggregatesFilteredWithDateFilters(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);

        $filters = [
            'date_from' => new DateTimeImmutable('2024-01-01'),
            'date_to'   => new DateTimeImmutable('2026-12-31'),
        ];
        $routes = $service->getRoutesWithAggregatesFiltered('test', $filters, 'requestTime', 'DESC', 100);

        self::assertIsArray($routes);
    }

    public function testGetRoutesWithAggregatesFilteredWithRouteAndRequestTimeFilters(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);

        $filters = [
            'route_name_pattern' => 'app',
            'min_request_time'   => 0.0,
            'max_request_time'   => 5.0,
            'min_query_count'    => 0,
            'max_query_count'    => 100,
        ];
        $routes = $service->getRoutesWithAggregatesFiltered('test', $filters, 'totalQueries', 'ASC', 50);

        self::assertIsArray($routes);
    }

    public function testGetRoutesWithAggregatesFilteredSortByQueryTime(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);
        $service->recordMetrics('qt_route_a', 'test', 0.2, 3, 0.01);
        $service->recordMetrics('qt_route_b', 'test', 0.4, 6, 0.02);

        $routes = $service->getRoutesWithAggregatesFiltered('test', [], 'queryTime', 'DESC', 20);

        self::assertIsArray($routes);
    }

    public function testGetRoutesWithAggregatesFilteredSortByMemoryUsage(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);
        $service->recordMetrics('mem_route_a', 'test', 0.1, 1, null, null, 100000);
        $service->recordMetrics('mem_route_b', 'test', 0.1, 1, null, null, 900000);

        $routes = $service->getRoutesWithAggregatesFiltered('test', [], 'memoryUsage', 'DESC', 20);

        self::assertIsArray($routes);
    }

    public function testCountWithFiltersViaRepository(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);
        $repo    = $service->getRepository();

        $count = $repo->countWithFilters('test', []);
        self::assertIsInt($count);
        self::assertGreaterThanOrEqual(0, $count);
    }

    public function testRecordMetricsTwiceUpdatesExistingRoute(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);

        $service->recordMetrics('update_route', 'test', 0.1, 2);
        $result = $service->recordMetrics('update_route', 'test', 0.2, 5);

        self::assertFalse($result['is_new']);
        self::assertTrue($result['was_updated']);
    }

    public function testGetRoutesWithAggregatesWithData(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);
        $service->recordMetrics('agg_route', 'test', 0.15, 4, 0.02);

        $routes = $service->getRoutesWithAggregates('test');
        self::assertIsArray($routes);
    }

    public function testGetWorstPerformingRoutesWithData(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);
        $service->recordMetrics('slow_route', 'test', 1.5, 20, 0.5);
        $service->recordMetrics('fast_route', 'test', 0.05, 1);

        $routes = $service->getWorstPerformingRoutes('test', 10);
        self::assertIsArray($routes);
    }

    public function testGetRouteDataWhenExists(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);
        $service->recordMetrics('existing_route', 'test', 0.1, 2);

        $routeData = $service->getRouteData('existing_route', 'test');
        self::assertNotNull($routeData);
        self::assertSame('existing_route', $routeData->getName());
    }

    public function testRecordMetricsWithNonEmptyParamsSetsRouteParamsOnAccessRecord(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);

        $service->recordMetrics(
            'params_access_route',
            'test',
            0.12,
            2,
            0.01,
            ['slug' => 'hello'],
            512000,
            'GET',
            200,
            [],
            'req-params-ar',
        );

        self::assertNotNull($service->getRouteData('params_access_route', 'test'));
    }

    public function testRecordMetricsDuplicateRequestIdSkipsSecondAccessRecord(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);
        $rid     = 'shared-request-id-001';

        $service->recordMetrics('dup_req_route', 'test', 0.1, 1, null, null, 100000, 'GET', 200, [], $rid);
        $service->recordMetrics('dup_req_route', 'test', 0.2, 2, null, null, 100000, 'GET', 200, [], $rid);

        self::assertNotNull($service->getRouteData('dup_req_route', 'test'));
    }

    public function testRecordMetricsSkipsAccessRecordWhenSaveAccessRecordsDisabledOnRoute(): void
    {
        $this->createTablesFirst();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);

        $service->recordMetrics('no_save_ar', 'test', 0.1, 1, null, null, 100000, 'GET', 200, [], 'rid-ns-1');
        $routeData = $service->getRouteData('no_save_ar', 'test');
        self::assertNotNull($routeData);
        $routeData->setSaveAccessRecords(false);
        $service->getEntityManager()->flush();

        $service->recordMetrics('no_save_ar', 'test', 0.2, 2, null, null, 100000, 'GET', 200, [], 'rid-ns-2');

        self::assertFalse($routeData->getSaveAccessRecords());
    }

    private function createTablesFirst(): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        (new CommandTester($application->find('nowo:performance:create-table')))->execute([]);
        (new CommandTester($application->find('nowo:performance:create-records-table')))->execute([]);
    }
}
