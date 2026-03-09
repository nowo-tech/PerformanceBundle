<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\MessageBus\MessageBusInterface;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
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

    public function testRecordMetricsAsyncDispatchesMessageAndReturnsPlaceholder(): void
    {
        $service = new PerformanceMetricsService($this->registry, 'default', true);
        $bus     = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function ($msg): bool {
                return $msg instanceof \Nowo\PerformanceBundle\Message\RecordMetricsMessage
                    && $msg->getRouteName() === 'async_route'
                    && $msg->getEnv() === 'test';
            }))
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
        $this->entityManager->expects($this->once())->method('persist')->with($this->callback(static function ($entity): bool {
            return $entity instanceof RouteData
                && $entity->getName() === 'new_route'
                && $entity->getEnv() === 'dev';
        }));
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
}
