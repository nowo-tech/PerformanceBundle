<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Advanced tests for RouteDataRecordRepository.
 */
final class RouteDataRecordRepositoryAdvancedTest extends TestCase
{
    private ManagerRegistry|MockObject $registry;
    private EntityManagerInterface|MockObject $entityManager;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    public function testGetStatisticsByHourWithoutDateFilters(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn([
            ['hour' => 10, 'count' => 5, 'avg_response_time' => 0.5],
            ['hour' => 14, 'count' => 10, 'avg_response_time' => 0.8],
        ]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('addSelect')->willReturnSelf();
        $queryBuilder->method('join')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('groupBy')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->getStatisticsByHour('dev');

        $this->assertIsArray($result);
        $this->assertCount(24, $result); // Should fill all 24 hours
    }

    public function testGetStatisticsByHourWithDateFilters(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $startDate = new \DateTimeImmutable('2026-01-01');
        $endDate = new \DateTimeImmutable('2026-01-31');

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('addSelect')->willReturnSelf();
        $queryBuilder->method('join')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnCallback(function ($condition) {
                return in_array($condition, [
                    'r.accessedAt >= :startDate',
                    'r.accessedAt <= :endDate',
                ], true) ? $queryBuilder : null;
            });
        $queryBuilder->method('groupBy')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->getStatisticsByHour('dev', $startDate, $endDate);

        $this->assertIsArray($result);
        $this->assertCount(24, $result);
    }

    public function testGetTotalAccessCountWithoutDateFilters(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('100');

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
        $queryBuilder->expects($this->never())
            ->method('andWhere');
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($queryBuilder);

        $result = $repository->getTotalAccessCount('dev');

        $this->assertSame(100, $result);
    }

    public function testGetTotalAccessCountWithDateFilters(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $startDate = new \DateTimeImmutable('2026-01-01');
        $endDate = new \DateTimeImmutable('2026-01-31');

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getSingleScalarResult')->willReturn('50');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('join')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnCallback(function ($condition) {
                return in_array($condition, [
                    'r.accessedAt >= :startDate',
                    'r.accessedAt <= :endDate',
                ], true) ? $queryBuilder : null;
            });
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->getTotalAccessCount('dev', $startDate, $endDate);

        $this->assertSame(50, $result);
    }

    public function testDeleteByRouteData(): void
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
            ->with('routeDataId', 1)
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($queryBuilder);

        $result = $repository->deleteByRouteData(1);

        $this->assertSame(5, $result);
    }

    public function testDeleteByEnvironment(): void
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

    public function testGetRecordsForExportReturnsRecordsAndTotal(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $routeData = new \Nowo\PerformanceBundle\Entity\RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $record = new \Nowo\PerformanceBundle\Entity\RouteDataRecord();
        $record->setRouteData($routeData);
        $record->setAccessedAt(new \DateTimeImmutable('2026-01-15 12:00:00'));
        $record->setStatusCode(200);
        $record->setResponseTime(0.1);
        $record->setTotalQueries(5);
        $record->setQueryTime(0.05);
        $record->setMemoryUsage(1024);

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getSingleScalarResult')->willReturn('1');
        $query->method('getResult')->willReturn([$record]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('join')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('select')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $result = $repository->getRecordsForExport('dev', null, null, null, null, null, null, null, null, 100);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('records', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['records']);
        $this->assertSame('app_home', $result['records'][0]->getRouteData()?->getName());
        $this->assertSame(200, $result['records'][0]->getStatusCode());
        $this->assertSame(0.1, $result['records'][0]->getResponseTime());
    }

    public function testGetPaginatedRecordsReturnsStructureAndRecords(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $routeData = new \Nowo\PerformanceBundle\Entity\RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $record = new \Nowo\PerformanceBundle\Entity\RouteDataRecord();
        $record->setRouteData($routeData);
        $record->setAccessedAt(new \DateTimeImmutable('2026-01-15 12:00:00'));
        $record->setStatusCode(200);
        $record->setResponseTime(0.1);

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getSingleScalarResult')->willReturn('1');
        $query->method('getResult')->willReturn([$record]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('select')->willReturnSelf();
        $qb->method('setFirstResult')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $result = $repository->getPaginatedRecords('dev', 1, 10, null, null, null, null, null, null, null, null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('records', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('total_pages', $result);
        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(10, $result['per_page']);
        $this->assertSame(1, $result['total_pages']);
        $this->assertCount(1, $result['records']);
        $this->assertSame('app_home', $result['records'][0]->getRouteData()?->getName());
    }

    public function testGetRecordsForExportWithFiltersAndLimit(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $start = new \DateTimeImmutable('2026-01-01');
        $end = new \DateTimeImmutable('2026-01-31');

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getSingleScalarResult')->willReturn('100');
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('join')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('select')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $result = $repository->getRecordsForExport('prod', $start, $end, 'api_foo', 404, null, null, null, null, 500);

        $this->assertSame(100, $result['total']);
        $this->assertSame([], $result['records']);
    }

    public function testGetPaginatedRecordsWithQueryTimeAndMemoryFilters(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getSingleScalarResult')->willReturn('0');
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('select')->willReturnSelf();
        $qb->method('setFirstResult')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $result = $repository->getPaginatedRecords(
            'prod',
            1,
            20,
            null,
            null,
            null,
            null,
            0.1,
            5.0,
            1024 * 1024,
            50 * 1024 * 1024
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('records', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertSame(0, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(20, $result['per_page']);
    }

    public function testDeleteByFilterWithQueryTimeAndMemoryFilters(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getSingleColumnResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $deleted = $repository->deleteByFilter(
            'dev',
            null,
            null,
            null,
            null,
            0.05,
            3.0,
            2 * 1024 * 1024,
            100 * 1024 * 1024,
            500
        );

        $this->assertSame(0, $deleted);
    }
}
