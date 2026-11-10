<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use LogicException;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Message\RecordMetricsMessage;
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

    public function testConstructThrowsWhenRegistryReturnsNonEntityManager(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('default')->willReturn($this->createMock(ObjectManager::class));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected EntityManagerInterface from registry.');

        new PerformanceMetricsService($registry, 'default');
    }

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
            ->with($this->callback(static fn ($msg): bool => $msg instanceof RecordMetricsMessage
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
}
