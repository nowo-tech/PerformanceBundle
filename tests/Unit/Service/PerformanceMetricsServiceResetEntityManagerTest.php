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
use ReflectionMethod;
use ReflectionProperty;

/**
 * Covers resetEntityManager() when EntityManager is closed.
 */
final class PerformanceMetricsServiceResetEntityManagerTest extends TestCase
{
    public function testResetEntityManagerRebindsRepositoriesWhenManagerWasClosed(): void
    {
        $closedEm = $this->createMock(EntityManagerInterface::class);
        $closedEm->method('isOpen')->willReturn(false);

        $freshEm = $this->createMock(EntityManagerInterface::class);
        $freshEm->method('isOpen')->willReturn(true);

        $routeRepo  = $this->createMock(RouteDataRepository::class);
        $recordRepo = $this->createMock(RouteDataRecordRepository::class);

        $freshEm->method('getRepository')
            ->willReturnCallback(static function (string $class) use ($routeRepo, $recordRepo): ?\PHPUnit\Framework\MockObject\MockObject {
                if ($class === RouteData::class) {
                    return $routeRepo;
                }
                if ($class === RouteDataRecord::class) {
                    return $recordRepo;
                }

                return null;
            });

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())->method('resetManager')->with('default');
        $registry->method('getManager')->with('default')->willReturn($freshEm);

        $service = new PerformanceMetricsService($registry, 'default', false, true, false);

        $refEm = new ReflectionProperty(PerformanceMetricsService::class, 'entityManager');
        $refEm->setValue($service, $closedEm);

        $m = new ReflectionMethod(PerformanceMetricsService::class, 'resetEntityManager');
        $m->invoke($service);

        self::assertSame($freshEm, $service->getEntityManager());
        self::assertSame($routeRepo, $service->getRepository());
    }
}
