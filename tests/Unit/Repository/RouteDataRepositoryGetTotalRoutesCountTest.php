<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Repository;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RouteDataRepository::getTotalRoutesCount() method.
 */
final class RouteDataRepositoryGetTotalRoutesCountTest extends TestCase
{
    private ManagerRegistry $registry;
    private RouteDataRepository $repository;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->repository = new RouteDataRepository($this->registry);
    }

    public function testGetTotalRoutesCountReturnsInteger(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('15');

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
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->getTotalRoutesCount('dev');

        $this->assertSame(15, $result);
        $this->assertIsInt($result);
    }

    public function testGetTotalRoutesCountReturnsZeroWhenNoRoutes(): void
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
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->getTotalRoutesCount('prod');

        $this->assertSame(0, $result);
    }

    public function testGetTotalRoutesCountCastsStringToInt(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn('42');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->getTotalRoutesCount('test');

        $this->assertSame(42, $result);
        $this->assertIsInt($result);
    }
}
