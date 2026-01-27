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

    public function testGetStatisticsByHourReturnsHourlyStatistics(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        // Mock main query for hourly stats
        $mainQuery = $this->createMock(\Doctrine\ORM\Query::class);
        $mainQuery->expects($this->once())
            ->method('getResult')
            ->willReturn([
                ['hour' => 10, 'count' => 5, 'avg_response_time' => 0.5],
                ['hour' => 14, 'count' => 10, 'avg_response_time' => 0.8],
            ]);

        $mainQueryBuilder = $this->createMock(QueryBuilder::class);
        $mainQueryBuilder->expects($this->once())
            ->method('select')
            ->with('HOUR(r.accessedAt) as hour')
            ->willReturnSelf();
        $mainQueryBuilder->expects($this->once())
            ->method('addSelect')
            ->with('COUNT(r.id) as count')
            ->willReturnSelf();
        $mainQueryBuilder->expects($this->once())
            ->method('addSelect')
            ->with('AVG(r.responseTime) as avg_response_time')
            ->willReturnSelf();
        $mainQueryBuilder->expects($this->once())
            ->method('join')
            ->with('r.routeData', 'rd')
            ->willReturnSelf();
        $mainQueryBuilder->expects($this->once())
            ->method('where')
            ->with('rd.env = :env')
            ->willReturnSelf();
        $mainQueryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('env', 'dev')
            ->willReturnSelf();
        $mainQueryBuilder->expects($this->once())
            ->method('groupBy')
            ->with('hour')
            ->willReturnSelf();
        $mainQueryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('hour', 'ASC')
            ->willReturnSelf();
        $mainQueryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($mainQuery);

        // Mock status code query
        $statusCodeQuery = $this->createMock(\Doctrine\ORM\Query::class);
        $statusCodeQuery->expects($this->once())
            ->method('getResult')
            ->willReturn([
                ['hour' => 10, 'status_code' => 200, 'count' => 4],
                ['hour' => 10, 'status_code' => 404, 'count' => 1],
                ['hour' => 14, 'status_code' => 200, 'count' => 8],
                ['hour' => 14, 'status_code' => 500, 'count' => 2],
            ]);

        $statusCodeQueryBuilder = $this->createMock(QueryBuilder::class);
        $statusCodeQueryBuilder->method('select')->willReturnSelf();
        $statusCodeQueryBuilder->method('addSelect')->willReturnSelf();
        $statusCodeQueryBuilder->method('join')->willReturnSelf();
        $statusCodeQueryBuilder->method('where')->willReturnSelf();
        $statusCodeQueryBuilder->method('andWhere')->willReturnSelf();
        $statusCodeQueryBuilder->method('setParameter')->willReturnSelf();
        $statusCodeQueryBuilder->method('groupBy')->willReturnSelf();
        $statusCodeQueryBuilder->method('orderBy')->willReturnSelf();
        $statusCodeQueryBuilder->method('getQuery')->willReturn($statusCodeQuery);

        $repository
            ->expects($this->exactly(2))
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturnOnConsecutiveCalls($mainQueryBuilder, $statusCodeQueryBuilder);

        $startDate = new \DateTimeImmutable('2024-01-01 00:00:00');
        $endDate = new \DateTimeImmutable('2024-01-02 00:00:00');

        $result = $repository->getStatisticsByHour('dev', $startDate, $endDate);

        $this->assertIsArray($result);
        $this->assertCount(24, $result); // Should fill all 24 hours

        // Check that hour 10 has correct data
        $hour10 = array_filter($result, fn($stat) => $stat['hour'] === 10);
        $this->assertNotEmpty($hour10);
        $hour10Data = reset($hour10);
        $this->assertSame(5, $hour10Data['count']);
        $this->assertSame(0.5, $hour10Data['avg_response_time']);
        $this->assertArrayHasKey('status_codes', $hour10Data);
    }

    public function testGetStatisticsByHourWithoutDateFilters(): void
    {
        $repository = $this->getMockBuilder(RouteDataRecordRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn([]);

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
}
