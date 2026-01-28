<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for PerformanceController helper methods (buildFiltersFromRequest, getChartData, getDriverName).
 */
final class PerformanceControllerHelperMethodsAdvancedTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;
    private RouteDataRepository|MockObject $repository;
    private PerformanceController $controller;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->repository = $this->createMock(RouteDataRepository::class);
        $this->metricsService->method('getRepository')->willReturn($this->repository);

        $this->controller = new PerformanceController(
            $this->metricsService,
            null,
            true,
            [],
            'bootstrap',
            null,
            null,
            null,
            false,
            false,
            null,
            0.5,
            1.0,
            20,
            50,
            20.0,
            50.0,
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            0,
            [200, 404, 500, 503],
            null,
            false,
            true,
            ['dev', 'test'],
            'default',
            true,
            true,
            false,
            [],
            false,
            1.0,
            true,
        );
    }

    /**
     * Helper method to call private methods using reflection.
     */
    private function callPrivateMethod(string $methodName, mixed ...$args): mixed
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($this->controller, $args);
    }

    public function testBuildFiltersFromRequestWithNoFilters(): void
    {
        $request = new Request();
        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testBuildFiltersFromRequestWithRouteFilter(): void
    {
        $request = new Request(['route' => 'app_home']);
        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        $this->assertArrayHasKey('route_name_pattern', $result);
        $this->assertEquals('app_home', $result['route_name_pattern']);
    }

    public function testBuildFiltersFromRequestWithRequestTimeFilters(): void
    {
        $request = new Request([
            'min_request_time' => '0.5',
            'max_request_time' => '2.0',
        ]);
        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        $this->assertArrayHasKey('min_request_time', $result);
        $this->assertArrayHasKey('max_request_time', $result);
        $this->assertEquals(0.5, $result['min_request_time']);
        $this->assertEquals(2.0, $result['max_request_time']);
    }

    public function testBuildFiltersFromRequestWithQueryCountFilters(): void
    {
        $request = new Request([
            'min_query_count' => '10',
            'max_query_count' => '50',
        ]);
        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        $this->assertArrayHasKey('min_query_count', $result);
        $this->assertArrayHasKey('max_query_count', $result);
        $this->assertEquals(10, $result['min_query_count']);
        $this->assertEquals(50, $result['max_query_count']);
    }

    public function testBuildFiltersFromRequestWithValidDates(): void
    {
        $request = new Request([
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);
        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        $this->assertArrayHasKey('date_from', $result);
        $this->assertArrayHasKey('date_to', $result);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result['date_from']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result['date_to']);
    }

    public function testBuildFiltersFromRequestWithInvalidDates(): void
    {
        $request = new Request([
            'date_from' => 'invalid-date',
            'date_to' => 'also-invalid',
        ]);
        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        // Invalid dates should be ignored
        $this->assertArrayNotHasKey('date_from', $result);
        $this->assertArrayNotHasKey('date_to', $result);
    }

    public function testBuildFiltersFromRequestWithAllFilters(): void
    {
        $request = new Request([
            'route' => 'app_home',
            'min_request_time' => '0.5',
            'max_request_time' => '2.0',
            'min_query_count' => '10',
            'max_query_count' => '50',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);
        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        $this->assertArrayHasKey('route_name_pattern', $result);
        $this->assertArrayHasKey('min_request_time', $result);
        $this->assertArrayHasKey('max_request_time', $result);
        $this->assertArrayHasKey('min_query_count', $result);
        $this->assertArrayHasKey('max_query_count', $result);
        $this->assertArrayHasKey('date_from', $result);
        $this->assertArrayHasKey('date_to', $result);
    }

    public function testGetChartDataWithEmptyRoutes(): void
    {
        $this->repository->method('findWithFilters')->willReturn([]);

        $result = $this->callPrivateMethod('getChartData', 'dev', null, 7, 'requestTime');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertCount(7, $result['labels']); // 7 days
        $this->assertCount(2, $result['datasets']); // Average and Maximum
    }

    public function testGetChartDataWithRoutes(): void
    {
        $route1 = $this->createMock(RouteData::class);
        $route1->method('getRequestTime')->willReturn(0.5);
        $route1->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2026-01-27'));

        $route2 = $this->createMock(RouteData::class);
        $route2->method('getRequestTime')->willReturn(1.0);
        $route2->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2026-01-27'));

        $this->repository->method('findWithFilters')->willReturn([$route1, $route2]);

        $result = $this->callPrivateMethod('getChartData', 'dev', null, 7, 'requestTime');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertCount(7, $result['labels']);
        $this->assertCount(2, $result['datasets']);
    }

    public function testGetChartDataWithRouteName(): void
    {
        $this->repository->method('findWithFilters')->willReturn([]);

        $result = $this->callPrivateMethod('getChartData', 'dev', 'app_home', 7, 'requestTime');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
    }

    public function testGetChartDataWithDifferentMetrics(): void
    {
        $this->repository->method('findWithFilters')->willReturn([]);

        $metrics = ['requestTime', 'queryTime', 'totalQueries', 'memoryUsage'];
        foreach ($metrics as $metric) {
            $result = $this->callPrivateMethod('getChartData', 'dev', null, 7, $metric);
            $this->assertIsArray($result);
            $this->assertArrayHasKey('datasets', $result);
            $this->assertStringContainsString($metric, $result['datasets'][0]['label']);
        }
    }

    public function testGetChartDataWithMemoryUsageConversion(): void
    {
        $route = $this->createMock(RouteData::class);
        $route->method('getMemoryUsage')->willReturn(10485760); // 10 MB in bytes
        $route->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2026-01-27'));

        $this->repository->method('findWithFilters')->willReturn([$route]);

        $result = $this->callPrivateMethod('getChartData', 'dev', null, 7, 'memoryUsage');

        $this->assertIsArray($result);
        // Memory should be converted to MB
        $this->assertArrayHasKey('datasets', $result);
    }

    public function testGetDriverNameWithDirectGetName(): void
    {
        $driver = $this->createMock(\Doctrine\DBAL\Driver::class);
        $driver->method('getName')->willReturn('pdo_mysql');

        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->method('getDriver')->willReturn($driver);

        $result = $this->callPrivateMethod('getDriverName', $connection);

        $this->assertEquals('pdo_mysql', $result);
    }

    public function testGetDriverNameWithPlatformInference(): void
    {
        $driver = $this->createMock(\Doctrine\DBAL\Driver::class);
        $driver->method('getName')->willThrowException(new \Exception());

        $platform = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platformClass = new class() extends \Doctrine\DBAL\Platforms\AbstractPlatform {
            public function getName(): string
            {
                return 'mysql';
            }
        };

        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->method('getDriver')->willReturn($driver);
        $connection->method('getDatabasePlatform')->willReturn($platformClass);

        $result = $this->callPrivateMethod('getDriverName', $connection);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testGetDriverNameWithUnknownDriver(): void
    {
        $driver = $this->createMock(\Doctrine\DBAL\Driver::class);
        $driver->method('getName')->willThrowException(new \Exception());

        $platform = new class() extends \Doctrine\DBAL\Platforms\AbstractPlatform {
            public function getName(): string
            {
                return 'unknown';
            }
        };

        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->method('getDriver')->willReturn($driver);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $result = $this->callPrivateMethod('getDriverName', $connection);

        $this->assertEquals('unknown', $result);
    }
}
