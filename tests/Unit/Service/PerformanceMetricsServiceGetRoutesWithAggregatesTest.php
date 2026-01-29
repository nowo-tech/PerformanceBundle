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
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for PerformanceMetricsService::getRoutesWithAggregatesFiltered (sorting by memoryUsage and other metrics).
 */
final class PerformanceMetricsServiceGetRoutesWithAggregatesTest extends TestCase
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

    public function testGetRoutesWithAggregatesFilteredSortByMemoryUsageDesc(): void
    {
        $route1 = new RouteData();
        $route1->setName('route_low');
        $route1->setEnv('dev');
        $ref1 = new \ReflectionProperty(RouteData::class, 'id');
        $ref1->setAccessible(true);
        $ref1->setValue($route1, 1);

        $route2 = new RouteData();
        $route2->setName('route_high');
        $route2->setEnv('dev');
        $ref2 = new \ReflectionProperty(RouteData::class, 'id');
        $ref2->setAccessible(true);
        $ref2->setValue($route2, 2);

        $route3 = new RouteData();
        $route3->setName('route_mid');
        $route3->setEnv('dev');
        $ref3 = new \ReflectionProperty(RouteData::class, 'id');
        $ref3->setAccessible(true);
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
                    'request_time' => 0.1,
                    'query_time' => 0.05,
                    'total_queries' => 5,
                    'memory_usage' => 100_000,
                    'access_count' => 1,
                    'status_codes' => [],
                ],
                2 => [
                    'request_time' => 0.5,
                    'query_time' => 0.2,
                    'total_queries' => 20,
                    'memory_usage' => 50_000_000,
                    'access_count' => 10,
                    'status_codes' => [],
                ],
                3 => [
                    'request_time' => 0.3,
                    'query_time' => 0.1,
                    'total_queries' => 10,
                    'memory_usage' => 5_000_000,
                    'access_count' => 5,
                    'status_codes' => [],
                ],
            ]);

        $result = $this->service->getRoutesWithAggregatesFiltered('dev', [], 'memoryUsage', 'DESC', null);

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
        $ref1 = new \ReflectionProperty(RouteData::class, 'id');
        $ref1->setAccessible(true);
        $ref1->setValue($route1, 1);

        $route2 = new RouteData();
        $route2->setName('r2');
        $route2->setEnv('dev');
        $ref2 = new \ReflectionProperty(RouteData::class, 'id');
        $ref2->setAccessible(true);
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
                    'request_time' => null,
                    'query_time' => null,
                    'total_queries' => null,
                    'memory_usage' => 10_000_000,
                    'access_count' => 1,
                    'status_codes' => [],
                ],
                2 => [
                    'request_time' => null,
                    'query_time' => null,
                    'total_queries' => null,
                    'memory_usage' => 1_000_000,
                    'access_count' => 1,
                    'status_codes' => [],
                ],
            ]);

        $result = $this->service->getRoutesWithAggregatesFiltered('dev', [], 'memoryUsage', 'ASC', null);

        self::assertCount(2, $result);
        self::assertSame(1_000_000, $result[0]->getMemoryUsage());
        self::assertSame(10_000_000, $result[1]->getMemoryUsage());
    }

    public function testGetRoutesWithAggregatesFilteredSortByMemoryUsageWithNullsTreatedAsZero(): void
    {
        $route1 = new RouteData();
        $route1->setName('r1');
        $route1->setEnv('dev');
        $ref1 = new \ReflectionProperty(RouteData::class, 'id');
        $ref1->setAccessible(true);
        $ref1->setValue($route1, 1);

        $route2 = new RouteData();
        $route2->setName('r2');
        $route2->setEnv('dev');
        $ref2 = new \ReflectionProperty(RouteData::class, 'id');
        $ref2->setAccessible(true);
        $ref2->setValue($route2, 2);

        $this->repository
            ->method('findWithFilters')
            ->willReturn([$route1, $route2]);

        $this->recordRepository
            ->method('getAggregatesForRouteDataIds')
            ->with([1, 2])
            ->willReturn([
                1 => [
                    'request_time' => null,
                    'query_time' => null,
                    'total_queries' => null,
                    'memory_usage' => null,
                    'access_count' => 1,
                    'status_codes' => [],
                ],
                2 => [
                    'request_time' => null,
                    'query_time' => null,
                    'total_queries' => null,
                    'memory_usage' => 100,
                    'access_count' => 1,
                    'status_codes' => [],
                ],
            ]);

        $result = $this->service->getRoutesWithAggregatesFiltered('dev', [], 'memoryUsage', 'DESC', null);

        self::assertCount(2, $result);
        self::assertSame(100, $result[0]->getMemoryUsage());
        self::assertNull($result[1]->getMemoryUsage());
    }
}
