<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Event\BeforeMetricsRecordedEvent;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests for PerformanceMetricsService with event dispatcher.
 */
final class PerformanceMetricsServiceEventsTest extends TestCase
{
    private ManagerRegistry|MockObject $registry;
    private EntityManagerInterface|MockObject $entityManager;
    private RouteDataRepository|MockObject $repository;
    private EventDispatcherInterface|MockObject $eventDispatcher;
    private PerformanceMetricsService $service;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(RouteDataRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->registry
            ->method('getManager')
            ->with('default')
            ->willReturn($this->entityManager);

        $this->entityManager
            ->method('getRepository')
            ->with(RouteData::class)
            ->willReturn($this->repository);

        $this->service = new PerformanceMetricsService($this->registry, 'default', false, false, true);
        $this->service->setEventDispatcher($this->eventDispatcher);
    }

    public function testRecordMetricsDispatchesBeforeEvent(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(BeforeMetricsRecordedEvent::class))
            ->willReturnCallback(function ($event) {
                $this->assertInstanceOf(BeforeMetricsRecordedEvent::class, $event);
                $this->assertSame('app_home', $event->getRouteName());
                $this->assertSame('dev', $event->getEnv());
                $this->assertSame(0.5, $event->getRequestTime());
                $this->assertSame(10, $event->getTotalQueries());
                return $event;
            });

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

        $result = $this->service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
    }

    public function testRecordMetricsUsesModifiedValuesFromBeforeEvent(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(BeforeMetricsRecordedEvent::class))
            ->willReturnCallback(function ($event) {
                // Modify values in the event
                $event->setRequestTime(0.8);
                $event->setTotalQueries(20);
                $event->setQueryTime(0.4);
                return $event;
            });

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($routeData) {
                return $routeData instanceof RouteData
                    && $routeData->getRequestTime() === 0.8
                    && $routeData->getTotalQueries() === 20
                    && $routeData->getQueryTime() === 0.4;
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
    }

    public function testRecordMetricsDispatchesAfterEvent(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        $this->eventDispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($routeData) {
                if ($event instanceof BeforeMetricsRecordedEvent) {
                    return $event;
                }
                if ($event instanceof AfterMetricsRecordedEvent) {
                    $this->assertSame($routeData, $event->getRouteData());
                    $this->assertFalse($event->isNew());
                    return $event;
                }
                return $event;
            });

        $this->entityManager
            ->expects($this->once())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
    }

    public function testRecordMetricsDispatchesAfterEventForNewRecord(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->eventDispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                if ($event instanceof BeforeMetricsRecordedEvent) {
                    return $event;
                }
                if ($event instanceof AfterMetricsRecordedEvent) {
                    $this->assertTrue($event->isNew());
                    return $event;
                }
                return $event;
            });

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

        $result = $this->service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }

    public function testRecordMetricsWorksWithoutEventDispatcher(): void
    {
        $service = new PerformanceMetricsService($this->registry, 'default', false, false, true);
        // Don't set event dispatcher

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

        $result = $service->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }
}
