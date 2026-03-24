<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Model\RouteDataWithAggregates;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Tests for PerformanceMetricsService::getRoutesWithAggregatesFiltered (sorting by memoryUsage and other metrics).
 */
final class PerformanceMetricsServiceGetRoutesWithAggregatesTest extends TestCase
{
    private PerformanceMetricsService $service;
    private MockObject $registry;
    private MockObject $entityManager;
    private MockObject $repository;
    private MockObject $recordRepository;

    protected function setUp(): void
    {
        $this->registry         = $this->createMock(ManagerRegistry::class);
        $this->entityManager    = $this->createMock(EntityManagerInterface::class);
        $this->repository       = $this->createMock(RouteDataRepository::class);
        $this->recordRepository = $this->createMock(RouteDataRecordRepository::class);

        $this->registry
            ->method('getManager')
            ->with('default')
            ->willReturn($this->entityManager);

        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(fn (string $class): RouteDataRepository|RouteDataRecordRepository => $class === RouteData::class ? $this->repository : $this->recordRepository);

        $this->service = new PerformanceMetricsService($this->registry, 'default');
    }

    public function testGetRoutesWithAggregatesFilteredSortByMemoryUsageDesc(): void
    {
        $route1 = new RouteData();
        $route1->setName('route_low');
        $route1->setEnv('dev');
        $ref1 = new ReflectionProperty(RouteData::class, 'id');
        $ref1->setValue($route1, 1);

        $route2 = new RouteData();
        $route2->setName('route_high');
        $route2->setEnv('dev');
        $ref2 = new ReflectionProperty(RouteData::class, 'id');
        $ref2->setValue($route2, 2);

        $route3 = new RouteData();
        $route3->setName('route_mid');
        $route3->setEnv('dev');
        $ref3 = new ReflectionProperty(RouteData::class, 'id');
        $ref3->setValue($route3, 3);

        $this->repository
            ->expects(self::once())
            ->method('findWithFilters')
            ->with('dev', [], 'lastAccessedAt', 'DESC', null)
            ->willReturn([$route1, $route2, $route3]);

        $this->recordRepository
            ->expects(self::once())
            ->method('getAggregatesForRouteDataIds')
            ->with([1, 2, 3])
            ->willReturn([
                1 => [
                    'request_time'  => 0.1,
                    'query_time'    => 0.05,
                    'total_queries' => 5,
                    'memory_usage'  => 100_000,
                    'access_count'  => 1,
                    'status_codes'  => [],
                ],
                2 => [
                    'request_time'  => 0.5,
                    'query_time'    => 0.2,
                    'total_queries' => 20,
                    'memory_usage'  => 50_000_000,
                    'access_count'  => 10,
                    'status_codes'  => [],
                ],
                3 => [
                    'request_time'  => 0.3,
                    'query_time'    => 0.1,
                    'total_queries' => 10,
                    'memory_usage'  => 5_000_000,
                    'access_count'  => 5,
                    'status_codes'  => [],
                ],
            ]);

        $result = $this->service->getRoutesWithAggregatesFiltered('dev', [], 'memoryUsage', 'DESC');

        self::assertCount(3, $result);
        self::assertContainsOnlyInstancesOf(RouteDataWithAggregates::class, $result);
        self::assertSame(50_000_000, $result[0]->getMemoryUsage());
        self::assertSame(5_000_000, $result[1]->getMemoryUsage());
        self::assertSame(100_000, $result[2]->getMemoryUsage());
    }

    public function testGetRoutesWithAggregatesFilteredSortByMemoryUsageAsc(): void
    {
        $route1 = new RouteData();
        $route1->setName('r1');
        $route1->setEnv('dev');
        $ref1 = new ReflectionProperty(RouteData::class, 'id');
        $ref1->setValue($route1, 1);

        $route2 = new RouteData();
        $route2->setName('r2');
        $route2->setEnv('dev');
        $ref2 = new ReflectionProperty(RouteData::class, 'id');
        $ref2->setValue($route2, 2);

        $this->repository
            ->method('findWithFilters')
            ->with('dev', [], 'lastAccessedAt', 'ASC', null)
            ->willReturn([$route1, $route2]);

        $this->recordRepository
            ->method('getAggregatesForRouteDataIds')
            ->with([1, 2])
            ->willReturn([
                1 => [
                    'request_time'  => null,
                    'query_time'    => null,
                    'total_queries' => null,
                    'memory_usage'  => 10_000_000,
                    'access_count'  => 1,
                    'status_codes'  => [],
                ],
                2 => [
                    'request_time'  => null,
                    'query_time'    => null,
                    'total_queries' => null,
                    'memory_usage'  => 1_000_000,
                    'access_count'  => 1,
                    'status_codes'  => [],
                ],
            ]);

        $result = $this->service->getRoutesWithAggregatesFiltered('dev', [], 'memoryUsage', 'ASC');

        self::assertCount(2, $result);
        self::assertSame(1_000_000, $result[0]->getMemoryUsage());
        self::assertSame(10_000_000, $result[1]->getMemoryUsage());
    }

    public function testGetRoutesWithAggregatesFilteredSortByMemoryUsageWithNullsTreatedAsZero(): void
    {
        $route1 = new RouteData();
        $route1->setName('r1');
        $route1->setEnv('dev');
        $ref1 = new ReflectionProperty(RouteData::class, 'id');
        $ref1->setValue($route1, 1);

        $route2 = new RouteData();
        $route2->setName('r2');
        $route2->setEnv('dev');
        $ref2 = new ReflectionProperty(RouteData::class, 'id');
        $ref2->setValue($route2, 2);

        $this->repository
            ->method('findWithFilters')
            ->willReturn([$route1, $route2]);

        $this->recordRepository
            ->method('getAggregatesForRouteDataIds')
            ->with([1, 2])
            ->willReturn([
                1 => [
                    'request_time'  => null,
                    'query_time'    => null,
                    'total_queries' => null,
                    'memory_usage'  => null,
                    'access_count'  => 1,
                    'status_codes'  => [],
                ],
                2 => [
                    'request_time'  => null,
                    'query_time'    => null,
                    'total_queries' => null,
                    'memory_usage'  => 100,
                    'access_count'  => 1,
                    'status_codes'  => [],
                ],
            ]);

        $result = $this->service->getRoutesWithAggregatesFiltered('dev', [], 'memoryUsage', 'DESC');

        self::assertCount(2, $result);
        self::assertSame(100, $result[0]->getMemoryUsage());
        self::assertNull($result[1]->getMemoryUsage());
    }

    public function testGetRepositoryReturnsRouteDataRepository(): void
    {
        $repo = $this->service->getRepository();
        self::assertSame($this->repository, $repo);
    }

    public function testGetRoutesWithAggregatesFilteredWithEmptyResult(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findWithFilters')
            ->with('dev', [], 'lastAccessedAt', 'DESC', null)
            ->willReturn([]);

        $this->recordRepository->expects(self::never())->method('getAggregatesForRouteDataIds');

        $result = $this->service->getRoutesWithAggregatesFiltered('dev', [], 'requestTime', 'DESC');

        self::assertSame([], $result);
    }

    /**
     * When all routes have getId() === null, $ids is empty and method returns [] (line 568).
     *
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::getRoutesWithAggregatesFiltered
     */
    public function testGetRoutesWithAggregatesFilteredReturnsEmptyWhenAllRouteIdsNull(): void
    {
        $r1 = new RouteData();
        $r1->setName('a')->setEnv('dev');
        $r2 = new RouteData();
        $r2->setName('b')->setEnv('dev');
        // neither has id set, so $ids = [] and we return [] at line 568

        $this->repository
            ->method('findWithFilters')
            ->with('dev', [], 'lastAccessedAt', 'DESC', null)
            ->willReturn([$r1, $r2]);

        $this->recordRepository->expects(self::never())->method('getAggregatesForRouteDataIds');

        $result = $this->service->getRoutesWithAggregatesFiltered('dev', [], 'requestTime', 'DESC');

        self::assertSame([], $result);
    }

    /**
     * When sortBy is not in entitySortFields and not a metric name, match uses default => 0 (lines 575, 593).
     *
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::getRoutesWithAggregatesFiltered
     */
    public function testGetRoutesWithAggregatesFilteredSortByUnknownUsesDefaultInMatch(): void
    {
        $route1 = new RouteData();
        $route1->setName('r1');
        $route1->setEnv('dev');
        $ref1 = new ReflectionProperty(RouteData::class, 'id');
        $ref1->setValue($route1, 1);

        $route2 = new RouteData();
        $route2->setName('r2');
        $route2->setEnv('dev');
        $ref2 = new ReflectionProperty(RouteData::class, 'id');
        $ref2->setValue($route2, 2);

        $this->repository->method('findWithFilters')->willReturn([$route1, $route2]);
        $this->recordRepository->method('getAggregatesForRouteDataIds')->willReturn([
            1 => [
                'request_time'  => null,
                'total_queries' => null,
                'query_time'    => null,
                'memory_usage'  => null,
                'access_count'  => 0,
                'status_codes'  => [],
            ],
            2 => [
                'request_time'  => null,
                'total_queries' => null,
                'query_time'    => null,
                'memory_usage'  => null,
                'access_count'  => 0,
                'status_codes'  => [],
            ],
        ]);

        $result = $this->service->getRoutesWithAggregatesFiltered('dev', [], 'unknownSortField', 'DESC');

        self::assertCount(2, $result);
    }

    public function testGetWorstPerformingRoutesDelegatesToRepository(): void
    {
        $r1 = new RouteData();
        $r1->setName('a');
        $r1->setEnv('prod');
        $r2 = new RouteData();
        $r2->setName('b');
        $r2->setEnv('prod');
        $routes = [$r1, $r2];

        $this->repository->expects(self::once())
            ->method('findWorstPerforming')
            ->with('prod', 20)
            ->willReturn($routes);

        $result = $this->service->getWorstPerformingRoutes('prod', 20);

        self::assertSame($routes, $result);
    }

    public function testGetRoutesWithAggregatesFilteredSortByAccessCountDesc(): void
    {
        $route1 = new RouteData();
        $route1->setName('r1');
        $route1->setEnv('dev');
        $ref1 = new ReflectionProperty(RouteData::class, 'id');
        $ref1->setValue($route1, 1);

        $route2 = new RouteData();
        $route2->setName('r2');
        $route2->setEnv('dev');
        $ref2 = new ReflectionProperty(RouteData::class, 'id');
        $ref2->setValue($route2, 2);

        $this->repository->method('findWithFilters')->willReturn([$route1, $route2]);
        $this->recordRepository->method('getAggregatesForRouteDataIds')->willReturn([
            1 => [
                'request_time'  => null,
                'query_time'    => null,
                'total_queries' => null,
                'memory_usage'  => null,
                'access_count'  => 5,
                'status_codes'  => [],
            ],
            2 => [
                'request_time'  => null,
                'query_time'    => null,
                'total_queries' => null,
                'memory_usage'  => null,
                'access_count'  => 20,
                'status_codes'  => [],
            ],
        ]);

        $result = $this->service->getRoutesWithAggregatesFiltered('dev', [], 'accessCount', 'DESC');

        self::assertCount(2, $result);
        self::assertSame(20, $result[0]->getAccessCount());
        self::assertSame(5, $result[1]->getAccessCount());
    }

    public function testGetRoutesWithAggregatesFilteredSortByRequestTimeAsc(): void
    {
        $route1 = new RouteData();
        $route1->setName('r1');
        $route1->setEnv('dev');
        $ref1 = new ReflectionProperty(RouteData::class, 'id');
        $ref1->setValue($route1, 1);

        $route2 = new RouteData();
        $route2->setName('r2');
        $route2->setEnv('dev');
        $ref2 = new ReflectionProperty(RouteData::class, 'id');
        $ref2->setValue($route2, 2);

        $this->repository->method('findWithFilters')->willReturn([$route1, $route2]);
        $this->recordRepository->method('getAggregatesForRouteDataIds')->willReturn([
            1 => [
                'request_time'  => 0.5,
                'query_time'    => null,
                'total_queries' => null,
                'memory_usage'  => null,
                'access_count'  => 1,
                'status_codes'  => [],
            ],
            2 => [
                'request_time'  => 0.1,
                'query_time'    => null,
                'total_queries' => null,
                'memory_usage'  => null,
                'access_count'  => 1,
                'status_codes'  => [],
            ],
        ]);

        $result = $this->service->getRoutesWithAggregatesFiltered('dev', [], 'requestTime', 'ASC');

        self::assertCount(2, $result);
        self::assertEqualsWithDelta(0.1, $result[0]->getRequestTime(), 0.001);
        self::assertEqualsWithDelta(0.5, $result[1]->getRequestTime(), 0.001);
    }

    public function testGetRoutesWithAggregatesFilteredSingleRoute(): void
    {
        $route = new RouteData();
        $route->setName('single');
        $route->setEnv('dev');
        $ref = new ReflectionProperty(RouteData::class, 'id');
        $ref->setValue($route, 1);

        $this->repository->method('findWithFilters')->willReturn([$route]);
        $this->recordRepository->method('getAggregatesForRouteDataIds')->willReturn([
            1 => [
                'request_time'  => 0.2,
                'query_time'    => 0.05,
                'total_queries' => 7,
                'memory_usage'  => 1024,
                'access_count'  => 1,
                'status_codes'  => [200 => 1],
            ],
        ]);

        $result = $this->service->getRoutesWithAggregatesFiltered('dev', [], 'requestTime', 'DESC', 10);

        self::assertCount(1, $result);
        self::assertInstanceOf(RouteDataWithAggregates::class, $result[0]);
        self::assertEqualsWithDelta(0.2, $result[0]->getRequestTime(), 0.001);
        self::assertSame(7, $result[0]->getTotalQueries());
        self::assertSame(1, $result[0]->getAccessCount());
        self::assertSame([200 => 1], $result[0]->getStatusCodes());
    }

    public function testGetRoutesWithAggregatesFilteredSortByTotalQueriesDesc(): void
    {
        $route1 = new RouteData();
        $route1->setName('r1');
        $route1->setEnv('dev');
        $ref1 = new ReflectionProperty(RouteData::class, 'id');
        $ref1->setValue($route1, 1);

        $route2 = new RouteData();
        $route2->setName('r2');
        $route2->setEnv('dev');
        $ref2 = new ReflectionProperty(RouteData::class, 'id');
        $ref2->setValue($route2, 2);

        $this->repository->method('findWithFilters')->willReturn([$route1, $route2]);
        $this->recordRepository->method('getAggregatesForRouteDataIds')->willReturn([
            1 => [
                'request_time'  => null,
                'query_time'    => null,
                'total_queries' => 5,
                'memory_usage'  => null,
                'access_count'  => 1,
                'status_codes'  => [],
            ],
            2 => [
                'request_time'  => null,
                'query_time'    => null,
                'total_queries' => 50,
                'memory_usage'  => null,
                'access_count'  => 1,
                'status_codes'  => [],
            ],
        ]);

        $result = $this->service->getRoutesWithAggregatesFiltered('dev', [], 'totalQueries', 'DESC');

        self::assertCount(2, $result);
        self::assertSame(50, $result[0]->getTotalQueries());
        self::assertSame(5, $result[1]->getTotalQueries());
    }

    /**
     * Sorting by queryTime exercises match arms for queryTime (lines 593–594, 601–602).
     */
    public function testGetRoutesWithAggregatesFilteredSortByQueryTimeDesc(): void
    {
        $route1 = new RouteData();
        $route1->setName('r1');
        $route1->setEnv('dev');
        (new ReflectionProperty(RouteData::class, 'id'))->setValue($route1, 1);

        $route2 = new RouteData();
        $route2->setName('r2');
        $route2->setEnv('dev');
        (new ReflectionProperty(RouteData::class, 'id'))->setValue($route2, 2);

        $this->repository->method('findWithFilters')->willReturn([$route1, $route2]);
        $this->recordRepository->method('getAggregatesForRouteDataIds')->willReturn([
            1 => [
                'request_time'  => null,
                'query_time'    => 0.9,
                'total_queries' => 1,
                'memory_usage'  => null,
                'access_count'  => 1,
                'status_codes'  => [],
            ],
            2 => [
                'request_time'  => null,
                'query_time'    => 0.1,
                'total_queries' => 1,
                'memory_usage'  => null,
                'access_count'  => 1,
                'status_codes'  => [],
            ],
        ]);

        $result = $this->service->getRoutesWithAggregatesFiltered('dev', [], 'queryTime', 'DESC');

        self::assertCount(2, $result);
        self::assertEqualsWithDelta(0.9, $result[0]->getQueryTime() ?? 0.0, 0.001);
        self::assertEqualsWithDelta(0.1, $result[1]->getQueryTime() ?? 0.0, 0.001);
    }

    /** Covers foreach branch when a route has null id (continue at line 575). */
    public function testGetRoutesWithAggregatesFilteredSkipsRoutesWithNullId(): void
    {
        $noId = new RouteData();
        $noId->setName('no_id');
        $noId->setEnv('dev');

        $withId = new RouteData();
        $withId->setName('with_id');
        $withId->setEnv('dev');
        (new ReflectionProperty(RouteData::class, 'id'))->setValue($withId, 10);

        $this->repository->method('findWithFilters')->willReturn([$noId, $withId]);
        $this->recordRepository->method('getAggregatesForRouteDataIds')->willReturn([
            10 => [
                'request_time'  => 0.2,
                'query_time'    => null,
                'total_queries' => 2,
                'memory_usage'  => null,
                'access_count'  => 1,
                'status_codes'  => [],
            ],
        ]);

        $result = $this->service->getRoutesWithAggregatesFiltered('dev', [], 'requestTime', 'DESC');

        self::assertCount(1, $result);
        self::assertSame('with_id', $result[0]->getName());
    }
}
