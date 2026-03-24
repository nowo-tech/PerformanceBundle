<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Exception;
use LogicException;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\MessageBus\MessageBusInterface;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests for PerformanceMetricsService::getRouteData() and getRoutesByEnvironment().
 */
final class PerformanceMetricsServiceGetRouteDataAndRoutesByEnvTest extends TestCase
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

        $connection = $this->createMock(Connection::class);
        $this->entityManager->method('getConnection')->willReturn($connection);

        $this->service = new PerformanceMetricsService($this->registry, 'default');
    }

    /** @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::__construct */
    public function testConstructThrowsWhenRegistryReturnsNonEntityManager(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('default')->willReturn($this->createMock(ObjectManager::class));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected EntityManagerInterface from registry.');

        new PerformanceMetricsService($registry, 'default');
    }

    /** @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::__construct */
    public function testConstructThrowsWhenRouteDataRepositoryIsWrongType(): void
    {
        $recordRepo = $this->createMock(RouteDataRecordRepository::class);
        $registry   = $this->createMock(ManagerRegistry::class);
        $em         = $this->createMock(EntityManagerInterface::class);
        $registry->method('getManager')->with('default')->willReturn($em);
        $genericRepo = $this->createMock(EntityRepository::class);
        $em->method('getRepository')
            ->willReturnCallback(static function (string $class) use ($genericRepo, $recordRepo): EntityRepository|RouteDataRecordRepository {
                if ($class === RouteData::class) {
                    return $genericRepo;
                }

                return $recordRepo;
            });

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected RouteDataRepository.');

        new PerformanceMetricsService($registry, 'default');
    }

    /** @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::__construct */
    public function testConstructThrowsWhenRouteDataRecordRepositoryIsWrongType(): void
    {
        $routeRepo = $this->createMock(RouteDataRepository::class);
        $registry  = $this->createMock(ManagerRegistry::class);
        $em        = $this->createMock(EntityManagerInterface::class);
        $registry->method('getManager')->with('default')->willReturn($em);
        $genericRepo = $this->createMock(EntityRepository::class);
        $em->method('getRepository')
            ->willReturnCallback(static function (string $class) use ($routeRepo, $genericRepo): RouteDataRepository|EntityRepository {
                if ($class === RouteData::class) {
                    return $routeRepo;
                }

                return $genericRepo;
            });

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected RouteDataRecordRepository.');

        new PerformanceMetricsService($registry, 'default');
    }

    public function testRecordMetricsAsyncDispatchesMessageAndReturnsPlaceholder(): void
    {
        $service = new PerformanceMetricsService($this->registry, 'default', true);
        $bus     = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static fn ($msg): bool => $msg instanceof \Nowo\PerformanceBundle\Message\RecordMetricsMessage
                && $msg->getRouteName() === 'async_route'
                && $msg->getEnv() === 'test'))
            ->willReturn(null);
        $service->setMessageBus($bus);

        $result = $service->recordMetrics('async_route', 'test', 0.1, 5);

        $this->assertSame(['is_new' => false, 'was_updated' => false], $result);
    }

    public function testSetMessageBusAcceptsNull(): void
    {
        $this->service->setMessageBus(null);
        $this->addToAssertionCount(1);
    }

    public function testSetMessageBusAcceptsObject(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $this->service->setMessageBus($bus);
        $this->addToAssertionCount(1);
    }

    public function testSetCacheService(): void
    {
        $cache = $this->createMock(PerformanceCacheService::class);
        $this->service->setCacheService($cache);
        $this->addToAssertionCount(1);
    }

    public function testSetCacheServiceNull(): void
    {
        $this->service->setCacheService(null);
        $this->addToAssertionCount(1);
    }

    public function testSetEventDispatcher(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->service->setEventDispatcher($dispatcher);
        $this->addToAssertionCount(1);
    }

    public function testSetEventDispatcherNull(): void
    {
        $this->service->setEventDispatcher(null);
        $this->addToAssertionCount(1);
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

    /**
     * When getAggregatesForRouteDataIds omits an id, that route gets default aggregate (nulls, 0, []).
     *
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::getRoutesWithAggregates
     */
    public function testGetRoutesWithAggregatesUsesDefaultAggregateWhenIdMissingFromAggregates(): void
    {
        $r1 = new RouteData();
        $r1->setName('a')->setEnv('dev');
        $ref = new ReflectionProperty(RouteData::class, 'id');
        $ref->setValue($r1, 1);

        $r2 = new RouteData();
        $r2->setName('b')->setEnv('dev');
        $ref2 = new ReflectionProperty(RouteData::class, 'id');
        $ref2->setValue($r2, 2);

        $this->repository->method('findByEnvironment')->with('dev')->willReturn([$r1, $r2]);
        $this->recordRepository->method('getAggregatesForRouteDataIds')->with([1, 2])->willReturn([
            1 => [
                'request_time'  => 0.5,
                'total_queries' => 10,
                'query_time'    => 0.1,
                'memory_usage'  => 1000,
                'access_count'  => 5,
                'status_codes'  => [],
            ],
            // id 2 omitted -> default aggregate used
        ]);

        $result = $this->service->getRoutesWithAggregates('dev');

        $this->assertCount(2, $result);
        $this->assertSame(0.5, $result[0]->getRequestTime());
        $this->assertSame(10, $result[0]->getTotalQueries());
        $this->assertNull($result[1]->getRequestTime());
        $this->assertNull($result[1]->getTotalQueries());
    }

    /**
     * When a route has getId() === null, it is skipped in the foreach (line 519).
     *
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::getRoutesWithAggregates
     */
    public function testGetRoutesWithAggregatesSkipsRouteWithNullId(): void
    {
        $r1 = new RouteData();
        $r1->setName('a')->setEnv('dev');
        $ref = new ReflectionProperty(RouteData::class, 'id');
        $ref->setValue($r1, 1);

        $r2 = new RouteData();
        $r2->setName('b')->setEnv('dev');
        // r2 has id = null (default), so it is skipped in the loop

        $this->repository->method('findByEnvironment')->with('dev')->willReturn([$r1, $r2]);
        $this->recordRepository->method('getAggregatesForRouteDataIds')->with([1])->willReturn([
            1 => [
                'request_time'  => 0.2,
                'total_queries' => 3,
                'query_time'    => 0.01,
                'memory_usage'  => 512,
                'access_count'  => 1,
                'status_codes'  => [],
            ],
        ]);

        $result = $this->service->getRoutesWithAggregates('dev');

        $this->assertCount(1, $result);
        $this->assertSame(0.2, $result[0]->getRequestTime());
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

    public function testGetConnectionReturnsEntityManagerConnection(): void
    {
        $connection = $this->service->getConnection();
        $this->assertInstanceOf(Connection::class, $connection);
    }

    public function testGetEntityManagerReturnsInjectedEntityManager(): void
    {
        $this->assertSame($this->entityManager, $this->service->getEntityManager());
    }

    public function testRecordMetricsSyncWhenNoExistingRouteCreatesNewAndFlushes(): void
    {
        $this->repository->method('findByRouteAndEnv')->with('new_route', 'dev')->willReturn(null);
        $this->entityManager->method('isOpen')->willReturn(true);
        $this->entityManager->expects($this->once())->method('persist')->with($this->callback(static fn ($entity): bool => $entity instanceof RouteData
            && $entity->getName() === 'new_route'
            && $entity->getEnv() === 'dev'));
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->recordMetrics('new_route', 'dev', 0.5, 10, 0.1, null, null, 'GET', 200);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('is_new', $result);
        $this->assertArrayHasKey('was_updated', $result);
        $this->assertTrue($result['is_new']);
        $this->assertFalse($result['was_updated']);
    }

    public function testRecordMetricsSyncWhenExistingRouteUpdatesAndFlushes(): void
    {
        $existing = new RouteData();
        $existing->setName('existing_route')->setEnv('dev');
        $this->repository->method('findByRouteAndEnv')->with('existing_route', 'dev')->willReturn($existing);
        $this->entityManager->method('isOpen')->willReturn(true);
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->recordMetrics('existing_route', 'dev', 0.3, 5);

        $this->assertTrue($result['was_updated']);
        $this->assertFalse($result['is_new']);
    }

    /**
     * When cacheService is set, successful recordMetricsSync invalidates cache for the env.
     *
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::recordMetrics
     */
    public function testRecordMetricsSyncInvalidatesCacheWhenCacheServiceSet(): void
    {
        $cache = $this->createMock(PerformanceCacheService::class);
        $cache->expects($this->once())->method('invalidateStatistics')->with('dev');

        $this->service->setCacheService($cache);

        $existing = new RouteData();
        $existing->setName('cached_route')->setEnv('dev');
        $this->repository->method('findByRouteAndEnv')->with('cached_route', 'dev')->willReturn($existing);
        $this->entityManager->method('isOpen')->willReturn(true);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->recordMetrics('cached_route', 'dev', 0.2, 3);
    }

    /**
     * Covers resetEntityManager() when EntityManager is closed before flush:
     * service resets manager and replaces with a fresh one, then flush succeeds.
     *
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::recordMetrics
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::resetEntityManager
     */
    public function testRecordMetricsSyncResetsEntityManagerWhenClosedBeforeFlush(): void
    {
        $connection = $this->createMock(Connection::class);
        $emClosed   = $this->createMock(EntityManagerInterface::class);
        $emOpen     = $this->createMock(EntityManagerInterface::class);

        $repo       = $this->createMock(RouteDataRepository::class);
        $recordRepo = $this->createMock(RouteDataRecordRepository::class);
        $existing   = new RouteData();
        $existing->setName('route')->setEnv('dev');
        $repo->method('findByRouteAndEnv')->with('route', 'dev')->willReturn($existing);

        $emClosed->method('getRepository')
            ->willReturnCallback(static fn (string $class): RouteDataRepository|RouteDataRecordRepository => $class === RouteData::class ? $repo : $recordRepo);
        $emClosed->method('getConnection')->willReturn($connection);
        $emClosed->method('isOpen')->willReturn(false);

        $emOpen->method('getRepository')
            ->willReturnCallback(static fn (string $class): RouteDataRepository|RouteDataRecordRepository => $class === RouteData::class ? $repo : $recordRepo);
        $emOpen->method('getConnection')->willReturn($connection);
        $emOpen->method('isOpen')->willReturn(true);
        $emOpen->expects($this->once())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('default')->willReturnOnConsecutiveCalls($emClosed, $emOpen);
        $registry->expects($this->once())->method('resetManager')->with('default');

        $service = new PerformanceMetricsService($registry, 'default', false);

        $result = $service->recordMetrics('route', 'dev', 0.1, 2);

        $this->assertFalse($result['is_new']);
        $this->assertTrue($result['was_updated']);
    }

    /**
     * When flush() throws, recordMetricsSync catches, resets EntityManager, and rethrows.
     *
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::recordMetrics
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::recordMetricsSync
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::resetEntityManager
     */
    public function testRecordMetricsSyncRethrowsExceptionAfterResetWhenFlushThrows(): void
    {
        $connection = $this->createMock(Connection::class);
        $emThrow    = $this->createMock(EntityManagerInterface::class);
        $emFresh    = $this->createMock(EntityManagerInterface::class);

        $repo       = $this->createMock(RouteDataRepository::class);
        $recordRepo = $this->createMock(RouteDataRecordRepository::class);
        $existing   = new RouteData();
        $existing->setName('route')->setEnv('dev');
        $repo->method('findByRouteAndEnv')->with('route', 'dev')->willReturn($existing);

        $emThrow->method('getRepository')
            ->willReturnCallback(static fn (string $class): RouteDataRepository|RouteDataRecordRepository => $class === RouteData::class ? $repo : $recordRepo);
        $emThrow->method('getConnection')->willReturn($connection);
        $emThrow->method('isOpen')->willReturn(true);
        $emThrow->method('flush')->willThrowException(new Exception('flush failed'));

        $emFresh->method('getRepository')
            ->willReturnCallback(static fn (string $class): RouteDataRepository|RouteDataRecordRepository => $class === RouteData::class ? $repo : $recordRepo);
        $emFresh->method('getConnection')->willReturn($connection);
        $emFresh->method('isOpen')->willReturn(true);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('default')->willReturnOnConsecutiveCalls($emThrow, $emFresh);
        $registry->method('resetManager')->with('default');

        $service = new PerformanceMetricsService($registry, 'default', false);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('flush failed');

        $service->recordMetrics('route', 'dev', 0.1, 2);
    }

    /**
     * When enableAccessRecords is true, requestId is set, and no existing record: creates access record and sets referer/userId/params/routePath.
     *
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::recordMetrics
     */
    public function testRecordMetricsSyncCreatesAccessRecordWhenEnabledAndRequestIdSet(): void
    {
        $connection = $this->createMock(Connection::class);
        $em         = $this->createMock(EntityManagerInterface::class);
        $repo       = $this->createMock(RouteDataRepository::class);
        $recordRepo = $this->createMock(RouteDataRecordRepository::class);

        $existing = new RouteData();
        $existing->setName('api_route')->setEnv('dev')->setSaveAccessRecords(true);
        $repo->method('findByRouteAndEnv')->with('api_route', 'dev')->willReturn($existing);
        $recordRepo->method('findOneByRequestId')->with('req-123')->willReturn(null);

        $em->method('getRepository')
            ->willReturnCallback(static fn (string $class): RouteDataRepository|RouteDataRecordRepository => $class === RouteData::class ? $repo : $recordRepo);
        $em->method('getConnection')->willReturn($connection);
        $em->method('isOpen')->willReturn(true);
        $em->expects($this->exactly(2))->method('flush');
        $em->expects($this->once())->method('persist')->with($this->callback(static fn ($entity): bool => $entity instanceof RouteDataRecord
            && $entity->getRequestId() === 'req-123'
            && $entity->getReferer() === 'https://example.com'
            && $entity->getUserIdentifier() === 'user@test.com'
            && $entity->getUserId() === '42'
            && $entity->getRoutePath() === '/api/route'));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('default')->willReturn($em);

        $service = new PerformanceMetricsService($registry, 'default', false, true);

        $result = $service->recordMetrics(
            'api_route',
            'dev',
            0.15,
            5,
            0.02,
            ['id' => 1],
            1024,
            'GET',
            200,
            [],
            'req-123',
            'https://example.com',
            'user@test.com',
            '42',
            '/api/route',
        );

        $this->assertFalse($result['is_new']);
        $this->assertTrue($result['was_updated']);
    }

    /**
     * When enableAccessRecords is true but getSaveAccessRecords() is false: skips creating access record (line 373).
     *
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::recordMetrics
     */
    public function testRecordMetricsSyncSkipsAccessRecordWhenRouteHasSaveAccessRecordsDisabled(): void
    {
        $connection = $this->createMock(Connection::class);
        $em         = $this->createMock(EntityManagerInterface::class);
        $repo       = $this->createMock(RouteDataRepository::class);
        $recordRepo = $this->createMock(RouteDataRecordRepository::class);

        $existing = new RouteData();
        $existing->setName('no_access')->setEnv('dev')->setSaveAccessRecords(false);
        $repo->method('findByRouteAndEnv')->with('no_access', 'dev')->willReturn($existing);

        $em->method('getRepository')
            ->willReturnCallback(static fn (string $class): RouteDataRepository|RouteDataRecordRepository => $class === RouteData::class ? $repo : $recordRepo);
        $em->method('getConnection')->willReturn($connection);
        $em->method('isOpen')->willReturn(true);
        $em->expects($this->once())->method('flush');
        $recordRepo->expects($this->never())->method('findOneByRequestId');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('default')->willReturn($em);

        $service = new PerformanceMetricsService($registry, 'default', false, true);

        $result = $service->recordMetrics('no_access', 'dev', 0.1, 2, null, null, null, null, 200, [], 'req-456');

        $this->assertTrue($result['was_updated']);
    }

    /**
     * When enableAccessRecords is true and findOneByRequestId returns existing record: skips creating duplicate (line 378).
     *
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::recordMetrics
     */
    public function testRecordMetricsSyncSkipsAccessRecordWhenRequestIdAlreadyRecorded(): void
    {
        $connection = $this->createMock(Connection::class);
        $em         = $this->createMock(EntityManagerInterface::class);
        $repo       = $this->createMock(RouteDataRepository::class);
        $recordRepo = $this->createMock(RouteDataRecordRepository::class);

        $existing = new RouteData();
        $existing->setName('dup')->setEnv('dev')->setSaveAccessRecords(true);
        $existingRecord = new RouteDataRecord();
        $repo->method('findByRouteAndEnv')->with('dup', 'dev')->willReturn($existing);
        $recordRepo->method('findOneByRequestId')->with('req-789')->willReturn($existingRecord);

        $em->method('getRepository')
            ->willReturnCallback(static fn (string $class): RouteDataRepository|RouteDataRecordRepository => $class === RouteData::class ? $repo : $recordRepo);
        $em->method('getConnection')->willReturn($connection);
        $em->method('isOpen')->willReturn(true);
        $em->expects($this->once())->method('flush');
        $em->expects($this->never())->method('persist')->with($this->callback(static fn ($e): bool => $e instanceof RouteDataRecord));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('default')->willReturn($em);

        $service = new PerformanceMetricsService($registry, 'default', false, true);

        $result = $service->recordMetrics('dup', 'dev', 0.1, 2, null, null, null, null, 200, [], 'req-789');

        $this->assertTrue($result['was_updated']);
    }

    /**
     * When resetEntityManager runs and getManager returns non-EntityManagerInterface, LogicException is thrown (line 651).
     *
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::recordMetrics
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::resetEntityManager
     */
    public function testRecordMetricsSyncResetThrowsWhenGetManagerReturnsNonEntityManager(): void
    {
        $connection = $this->createMock(Connection::class);
        $emClosed   = $this->createMock(EntityManagerInterface::class);
        $repo       = $this->createMock(RouteDataRepository::class);
        $recordRepo = $this->createMock(RouteDataRecordRepository::class);
        $existing   = new RouteData();
        $existing->setName('x')->setEnv('dev');
        $repo->method('findByRouteAndEnv')->with('x', 'dev')->willReturn($existing);

        $emClosed->method('getRepository')
            ->willReturnCallback(static fn (string $class): RouteDataRepository|RouteDataRecordRepository => $class === RouteData::class ? $repo : $recordRepo);
        $emClosed->method('getConnection')->willReturn($connection);
        $emClosed->method('isOpen')->willReturn(false);
        $emClosed->expects($this->never())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $notAnEM  = $this->createMock(ObjectManager::class);
        $registry->method('getManager')->with('default')->willReturnOnConsecutiveCalls($emClosed, $notAnEM, $notAnEM);
        $registry->method('resetManager')->with('default');

        $service = new PerformanceMetricsService($registry, 'default', false);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected EntityManagerInterface from registry.');

        $service->recordMetrics('x', 'dev', 0.1, 2);
    }

    /**
     * When resetEntityManager runs and getRepository(RouteData::class) returns non-RouteDataRepository, LogicException (line 656).
     *
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::recordMetrics
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::resetEntityManager
     */
    public function testRecordMetricsSyncResetThrowsWhenRouteRepositoryWrongType(): void
    {
        $connection = $this->createMock(Connection::class);
        $emClosed   = $this->createMock(EntityManagerInterface::class);
        $emOpen     = $this->createMock(EntityManagerInterface::class);
        $repo       = $this->createMock(RouteDataRepository::class);
        $recordRepo = $this->createMock(RouteDataRecordRepository::class);
        $existing   = new RouteData();
        $existing->setName('y')->setEnv('dev');
        $repo->method('findByRouteAndEnv')->with('y', 'dev')->willReturn($existing);

        $emClosed->method('getRepository')
            ->willReturnCallback(static fn (string $class): RouteDataRepository|RouteDataRecordRepository => $class === RouteData::class ? $repo : $recordRepo);
        $emClosed->method('getConnection')->willReturn($connection);
        $emClosed->method('isOpen')->willReturn(false);

        $wrongRepo = $this->createMock(EntityRepository::class);
        $emOpen->method('getRepository')
            ->willReturnCallback(static fn (string $class): MockObject => $class === RouteData::class ? $wrongRepo : $recordRepo);
        $emOpen->method('getConnection')->willReturn($connection);
        $emOpen->method('isOpen')->willReturn(true);
        $emOpen->expects($this->never())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('default')->willReturnOnConsecutiveCalls($emClosed, $emOpen, $emOpen);
        $registry->method('resetManager')->with('default');

        $service = new PerformanceMetricsService($registry, 'default', false);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected RouteDataRepository.');

        $service->recordMetrics('y', 'dev', 0.1, 2);
    }

    /**
     * When resetEntityManager runs and getRepository(RouteDataRecord::class) returns non-RouteDataRecordRepository, LogicException (line 661).
     *
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::recordMetrics
     * @covers \Nowo\PerformanceBundle\Service\PerformanceMetricsService::resetEntityManager
     */
    public function testRecordMetricsSyncResetThrowsWhenRecordRepositoryWrongType(): void
    {
        $connection = $this->createMock(Connection::class);
        $emClosed   = $this->createMock(EntityManagerInterface::class);
        $emOpen     = $this->createMock(EntityManagerInterface::class);
        $repo       = $this->createMock(RouteDataRepository::class);
        $recordRepo = $this->createMock(RouteDataRecordRepository::class);
        $existing   = new RouteData();
        $existing->setName('z')->setEnv('dev');
        $repo->method('findByRouteAndEnv')->with('z', 'dev')->willReturn($existing);

        $emClosed->method('getRepository')
            ->willReturnCallback(static fn (string $class): RouteDataRepository|RouteDataRecordRepository => $class === RouteData::class ? $repo : $recordRepo);
        $emClosed->method('getConnection')->willReturn($connection);
        $emClosed->method('isOpen')->willReturn(false);

        $wrongRecordRepo = $this->createMock(EntityRepository::class);
        $emOpen->method('getRepository')
            ->willReturnCallback(static fn (string $class): MockObject => $class === RouteData::class ? $repo : $wrongRecordRepo);
        $emOpen->method('getConnection')->willReturn($connection);
        $emOpen->method('isOpen')->willReturn(true);
        $emOpen->expects($this->never())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('default')->willReturnOnConsecutiveCalls($emClosed, $emOpen, $emOpen);
        $registry->method('resetManager')->with('default');

        $service = new PerformanceMetricsService($registry, 'default', false);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected RouteDataRecordRepository.');

        $service->recordMetrics('z', 'dev', 0.1, 2);
    }
}
