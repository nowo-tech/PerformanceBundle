<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class PerformanceMetricsServiceTest extends TestCase
{
    private PerformanceMetricsService $service;
    private ManagerRegistry|MockObject $registry;
    private EntityManagerInterface|MockObject $entityManager;
    private RouteDataRepository|MockObject $repository;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(RouteDataRepository::class);

        $this->registry
            ->method('getManager')
            ->with('default')
            ->willReturn($this->entityManager);

        $this->entityManager
            ->method('getRepository')
            ->with(RouteData::class)
            ->willReturn($this->repository);

        $this->service = new PerformanceMetricsService($this->registry, 'default');
    }

    public function testRecordMetricsCreatesNewRouteData(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(RouteData::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            ['id' => 123],
            null,
            'GET'
        );
    }

    public function testRecordMetricsUpdatesExistingRouteData(): void
    {
        $existingRoute = new RouteData();
        $existingRoute->setName('app_home');
        $existingRoute->setEnv('dev');
        $existingRoute->setRequestTime(0.3);
        $existingRoute->setTotalQueries(5);

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($existingRoute);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Update with worse metrics (higher time, more queries)
        $this->service->recordMetrics(
            'app_home',
            'dev',
            0.8, // Worse (higher)
            15,  // Worse (more)
            0.4,
            ['id' => 456]
        );

        $this->assertSame(0.8, $existingRoute->getRequestTime());
        $this->assertSame(15, $existingRoute->getTotalQueries());
        $this->assertSame(0.4, $existingRoute->getQueryTime());
        $this->assertSame(['id' => 456], $existingRoute->getParams());
    }

    public function testRecordMetricsDoesNotUpdateWithBetterMetrics(): void
    {
        $existingRoute = new RouteData();
        $existingRoute->setName('app_home');
        $existingRoute->setEnv('dev');
        $existingRoute->setRequestTime(0.5);
        $existingRoute->setTotalQueries(10);

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($existingRoute);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Update with better metrics (should not update)
        $this->service->recordMetrics(
            'app_home',
            'dev',
            0.3, // Better (lower)
            5,   // Better (fewer)
            0.1,
            null,
            null,
            'GET'
        );

        // Should remain unchanged
        $this->assertSame(0.5, $existingRoute->getRequestTime());
        $this->assertSame(10, $existingRoute->getTotalQueries());
    }

    public function testRecordMetricsWithNullValues(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(RouteData::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->recordMetrics(
            'app_home',
            'dev',
            null,
            null,
            null,
            null,
            null,
            null
        );
    }

    public function testGetRouteData(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        $result = $this->service->getRouteData('app_home', 'dev');

        $this->assertSame($routeData, $result);
    }

    public function testGetRoutesByEnvironment(): void
    {
        $routes = [
            new RouteData(),
            new RouteData(),
        ];

        $this->repository
            ->expects($this->once())
            ->method('findByEnvironment')
            ->with('dev')
            ->willReturn($routes);

        $result = $this->service->getRoutesByEnvironment('dev');

        $this->assertSame($routes, $result);
    }

    public function testGetWorstPerformingRoutes(): void
    {
        $routes = [
            new RouteData(),
            new RouteData(),
        ];

        $this->repository
            ->expects($this->once())
            ->method('findWorstPerforming')
            ->with('dev', 10)
            ->willReturn($routes);

        $result = $this->service->getWorstPerformingRoutes('dev', 10);

        $this->assertSame($routes, $result);
    }

    public function testGetWorstPerformingRoutesWithCustomLimit(): void
    {
        $routes = [new RouteData()];

        $this->repository
            ->expects($this->once())
            ->method('findWorstPerforming')
            ->with('prod', 5)
            ->willReturn($routes);

        $result = $this->service->getWorstPerformingRoutes('prod', 5);

        $this->assertSame($routes, $result);
    }

    public function testSetCacheService(): void
    {
        $cacheService = $this->createMock(\Nowo\PerformanceBundle\Service\PerformanceCacheService::class);
        
        $this->service->setCacheService($cacheService);
        
        // Test that cache service is set (we can't directly verify it, but we can test it's used in recordMetrics)
        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(RouteData::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $cacheService
            ->expects($this->once())
            ->method('invalidateStatistics')
            ->with('dev');

        $this->service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2, null, null, 'GET');
    }

    public function testRecordMetricsWithException(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(RouteData::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
            ->willThrowException(new \Exception('Database error'));

        // Should not throw exception
        $this->service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2, null, null, 'POST');
    }

    public function testRecordMetricsWithHttpMethod(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($routeData) {
                return $routeData instanceof RouteData && $routeData->getHttpMethod() === 'PUT';
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'PUT'
        );
    }

    public function testRecordMetricsWithMemoryUsage(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($routeData) {
                return $routeData instanceof RouteData && $routeData->getMemoryUsage() === 1048576;
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            1048576, // 1MB
            'DELETE'
        );
    }

    public function testRecordMetricsUpdatesMemoryUsageWhenWorse(): void
    {
        $existingRoute = new RouteData();
        $existingRoute->setName('app_home');
        $existingRoute->setEnv('dev');
        $existingRoute->setMemoryUsage(512000); // 512KB

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($existingRoute);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Update with worse memory usage (higher)
        $this->service->recordMetrics(
            'app_home',
            'dev',
            0.8, // Worse (higher)
            15,  // Worse (more)
            0.4,
            null,
            1048576, // 1MB - worse (higher)
            'PATCH'
        );

        $this->assertSame(1048576, $existingRoute->getMemoryUsage());
    }

    public function testGetRepository(): void
    {
        $repository = $this->service->getRepository();
        $this->assertSame($this->repository, $repository);
    }
}
