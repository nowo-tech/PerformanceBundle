<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for PerformanceMetricsService edge cases and complex scenarios.
 */
final class PerformanceMetricsServiceEdgeCasesTest extends TestCase
{
    private ManagerRegistry|MockObject $registry;
    private EntityManagerInterface|MockObject $entityManager;
    private RouteDataRepository|MockObject $repository;
    private PerformanceMetricsService $service;

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

        $this->service = new PerformanceMetricsService($this->registry, 'default', false, false, true);
    }

    public function testRecordMetricsWithAllNullValues(): void
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
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics('app_home', 'dev', null, null, null, null, null, null);
        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }

    public function testRecordMetricsWithZeroValues(): void
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
                return $routeData instanceof RouteData
                    && $routeData->getRequestTime() === 0.0
                    && $routeData->getTotalQueries() === 0
                    && $routeData->getQueryTime() === 0.0;
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics('app_home', 'dev', 0.0, 0, 0.0);
        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }

    public function testRecordMetricsWithVeryLargeValues(): void
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
                return $routeData instanceof RouteData
                    && $routeData->getRequestTime() === 3600.0
                    && $routeData->getTotalQueries() === 999999
                    && $routeData->getMemoryUsage() === PHP_INT_MAX;
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics('app_home', 'dev', 3600.0, 999999, 1800.0, null, PHP_INT_MAX);
        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }

    public function testRecordMetricsUpdatesOnlyRequestTimeWhenWorse(): void
    {
        $existingRoute = new RouteData();
        $existingRoute->setName('app_home');
        $existingRoute->setEnv('dev');
        $existingRoute->setRequestTime(0.3);
        $existingRoute->setTotalQueries(10);

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($existingRoute);

        $this->entityManager
            ->expects($this->once())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
        $this->assertFalse($result['is_new']);
        $this->assertTrue($result['was_updated']);
    }

    public function testRecordMetricsUpdatesOnlyQueryCountWhenWorse(): void
    {
        $existingRoute = new RouteData();
        $existingRoute->setName('app_home');
        $existingRoute->setEnv('dev');
        $existingRoute->setRequestTime(0.5);
        $existingRoute->setTotalQueries(5);

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($existingRoute);

        $this->entityManager
            ->expects($this->once())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
        $this->assertFalse($result['is_new']);
        $this->assertTrue($result['was_updated']);
    }

    public function testRecordMetricsDoesNotUpdateWhenMetricsAreBetter(): void
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
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Better metrics (lower time, fewer queries)
        $result = $this->service->recordMetrics('app_home', 'dev', 0.3, 5, 0.1);
        $this->assertIsArray($result);
        $this->assertFalse($result['is_new']);
        // wasUpdated is still true because accessCount is incremented
        $this->assertTrue($result['was_updated']);
    }

    public function testRecordMetricsWithQueryTimeOnly(): void
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
                return $routeData instanceof RouteData
                    && $routeData->getQueryTime() === 0.2
                    && null === $routeData->getRequestTime()
                    && null === $routeData->getTotalQueries();
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics('app_home', 'dev', null, null, 0.2);
        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }

    public function testRecordMetricsWithParamsArray(): void
    {
        $params = ['id' => 123, 'slug' => 'test-article', 'category' => 'tech'];

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($routeData) use ($params) {
                return $routeData instanceof RouteData
                    && $routeData->getParams() === $params;
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2, $params);
        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }

    public function testRecordMetricsWithEmptyParamsArray(): void
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
                return $routeData instanceof RouteData
                    && $routeData->getParams() === [];
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2, []);
        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }

    public function testRecordMetricsUpdatesParamsWhenMetricsAreWorse(): void
    {
        $existingRoute = new RouteData();
        $existingRoute->setName('app_home');
        $existingRoute->setEnv('dev');
        $existingRoute->setRequestTime(0.3);
        $existingRoute->setTotalQueries(5);
        $existingRoute->setParams(['id' => 123]);

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($existingRoute);

        $this->entityManager
            ->expects($this->once())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $newParams = ['id' => 456, 'slug' => 'new-article'];
        $result = $this->service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2, $newParams);
        $this->assertIsArray($result);
        $this->assertFalse($result['is_new']);
        $this->assertTrue($result['was_updated']);
    }

    public function testRecordMetricsUpdatesQueryTimeWhenMetricsAreWorse(): void
    {
        $existingRoute = new RouteData();
        $existingRoute->setName('app_home');
        $existingRoute->setEnv('dev');
        $existingRoute->setRequestTime(0.3);
        $existingRoute->setTotalQueries(5);
        $existingRoute->setQueryTime(0.1);

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($existingRoute);

        $this->entityManager
            ->expects($this->once())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics('app_home', 'dev', 0.5, 10, 0.3);
        $this->assertIsArray($result);
        $this->assertFalse($result['is_new']);
        $this->assertTrue($result['was_updated']);
    }

    public function testRecordMetricsWithExistingRouteAndNullRequestTime(): void
    {
        $existingRoute = new RouteData();
        $existingRoute->setName('app_home');
        $existingRoute->setEnv('dev');
        $existingRoute->setRequestTime(null);
        $existingRoute->setTotalQueries(10);

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($existingRoute);

        $this->entityManager
            ->expects($this->once())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Should update because existing route has null requestTime
        $result = $this->service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
        $this->assertFalse($result['is_new']);
        $this->assertTrue($result['was_updated']);
    }

    public function testRecordMetricsWithExistingRouteAndNullQueryCount(): void
    {
        $existingRoute = new RouteData();
        $existingRoute->setName('app_home');
        $existingRoute->setEnv('dev');
        $existingRoute->setRequestTime(0.5);
        $existingRoute->setTotalQueries(null);

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($existingRoute);

        $this->entityManager
            ->expects($this->once())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Should update because existing route has null totalQueries
        $result = $this->service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
        $this->assertFalse($result['is_new']);
        $this->assertTrue($result['was_updated']);
    }

    public function testRecordMetricsWithMultipleStatusCodes(): void
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
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Record with multiple status codes in track list
        $result = $this->service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            404,
            [200, 404, 500, 503]
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }

    public function testRecordMetricsWithAccessRecordsAndMultipleFlushes(): void
    {
        $service = new PerformanceMetricsService($this->registry, 'default', false, true, true);

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist')
            ->with($this->callback(function ($entity) {
                return $entity instanceof RouteData || $entity instanceof RouteDataRecord;
            }));

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        $this->entityManager
            ->expects($this->once())
            ->method('isOpen')
            ->willReturn(true);

        $result = $service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2, null, null, 'GET', 200, [200, 404, 500, 503]);
        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }
}
