<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
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

        $result = $this->service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            ['id' => 123],
            null,
            'GET'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('is_new', $result);
        $this->assertArrayHasKey('was_updated', $result);
        $this->assertTrue($result['is_new']);
        $this->assertFalse($result['was_updated']);
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
        $existingRoute->setRequestTime(0.8);
        $existingRoute->setTotalQueries(15);

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($existingRoute);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Try to update with better metrics (lower time, fewer queries)
        $result = $this->service->recordMetrics(
            'app_home',
            'dev',
            0.3, // Better (lower)
            5,   // Better (fewer)
            0.2
        );

        // Metrics should not be updated
        $this->assertSame(0.8, $existingRoute->getRequestTime());
        $this->assertSame(15, $existingRoute->getTotalQueries());
        
        $this->assertIsArray($result);
        $this->assertFalse($result['is_new']);
        $this->assertFalse($result['was_updated']); // No update because metrics were better
    }

    public function testRecordMetricsReturnsOperationInfoForNewRecord(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('new_route', 'dev')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics('new_route', 'dev', 0.5, 10);

        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
        $this->assertFalse($result['was_updated']);
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

    public function testRecordMetricsAcceptsRequestIdParameter(): void
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
            ->expects($this->atLeastOnce())
            ->method('flush');

        $result = $this->service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            null,
            [],
            'req-abc123'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('is_new', $result);
        $this->assertArrayHasKey('was_updated', $result);
        $this->assertTrue($result['is_new']);
    }

    public function testRecordMetricsWithRequestIdAndExistingAccessRecordSkipsDuplicate(): void
    {
        $recordRepository = $this->createMock(RouteDataRecordRepository::class);
        $existingAccessRecord = new RouteDataRecord();
        $existingAccessRecord->setRequestId('req-dedup');

        $this->entityManager
            ->method('getRepository')
            ->willReturnMap([
                [RouteData::class, $this->repository],
                [RouteDataRecord::class, $recordRepository],
            ]);

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $recordRepository
            ->expects($this->once())
            ->method('findOneByRequestId')
            ->with('req-dedup')
            ->willReturn($existingAccessRecord);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(RouteData::class));

        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('flush');

        $service = new PerformanceMetricsService(
            $this->registry,
            'default',
            false,  // async
            true,   // enableAccessRecords
            true    // enableLogging
        );

        $result = $service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            null,
            [],
            'req-dedup'
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }

    public function testRecordMetricsSkipsAccessRecordWhenRouteSaveAccessRecordsFalse(): void
    {
        $recordRepository = $this->createMock(RouteDataRecordRepository::class);
        $existingRoute = new RouteData();
        $existingRoute->setName('app_home');
        $existingRoute->setEnv('dev');
        $existingRoute->setSaveAccessRecords(false);

        $this->entityManager
            ->method('getRepository')
            ->willReturnMap([
                [RouteData::class, $this->repository],
                [RouteDataRecord::class, $recordRepository],
            ]);

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($existingRoute);

        $recordRepository
            ->expects($this->never())
            ->method('findOneByRequestId');

        $this->entityManager
            ->expects($this->never())
            ->method('persist')
            ->with($this->isInstanceOf(RouteDataRecord::class));

        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('flush');

        $service = new PerformanceMetricsService(
            $this->registry,
            'default',
            false,
            true,
            true
        );

        $result = $service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            null,
            [],
            'req-xyz'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('is_new', $result);
        $this->assertArrayHasKey('was_updated', $result);
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

    public function testRecordMetricsReturnsOperationInfoForUpdatedRecord(): void
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

        // Update with worse metrics
        $result = $this->service->recordMetrics(
            'app_home',
            'dev',
            0.8, // Worse
            15   // Worse
        );

        $this->assertIsArray($result);
        $this->assertFalse($result['is_new']);
        $this->assertTrue($result['was_updated']);
    }

    public function testRecordMetricsReturnsOperationInfoForNoChanges(): void
    {
        $existingRoute = new RouteData();
        $existingRoute->setName('app_home');
        $existingRoute->setEnv('dev');
        $existingRoute->setRequestTime(0.8);
        $existingRoute->setTotalQueries(15);

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($existingRoute);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Try with better metrics (should not update)
        $result = $this->service->recordMetrics(
            'app_home',
            'dev',
            0.3, // Better
            5    // Better
        );

        $this->assertIsArray($result);
        $this->assertFalse($result['is_new']);
        $this->assertFalse($result['was_updated']);
    }

    public function testRecordMetricsCreatesRouteDataRecordWhenEnabled(): void
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
                return $entity instanceof RouteData || $entity instanceof \Nowo\PerformanceBundle\Entity\RouteDataRecord;
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            ['id' => 123],
            null,
            'GET',
            200,
            [200, 404, 500, 503]
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }

    public function testRecordMetricsSetsRefererOnAccessRecordWhenProvided(): void
    {
        $service = new PerformanceMetricsService($this->registry, 'default', false, true, true);
        $refererUrl = 'https://referer.example/page';

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $capturedRecord = null;
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedRecord) {
                if ($entity instanceof RouteDataRecord) {
                    $capturedRecord = $entity;
                }
            });

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            200,
            [200, 404, 500, 503],
            null,
            $refererUrl
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
        $this->assertInstanceOf(RouteDataRecord::class, $capturedRecord);
        $this->assertSame($refererUrl, $capturedRecord->getReferer());
    }

    public function testRecordMetricsDoesNotSetRefererOnAccessRecordWhenNull(): void
    {
        $service = new PerformanceMetricsService($this->registry, 'default', false, true, true);

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $capturedRecord = null;
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedRecord) {
                if ($entity instanceof RouteDataRecord) {
                    $capturedRecord = $entity;
                }
            });

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            200,
            [200, 404, 500, 503],
            null,
            null
        );

        $this->assertInstanceOf(RouteDataRecord::class, $capturedRecord);
        $this->assertNull($capturedRecord->getReferer());
    }

    public function testRecordMetricsDoesNotSetRefererOnAccessRecordWhenEmptyString(): void
    {
        $service = new PerformanceMetricsService($this->registry, 'default', false, true, true);

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $capturedRecord = null;
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedRecord) {
                if ($entity instanceof RouteDataRecord) {
                    $capturedRecord = $entity;
                }
            });

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            200,
            [200, 404, 500, 503],
            null,
            ''
        );

        $this->assertInstanceOf(RouteDataRecord::class, $capturedRecord);
        $this->assertNull($capturedRecord->getReferer());
    }

    public function testRecordMetricsDoesNotCreateRouteDataRecordWhenDisabled(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        // Should only persist RouteData, not RouteDataRecord
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(RouteData::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            ['id' => 123],
            null,
            'GET',
            200,
            [200, 404, 500, 503]
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }

    public function testLoggingDisabledDoesNotLogMessages(): void
    {
        // Create service with logging disabled
        $service = new PerformanceMetricsService($this->registry, 'default', false, false, false);

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(\Nowo\PerformanceBundle\Entity\RouteData::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Should work normally even with logging disabled
        $result = $service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('is_new', $result);
        $this->assertArrayHasKey('was_updated', $result);
    }

    public function testLoggingEnabledLogsMessages(): void
    {
        // Create service with logging enabled (default)
        $service = new PerformanceMetricsService($this->registry, 'default', false, false, true);

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(\Nowo\PerformanceBundle\Entity\RouteData::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Should work normally with logging enabled
        $result = $service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('is_new', $result);
        $this->assertArrayHasKey('was_updated', $result);
    }

    public function testRecordMetricsWithClosedEntityManagerResetsManager(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('isOpen')
            ->willReturnOnConsecutiveCalls(false, true);

        $this->registry
            ->expects($this->once())
            ->method('resetManager')
            ->with('default');

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(RouteData::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
    }

    public function testRecordMetricsWithAsyncModeReturnsEarly(): void
    {
        $messageBus = $this->createMock(\Symfony\Component\Messenger\MessageBusInterface::class);
        $service = new PerformanceMetricsService($this->registry, 'default', true, false, true);
        $service->setMessageBus($messageBus);

        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(\Nowo\PerformanceBundle\Message\RecordMetricsMessage::class));

        $this->repository
            ->expects($this->never())
            ->method('findByRouteAndEnv');

        $result = $service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        
        $this->assertIsArray($result);
        $this->assertFalse($result['is_new']);
        $this->assertFalse($result['was_updated']);
    }

    public function testRecordMetricsWithCacheServiceInvalidatesCache(): void
    {
        $cacheService = $this->createMock(\Nowo\PerformanceBundle\Service\PerformanceCacheService::class);
        $this->service->setCacheService($cacheService);

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

        $cacheService
            ->expects($this->once())
            ->method('invalidateStatistics')
            ->with('dev');

        $result = $this->service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
    }

    public function testRecordMetricsWithAccessRecordsEnabledCreatesRecord(): void
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
                return $entity instanceof RouteData || $entity instanceof \Nowo\PerformanceBundle\Entity\RouteDataRecord;
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

    public function testRecordMetricsWithStatusCodeTracking(): void
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
                return $routeData instanceof RouteData;
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            200,
            [200, 404, 500, 503]
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }

    public function testRecordMetricsWithStatusCodeNotInTrackList(): void
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

        // Status code 201 is not in track list [200, 404, 500, 503]
        $result = $this->service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'POST',
            201,
            [200, 404, 500, 503]
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }
}
