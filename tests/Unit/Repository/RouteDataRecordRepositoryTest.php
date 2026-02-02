<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class RouteDataRecordRepositoryTest extends TestCase
{
    private RouteDataRecordRepository $repository;
    private ManagerRegistry|MockObject $registry;
    private EntityManagerInterface|MockObject $entityManager;
    private QueryBuilder|MockObject $queryBuilder;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);

        $this->repository = new RouteDataRecordRepository($this->registry);
    }

    public function testGetStatisticsByHourAggregatesInPhpAndFillsAllHours(): void
    {
        /** @var RouteDataRecordRepository&MockObject $repository */
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $routeData = new RouteData();

        $record1 = new RouteDataRecord();
        $record1->setRouteData($routeData);
        $record1->setAccessedAt(new \DateTimeImmutable('2024-01-01 10:15:00'));
        $record1->setResponseTime(0.5);
        $record1->setStatusCode(200);

        $record2 = new RouteDataRecord();
        $record2->setRouteData($routeData);
        $record2->setAccessedAt(new \DateTimeImmutable('2024-01-01 10:45:00'));
        $record2->setResponseTime(0.7);
        $record2->setStatusCode(404);

        $record3 = new RouteDataRecord();
        $record3->setRouteData($routeData);
        $record3->setAccessedAt(new \DateTimeImmutable('2024-01-01 14:00:00'));
        $record3->setResponseTime(1.0);
        $record3->setStatusCode(500);

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$record1, $record2, $record3]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($qb);

        $startDate = new \DateTimeImmutable('2024-01-01 00:00:00');
        $endDate = new \DateTimeImmutable('2024-01-02 00:00:00');

        $result = $repository->getStatisticsByHour('dev', $startDate, $endDate);

        $this->assertIsArray($result);
        $this->assertCount(24, $result); // 0-23

        $hour10 = array_values(array_filter($result, static fn ($stat) => $stat['hour'] === 10))[0];
        $this->assertSame(2, $hour10['count']);
        $this->assertEqualsWithDelta((0.5 + 0.7) / 2, $hour10['avg_response_time'], 0.0001);
        $this->assertEquals([200 => 1, 404 => 1], $hour10['status_codes']);

        $hour14 = array_values(array_filter($result, static fn ($stat) => $stat['hour'] === 14))[0];
        $this->assertSame(1, $hour14['count']);
        $this->assertSame(1.0, $hour14['avg_response_time']);
        $this->assertEquals([500 => 1], $hour14['status_codes']);
    }

    public function testGetStatisticsByHourWithRouteNameAndStatusCodeFilters(): void
    {
        /** @var RouteDataRecordRepository&MockObject $repository */
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $routeData = new RouteData();
        $routeData->setName('api_foo')->setEnv('dev');

        $record = new RouteDataRecord();
        $record->setRouteData($routeData);
        $record->setAccessedAt(new \DateTimeImmutable('2024-01-01 09:30:00'));
        $record->setResponseTime(0.3);
        $record->setStatusCode(200);

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn([$record]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->expects($this->exactly(4))->method('andWhere')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-02');
        $result = $repository->getStatisticsByHour('dev', $startDate, $endDate, 'api_foo', 200);

        $this->assertIsArray($result);
        $this->assertCount(24, $result);
    }

    public function testGetStatisticsByHourWithoutDataStillReturnsAllHours(): void
    {
        /** @var RouteDataRecordRepository&MockObject $repository */
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $result = $repository->getStatisticsByHour('dev');

        $this->assertIsArray($result);
        $this->assertCount(24, $result);
        foreach ($result as $i => $stat) {
            $this->assertSame($i, $stat['hour']);
            $this->assertSame(0, $stat['count']);
            $this->assertSame(0.0, $stat['avg_response_time']);
        }
    }

    public function testGetStatisticsByDayOfWeekAggregatesCorrectly(): void
    {
        /** @var RouteDataRecordRepository&MockObject $repository */
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $routeData = new RouteData();

        // Monday
        $record1 = new RouteDataRecord();
        $record1->setRouteData($routeData);
        $record1->setAccessedAt(new \DateTimeImmutable('2024-01-01 10:00:00')); // Monday
        $record1->setResponseTime(0.5);
        $record1->setStatusCode(200);

        // Wednesday
        $record2 = new RouteDataRecord();
        $record2->setRouteData($routeData);
        $record2->setAccessedAt(new \DateTimeImmutable('2024-01-03 12:00:00')); // Wednesday
        $record2->setResponseTime(1.0);
        $record2->setStatusCode(500);

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn([$record1, $record2]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $result = $repository->getStatisticsByDayOfWeek('dev');

        $this->assertCount(7, $result);

        $monday = $result[1];
        $this->assertSame(1, $monday['count']);
        $this->assertSame('Monday', $monday['day_name']);
        $this->assertEquals([200 => 1], $monday['status_codes']);

        $wednesday = $result[3];
        $this->assertSame(1, $wednesday['count']);
        $this->assertSame('Wednesday', $wednesday['day_name']);
        $this->assertEquals([500 => 1], $wednesday['status_codes']);
    }

    public function testGetStatisticsByMonthAggregatesCorrectly(): void
    {
        /** @var RouteDataRecordRepository&MockObject $repository */
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $routeData = new RouteData();

        $record1 = new RouteDataRecord();
        $record1->setRouteData($routeData);
        $record1->setAccessedAt(new \DateTimeImmutable('2024-01-10 10:00:00')); // January
        $record1->setResponseTime(0.5);
        $record1->setStatusCode(200);

        $record2 = new RouteDataRecord();
        $record2->setRouteData($routeData);
        $record2->setAccessedAt(new \DateTimeImmutable('2024-02-10 10:00:00')); // February
        $record2->setResponseTime(1.0);
        $record2->setStatusCode(500);

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn([$record1, $record2]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $result = $repository->getStatisticsByMonth('dev');

        $this->assertCount(12, $result);
        $this->assertSame('January', $result[0]['month_name']);
        $this->assertSame(1, $result[0]['count']);
        $this->assertSame('February', $result[1]['month_name']);
        $this->assertSame(1, $result[1]['count']);
    }

    public function testGetHeatmapDataAggregatesCorrectly(): void
    {
        /** @var RouteDataRecordRepository&MockObject $repository */
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $routeData = new RouteData();

        $record1 = new RouteDataRecord();
        $record1->setRouteData($routeData);
        $record1->setAccessedAt(new \DateTimeImmutable('2024-01-01 10:00:00')); // Monday 10:00

        $record2 = new RouteDataRecord();
        $record2->setRouteData($routeData);
        $record2->setAccessedAt(new \DateTimeImmutable('2024-01-01 10:30:00')); // Monday 10:xx

        $record3 = new RouteDataRecord();
        $record3->setRouteData($routeData);
        $record3->setAccessedAt(new \DateTimeImmutable('2024-01-03 14:00:00')); // Wednesday 14:00

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn([$record1, $record2, $record3]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $heatmap = $repository->getHeatmapData('dev');

        $this->assertCount(7, $heatmap);
        $this->assertCount(24, $heatmap[0]); // Sunday row

        // Monday is 1
        $this->assertSame(2, $heatmap[1][10]); // two hits at Monday 10h
        // Wednesday is 3
        $this->assertSame(1, $heatmap[3][14]);
    }

    public function testGetTotalAccessCountReturnsCount(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('42');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('select')
            ->with('COUNT(r.id)')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('join')
            ->with('r.routeData', 'rd')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('rd.env = :env')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('env', 'dev')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($queryBuilder);

        $result = $repository->getTotalAccessCount('dev');

        $this->assertSame(42, $result);
    }

    public function testGetTotalAccessCountWithDateFilters(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('15');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('join')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(3))
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-02');

        $result = $repository->getTotalAccessCount('dev', $startDate, $endDate);

        $this->assertSame(15, $result);
    }

    public function testDeleteByRouteDataReturnsDeletedCount(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('execute')
            ->willReturn(5);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('delete')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('r.routeData = :routeDataId')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('routeDataId', 123)
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($queryBuilder);

        $result = $repository->deleteByRouteData(123);

        $this->assertSame(5, $result);
    }

    public function testFindOneByRequestIdCallsFindOneByWithCorrectCriteria(): void
    {
        $existingRecord = new RouteDataRecord();
        $existingRecord->setRequestId('req-xyz');

        /** @var RouteDataRecordRepository&MockObject $repository */
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['requestId' => 'req-xyz'], ['id' => 'ASC'])
            ->willReturn($existingRecord);

        $result = $repository->findOneByRequestId('req-xyz');

        $this->assertSame($existingRecord, $result);
    }

    public function testFindOneByRequestIdReturnsNullWhenNoRecord(): void
    {
        /** @var RouteDataRecordRepository&MockObject $repository */
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['requestId' => 'req-nonexistent'], ['id' => 'ASC'])
            ->willReturn(null);

        $result = $repository->findOneByRequestId('req-nonexistent');

        $this->assertNull($result);
    }

    public function testDeleteByEnvironmentReturnsDeletedCount(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('execute')
            ->willReturn(10);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('delete')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('join')
            ->with('r.routeData', 'rd')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('rd.env = :env')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('env', 'dev')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($queryBuilder);

        $result = $repository->deleteByEnvironment('dev');

        $this->assertSame(10, $result);
    }

    public function testCountByRouteDataReturnsCount(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home')->setEnv('dev');

        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('7');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('select')
            ->with('COUNT(r.id)')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('r.routeData = :routeData')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('routeData', $routeData)
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($queryBuilder);

        $result = $repository->countByRouteData($routeData);

        $this->assertSame(7, $result);
    }

    public function testGetAggregatesForRouteDataIdsReturnsEmptyWhenEmptyInput(): void
    {
        $result = $this->repository->getAggregatesForRouteDataIds([]);
        $this->assertSame([], $result);
    }

    public function testDeleteByFilterReturnsZeroWhenNoRecordsMatch(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getSingleColumnResult')
            ->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->expects($this->once())->method('createQueryBuilder')->willReturn($qb);

        $result = $repository->deleteByFilter('dev', null, null, null, null, null, null, null, null, null, null, 1000);

        $this->assertSame(0, $result);
    }

    public function testGetPaginatedRecordsReturnsStructure(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $countQuery = $this->createMock(\Doctrine\ORM\Query::class);
        $countQuery->method('getSingleScalarResult')->willReturn(0);
        $recordsQuery = $this->createMock(\Doctrine\ORM\Query::class);
        $recordsQuery->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('join')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('select')->willReturnSelf();
        $qb->method('setFirstResult')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $getQueryCallCount = 0;
        $qb->method('getQuery')->willReturnCallback(function () use (&$getQueryCallCount, $countQuery, $recordsQuery) {
            ++$getQueryCallCount;
            return 1 === $getQueryCallCount ? $countQuery : $recordsQuery;
        });

        $repository->method('createQueryBuilder')->willReturn($qb);

        $result = $repository->getPaginatedRecords('dev', 1, 50);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('records', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('total_pages', $result);
        $this->assertSame(1, $result['page']);
        $this->assertSame(50, $result['per_page']);
        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['total_pages']);
        $this->assertIsArray($result['records']);
        $this->assertSame([], $result['records']);
    }

    public function testGetRecordsForExportReturnsStructure(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getSingleScalarResult')->willReturn(0);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('join')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('select')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $result = $repository->getRecordsForExport('dev');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('records', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertSame([], $result['records']);
        $this->assertSame(0, $result['total']);
    }
}
