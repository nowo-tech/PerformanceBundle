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
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Covers recordMetricsSync when EntityManager is closed before flush (triggers reset beforehand).
 */
final class PerformanceMetricsServiceRecordMetricsSyncClosedEmTest extends TestCase
{
    public function testRecordMetricsSyncResetsEntityManagerWhenClosedBeforeFlush(): void
    {
        $routeData = new RouteData();
        $routeData->setName('closed_em_route');
        $routeData->setEnv('test');

        $routeRepo = $this->createMock(RouteDataRepository::class);
        $routeRepo->method('findByRouteAndEnv')->with('closed_em_route', 'test')->willReturn($routeData);

        $recordRepo = $this->createMock(RouteDataRecordRepository::class);

        $closedEm = $this->createMock(EntityManagerInterface::class);
        $closedEm->method('isOpen')->willReturn(false);
        $closedEm->method('flush');

        $freshEm = $this->createMock(EntityManagerInterface::class);
        $freshEm->method('isOpen')->willReturn(true);
        $freshEm->method('flush');
        $freshEm->method('getRepository')
            ->willReturnCallback(static function (string $class) use ($routeRepo, $recordRepo) {
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

        $service = (new ReflectionClass(PerformanceMetricsService::class))->newInstanceWithoutConstructor();

        foreach (
            [
                'registry'            => $registry,
                'connectionName'      => 'default',
                'async'               => false,
                'enableAccessRecords' => false,
                'enableLogging'       => false,
                'entityManager'       => $closedEm,
                'repository'          => $routeRepo,
                'recordRepository'    => $recordRepo,
            ] as $prop => $value
        ) {
            $rp = new ReflectionProperty(PerformanceMetricsService::class, $prop);
            $rp->setAccessible(true);
            $rp->setValue($service, $value);
        }

        $m = new ReflectionMethod(PerformanceMetricsService::class, 'recordMetricsSync');
        $m->setAccessible(true);

        $result = $m->invoke(
            $service,
            'closed_em_route',
            'test',
            0.1,
            1,
            0.01,
            null,
            100000,
            'GET',
            200,
            null,
            null,
            null,
            null,
            null,
        );

        self::assertFalse($result['is_new']);
        self::assertTrue($result['was_updated']);
    }
}
