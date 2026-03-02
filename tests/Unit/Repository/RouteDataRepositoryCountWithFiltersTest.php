<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Repository;

use DateTimeImmutable;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RouteDataRepository::countWithFilters() method.
 */
final class RouteDataRepositoryCountWithFiltersTest extends TestCase
{
    private ManagerRegistry $registry;
    private RouteDataRepository $repository;

    protected function setUp(): void
    {
        $this->registry   = $this->createMock(ManagerRegistry::class);
        $this->repository = new RouteDataRepository($this->registry);
    }

    public function testCountWithFiltersNoFilters(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('3');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('select')
            ->with('COUNT(r.id)')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('r.env = :env')
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

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->countWithFilters('dev', []);

        $this->assertSame(3, $result);
    }

    public function testCountWithFiltersRouteNames(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn('2');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('r.name IN (:route_names)')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->countWithFilters('dev', ['route_names' => ['app_home', 'app_about']]);

        $this->assertSame(2, $result);
    }

    public function testCountWithFiltersRouteNamePattern(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn('5');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('r.name LIKE :route_pattern')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnCallback(function ($key, $value) use ($queryBuilder) {
                if ($key === 'route_pattern') {
                    $this->assertSame('%api%', $value);
                }

                return $queryBuilder;
            });
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->countWithFilters('dev', ['route_name_pattern' => 'api']);

        $this->assertSame(5, $result);
    }

    public function testCountWithFiltersDateRange(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $dateFrom = new DateTimeImmutable('2025-01-01');
        $dateTo   = new DateTimeImmutable('2025-01-31');

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn('10');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(3))
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->countWithFilters('dev', [
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ]);

        $this->assertSame(10, $result);
    }

    public function testCountWithFiltersCombinedFilters(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $dateFrom = new DateTimeImmutable('2025-01-01');

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn('7');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(3))
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->countWithFilters('prod', [
            'route_names' => ['route1', 'route2'],
            'date_from'   => $dateFrom,
        ]);

        $this->assertSame(7, $result);
    }

    public function testCountWithFiltersDateToOnly(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $dateTo = new DateTimeImmutable('2025-12-31');

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn('4');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('r.createdAt <= :date_to')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->countWithFilters('dev', ['date_to' => $dateTo]);

        $this->assertSame(4, $result);
    }

    public function testCountWithFiltersDateFromOnly(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $dateFrom = new DateTimeImmutable('2026-01-01');

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn('6');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('r.createdAt >= :date_from')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->countWithFilters('prod', ['date_from' => $dateFrom]);

        $this->assertSame(6, $result);
    }

    public function testCountWithFiltersWithEmptyEnv(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn('0');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('env', '')
            ->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->countWithFilters('', []);

        $this->assertSame(0, $result);
    }

    public function testCountWithFiltersWithStageEnv(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn('2');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('env', 'stage')
            ->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->countWithFilters('stage', []);

        $this->assertSame(2, $result);
    }
}
