<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionMethod;

/**
 * Advanced tests for PerformanceController::getChartData() method edge cases.
 */
final class PerformanceControllerGetChartDataAdvancedTest extends TestCase
{
    private PerformanceController $controller;
    private PerformanceMetricsService|MockObject $metricsService;
    private RouteDataRepository|MockObject $repository;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->repository = $this->createMock(RouteDataRepository::class);
        
        $this->metricsService
            ->method('getRepository')
            ->willReturn($this->repository);

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
            1.0,
            [200, 404, 500, 503],
            true,
            null,
            null
        );
    }

    public function testGetChartDataWithEmptyRoutes(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getChartData');
        $method->setAccessible(true);

        $this->repository
            ->method('findWithFilters')
            ->willReturn([]);

        $result = $method->invoke($this->controller, 'dev', null, 7, 'requestTime');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertCount(7, $result['labels']); // 7 days
        $this->assertCount(2, $result['datasets']); // Average and Maximum
    }

    public function testGetChartDataWithNullRouteName(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getChartData');
        $method->setAccessible(true);

        $this->repository
            ->method('findWithFilters')
            ->willReturn([]);

        $result = $method->invoke($this->controller, 'dev', null, 7, 'requestTime');

        $this->assertIsArray($result);
    }

    public function testGetChartDataWithEmptyRouteName(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getChartData');
        $method->setAccessible(true);

        $this->repository
            ->method('findWithFilters')
            ->willReturn([]);

        $result = $method->invoke($this->controller, 'dev', '', 7, 'requestTime');

        $this->assertIsArray($result);
    }

    public function testGetChartDataWithRouteName(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getChartData');
        $method->setAccessible(true);

        $this->repository
            ->method('findWithFilters')
            ->with(
                'dev',
                $this->callback(function ($filters) {
                    return isset($filters['route_name_pattern']) && $filters['route_name_pattern'] === 'app_home';
                }),
                'createdAt',
                'ASC',
                null
            )
            ->willReturn([]);

        $result = $method->invoke($this->controller, 'dev', 'app_home', 7, 'requestTime');

        $this->assertIsArray($result);
    }

    public function testGetChartDataWithZeroDays(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getChartData');
        $method->setAccessible(true);

        $this->repository
            ->method('findWithFilters')
            ->willReturn([]);

        $result = $method->invoke($this->controller, 'dev', null, 0, 'requestTime');

        $this->assertIsArray($result);
        $this->assertCount(1, $result['labels']); // Today only
    }

    public function testGetChartDataWithNegativeDays(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getChartData');
        $method->setAccessible(true);

        $this->repository
            ->method('findWithFilters')
            ->willReturn([]);

        $result = $method->invoke($this->controller, 'dev', null, -5, 'requestTime');

        $this->assertIsArray($result);
    }

    public function testGetChartDataWithLargeDays(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getChartData');
        $method->setAccessible(true);

        $this->repository
            ->method('findWithFilters')
            ->willReturn([]);

        $result = $method->invoke($this->controller, 'dev', null, 365, 'requestTime');

        $this->assertIsArray($result);
        $this->assertCount(365, $result['labels']);
    }

    public function testGetChartDataWithRoutesHavingNullTimestamps(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getChartData');
        $method->setAccessible(true);

        $route = new RouteData();
        $route->setName('app_home');
        $route->setCreatedAt(null);
        $route->setUpdatedAt(null);
        $route->setRequestTime(0.5);

        $this->repository
            ->method('findWithFilters')
            ->willReturn([$route]);

        $result = $method->invoke($this->controller, 'dev', null, 7, 'requestTime');

        $this->assertIsArray($result);
        // Should handle null timestamps gracefully
    }

    public function testGetChartDataWithRoutesHavingNullValues(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getChartData');
        $method->setAccessible(true);

        $route = new RouteData();
        $route->setName('app_home');
        $route->setRequestTime(null);
        $route->setQueryTime(null);
        $route->setTotalQueries(null);
        $route->setMemoryUsage(null);

        $this->repository
            ->method('findWithFilters')
            ->willReturn([$route]);

        $result = $method->invoke($this->controller, 'dev', null, 7, 'requestTime');

        $this->assertIsArray($result);
        // Should handle null values gracefully (defaults to 0.0)
    }

    public function testGetChartDataWithMemoryUsageMetric(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getChartData');
        $method->setAccessible(true);

        $route = new RouteData();
        $route->setName('app_home');
        $route->setMemoryUsage(1024 * 1024); // 1 MB

        $this->repository
            ->method('findWithFilters')
            ->willReturn([$route]);

        $result = $method->invoke($this->controller, 'dev', null, 7, 'memoryUsage');

        $this->assertIsArray($result);
        $this->assertStringContainsString('Memory Usage (MB)', $result['datasets'][0]['label']);
    }

    public function testGetChartDataWithUnknownMetric(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getChartData');
        $method->setAccessible(true);

        $route = new RouteData();
        $route->setName('app_home');
        $route->setRequestTime(0.5);

        $this->repository
            ->method('findWithFilters')
            ->willReturn([$route]);

        $result = $method->invoke($this->controller, 'dev', null, 7, 'unknown_metric');

        $this->assertIsArray($result);
        // Should default to requestTime
        $this->assertStringContainsString('Request Time (s)', $result['datasets'][0]['label']);
    }

    public function testGetChartDataWithMultipleRoutesSameDate(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getChartData');
        $method->setAccessible(true);

        $date = new \DateTimeImmutable('2026-01-15');
        
        $route1 = new RouteData();
        $route1->setName('app_home');
        $route1->setCreatedAt($date);
        $route1->setRequestTime(0.5);

        $route2 = new RouteData();
        $route2->setName('app_about');
        $route2->setCreatedAt($date);
        $route2->setRequestTime(0.3);

        $this->repository
            ->method('findWithFilters')
            ->willReturn([$route1, $route2]);

        $result = $method->invoke($this->controller, 'dev', null, 7, 'requestTime');

        $this->assertIsArray($result);
        // Should calculate average for same date
    }

    public function testGetChartDataWithRoutesAcrossMultipleDates(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getChartData');
        $method->setAccessible(true);

        $route1 = new RouteData();
        $route1->setName('app_home');
        $route1->setCreatedAt(new \DateTimeImmutable('2026-01-15'));
        $route1->setRequestTime(0.5);

        $route2 = new RouteData();
        $route2->setName('app_about');
        $route2->setCreatedAt(new \DateTimeImmutable('2026-01-16'));
        $route2->setRequestTime(0.3);

        $this->repository
            ->method('findWithFilters')
            ->willReturn([$route1, $route2]);

        $result = $method->invoke($this->controller, 'dev', null, 7, 'requestTime');

        $this->assertIsArray($result);
        $this->assertCount(7, $result['labels']);
    }

    public function testGetChartDataWithZeroValues(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getChartData');
        $method->setAccessible(true);

        $route = new RouteData();
        $route->setName('app_home');
        $route->setRequestTime(0.0);
        $route->setQueryTime(0.0);
        $route->setTotalQueries(0);
        $route->setMemoryUsage(0);

        $this->repository
            ->method('findWithFilters')
            ->willReturn([$route]);

        $result = $method->invoke($this->controller, 'dev', null, 7, 'requestTime');

        $this->assertIsArray($result);
    }

    public function testGetChartDataReturnsCorrectDatasetStructure(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getChartData');
        $method->setAccessible(true);

        $this->repository
            ->method('findWithFilters')
            ->willReturn([]);

        $result = $method->invoke($this->controller, 'dev', null, 7, 'requestTime');

        $this->assertCount(2, $result['datasets']);
        
        $avgDataset = $result['datasets'][0];
        $this->assertArrayHasKey('label', $avgDataset);
        $this->assertArrayHasKey('data', $avgDataset);
        $this->assertArrayHasKey('borderColor', $avgDataset);
        $this->assertArrayHasKey('backgroundColor', $avgDataset);
        $this->assertArrayHasKey('tension', $avgDataset);
        
        $maxDataset = $result['datasets'][1];
        $this->assertArrayHasKey('label', $maxDataset);
        $this->assertArrayHasKey('data', $maxDataset);
        $this->assertArrayHasKey('borderColor', $maxDataset);
        $this->assertArrayHasKey('backgroundColor', $maxDataset);
        $this->assertArrayHasKey('tension', $maxDataset);
    }

    public function testGetChartDataWithAllMetrics(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getChartData');
        $method->setAccessible(true);

        $metrics = ['requestTime', 'queryTime', 'totalQueries', 'memoryUsage'];

        foreach ($metrics as $metric) {
            $this->repository
                ->method('findWithFilters')
                ->willReturn([]);

            $result = $method->invoke($this->controller, 'dev', null, 7, $metric);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('labels', $result);
            $this->assertArrayHasKey('datasets', $result);
        }
    }
}
