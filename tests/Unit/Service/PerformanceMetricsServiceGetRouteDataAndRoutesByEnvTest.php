<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for PerformanceMetricsService::getRouteData() and getRoutesByEnvironment().
 */
final class PerformanceMetricsServiceGetRouteDataAndRoutesByEnvTest extends TestCase
{
    private PerformanceMetricsService $service;
    private ManagerRegistry|MockObject $registry;
    private EntityManagerInterface|MockObject $entityManager;
    private RouteDataRepository|MockObject $repository;
    private RouteDataRecordRepository|MockObject $recordRepository;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(RouteDataRepository::class);
        $this->recordRepository = $this->createMock(RouteDataRecordRepository::class);

        $this->registry
            ->method('getManager')
            ->with('default')
            ->willReturn($this->entityManager);

        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function (string $class): RouteDataRepository|RouteDataRecordRepository {
                return $class === RouteData::class ? $this->repository : $this->recordRepository;
            });

        $this->service = new PerformanceMetricsService($this->registry, 'default');
    }

    public function testGetRouteDataReturnsRouteWhenFound(): void
    {
        $route = new RouteData();
        $route->setName('app_home')->setEnv('dev');

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($route);

        $result = $this->service->getRouteData('app_home', 'dev');

        $this->assertSame($route, $result);
    }

    public function testGetRouteDataReturnsNullWhenNotFound(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('missing_route', 'prod')
            ->willReturn(null);

        $result = $this->service->getRouteData('missing_route', 'prod');

        $this->assertNull($result);
    }

    public function testGetRoutesByEnvironmentReturnsArray(): void
    {
        $r1 = new RouteData();
        $r1->setName('r1')->setEnv('dev');
        $r2 = new RouteData();
        $r2->setName('r2')->setEnv('dev');

        $this->repository
            ->expects($this->once())
            ->method('findByEnvironment')
            ->with('dev')
            ->willReturn([$r1, $r2]);

        $result = $this->service->getRoutesByEnvironment('dev');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(RouteData::class, $result);
        $this->assertSame($r1, $result[0]);
        $this->assertSame($r2, $result[1]);
    }

    public function testGetRoutesByEnvironmentReturnsEmptyArrayWhenNoRoutes(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByEnvironment')
            ->with('prod')
            ->willReturn([]);

        $result = $this->service->getRoutesByEnvironment('prod');

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGetRoutesWithAggregatesReturnsEmptyWhenNoRoutes(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByEnvironment')
            ->with('dev')
            ->willReturn([]);

        $this->recordRepository->expects($this->never())->method('getAggregatesForRouteDataIds');

        $result = $this->service->getRoutesWithAggregates('dev');

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGetRoutesWithAggregatesReturnsEmptyWhenAllRouteIdsNull(): void
    {
        $r = new RouteData();
        $r->setName('r1')->setEnv('dev');
        $this->assertNull($r->getId());

        $this->repository
            ->expects($this->once())
            ->method('findByEnvironment')
            ->with('dev')
            ->willReturn([$r]);

        $this->recordRepository->expects($this->never())->method('getAggregatesForRouteDataIds');

        $result = $this->service->getRoutesWithAggregates('dev');

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGetWorstPerformingRoutesDelegatesToRepository(): void
    {
        $r1 = new RouteData();
        $r1->setName('slow')->setEnv('prod');

        $this->repository
            ->expects($this->once())
            ->method('findWorstPerforming')
            ->with('prod', 5)
            ->willReturn([$r1]);

        $result = $this->service->getWorstPerformingRoutes('prod', 5);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame($r1, $result[0]);
    }

    public function testGetRepositoryReturnsRepository(): void
    {
        $this->assertSame($this->repository, $this->service->getRepository());
    }
}
