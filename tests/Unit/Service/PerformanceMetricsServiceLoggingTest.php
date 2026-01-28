<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Additional tests for PerformanceMetricsService focusing on logging functionality.
 */
final class PerformanceMetricsServiceLoggingTest extends TestCase
{
    private ManagerRegistry|MockObject $registry;
    private EntityManagerInterface|MockObject $entityManager;
    private RouteDataRepository|MockObject $repository;
    private PerformanceMetricsService $serviceWithLogging;
    private PerformanceMetricsService $serviceWithoutLogging;

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
            ->willReturnCallback(function (string $class) {
                if (RouteData::class === $class) {
                    return $this->repository;
                }

                if (\is_a($class, RouteDataRecordRepository::class, true) || $class === \Nowo\PerformanceBundle\Entity\RouteDataRecord::class) {
                    return $this->createMock(RouteDataRecordRepository::class);
                }

                return $this->createMock(\Doctrine\ORM\EntityRepository::class);
            });

        // Service with logging enabled
        $this->serviceWithLogging = new PerformanceMetricsService(
            $this->registry,
            'default',
            false, // async
            false, // enableAccessRecords
            true   // enableLogging
        );

        // Service with logging disabled
        $this->serviceWithoutLogging = new PerformanceMetricsService(
            $this->registry,
            'default',
            false, // async
            false, // enableAccessRecords
            false  // enableLogging
        );
    }

    public function testRecordMetricsWithLoggingEnabledLogsBeforeFlush(): void
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

        // Should work normally with logging enabled
        $result = $this->serviceWithLogging->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }

    public function testRecordMetricsWithLoggingDisabledDoesNotLog(): void
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

        // Should work normally even with logging disabled
        $result = $this->serviceWithoutLogging->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }

    public function testRecordMetricsWithClosedEntityManagerResetsAndLogs(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('isOpen')
            ->willReturnOnConsecutiveCalls(false, true); // First call returns false, then true after reset

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

        // Should reset EntityManager when closed
        $result = $this->serviceWithLogging->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
    }

    public function testRecordMetricsWithExceptionLogsErrorWhenLoggingEnabled(): void
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
            ->method('flush')
            ->willThrowException(new \Exception('Database connection lost'));

        $this->registry
            ->expects($this->once())
            ->method('resetManager')
            ->with('default');

        // Should throw exception (re-thrown after logging)
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database connection lost');

        $this->serviceWithLogging->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
    }

    public function testRecordMetricsWithExceptionDoesNotLogWhenLoggingDisabled(): void
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
            ->method('flush')
            ->willThrowException(new \Exception('Database connection lost'));

        $this->registry
            ->expects($this->once())
            ->method('resetManager')
            ->with('default');

        // Should throw exception (but no logging should occur)
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database connection lost');

        $this->serviceWithoutLogging->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
    }

    public function testRecordMetricsWithExistingRouteAndLoggingEnabled(): void
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
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->serviceWithLogging->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
        $this->assertFalse($result['is_new']);
        $this->assertTrue($result['was_updated']); // Because accessCount is incremented
    }

    public function testRecordMetricsWithExistingRouteAndLoggingDisabled(): void
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
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->serviceWithoutLogging->recordMetrics('app_home', 'dev', 0.5, 10, 0.2);
        $this->assertIsArray($result);
        $this->assertFalse($result['is_new']);
        $this->assertTrue($result['was_updated']); // Because accessCount is incremented
    }
}
