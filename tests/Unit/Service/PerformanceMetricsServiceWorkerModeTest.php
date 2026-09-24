<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Error;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Long-running worker (FrankenPHP) without kernel.reset: recordMetricsSync must not grow the shared
 * identity map nor leave process-wide error_reporting changed.
 */
final class PerformanceMetricsServiceWorkerModeTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private MockObject&RouteDataRepository $repo;
    private MockObject&RouteDataRecordRepository $recordRepo;

    protected function setUp(): void
    {
        $this->em         = $this->createMock(EntityManagerInterface::class);
        $this->repo       = $this->createMock(RouteDataRepository::class);
        $this->recordRepo = $this->createMock(RouteDataRecordRepository::class);

        $this->em->method('getRepository')
            ->willReturnCallback(fn (string $class): RouteDataRepository|RouteDataRecordRepository => $class === RouteData::class ? $this->repo : $this->recordRepo);
        $this->em->method('getConnection')->willReturn($this->createMock(Connection::class));
        $this->em->method('isOpen')->willReturn(true);
    }

    public function testPersistedEntitiesAreDetachedAfterEachRequest(): void
    {
        $existing = (new RouteData())->setName('app_home')->setEnv('prod')->setSaveAccessRecords(true);
        $this->repo->method('findByRouteAndEnv')->willReturnOnConsecutiveCalls(null, $existing);

        $detached = [];
        $this->em->method('detach')->willReturnCallback(static function (object $entity) use (&$detached): void {
            $detached[] = $entity;
        });

        $service = $this->createService();

        $first = $service->recordMetrics('app_home', 'prod', 0.1, 1, 0.01, null, 1024, 'GET', 200, [], 'req-1');
        $this->assertTrue($first['is_new']);
        $this->assertCount(2, $detached);
        $this->assertInstanceOf(RouteDataRecord::class, $detached[0]);
        $this->assertInstanceOf(RouteData::class, $detached[1]);
        $this->assertSame('app_home', $detached[1]->getName());

        $second = $service->recordMetrics('app_home', 'prod', 0.2, 2, 0.02, null, 2048, 'GET', 200, [], 'req-2');
        $this->assertTrue($second['was_updated']);
        $this->assertCount(4, $detached);
        $this->assertInstanceOf(RouteDataRecord::class, $detached[2]);
        $this->assertNotSame($detached[0], $detached[2]);
        $this->assertSame($existing, $detached[3]);
    }

    public function testRouteDataIsDetachedWhenNoAccessRecordIsCreated(): void
    {
        $existing = (new RouteData())->setName('no_records')->setEnv('prod')->setSaveAccessRecords(false);
        $this->repo->method('findByRouteAndEnv')->willReturn($existing);
        $this->em->expects($this->once())->method('detach')->with($existing);

        $this->createService()->recordMetrics('no_records', 'prod', 0.1, 1, null, null, null, null, 200, [], 'req-3');
    }

    public function testErrorDuringFlushRestoresErrorReporting(): void
    {
        $this->repo->method('findByRouteAndEnv')->willReturn(null);
        $this->em->method('flush')->willThrowException(new Error('driver failure'));
        $this->em->expects($this->never())->method('detach');

        $errorReportingBefore = error_reporting();

        try {
            $this->createService()->recordMetrics('broken', 'prod', 0.1, 1, null, null, null, null, 200, [], 'req-4');
            $this->fail('Error was expected');
        } catch (Error $e) {
            $this->assertSame('driver failure', $e->getMessage());
        }

        $this->assertSame($errorReportingBefore, error_reporting());
    }

    private function createService(): PerformanceMetricsService
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('default')->willReturn($this->em);

        return new PerformanceMetricsService($registry, 'default', false, true, false);
    }
}
