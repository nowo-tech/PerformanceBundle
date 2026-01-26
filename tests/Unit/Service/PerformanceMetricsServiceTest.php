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
            ['id' => 123]
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
            0.1
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
}
