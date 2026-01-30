<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for RouteDataRepository::deleteAll() method.
 */
final class RouteDataRepositoryDeleteAllTest extends TestCase
{
    private ManagerRegistry|MockObject $registry;
    private RouteDataRepository $repository;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->repository = new RouteDataRepository($this->registry);
    }

    public function testDeleteAllWithoutEnvDeletesAll(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('execute')
            ->willReturn(10);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('delete')
            ->willReturnSelf();
        $queryBuilder->expects($this->never())
            ->method('where');
        $queryBuilder->expects($this->never())
            ->method('setParameter');
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->deleteAll(null);

        $this->assertSame(10, $result);
    }

    public function testDeleteAllWithEnvDeletesOnlyThatEnv(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('execute')
            ->willReturn(5);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('delete')
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

        $result = $repository->deleteAll('dev');

        $this->assertSame(5, $result);
    }

    public function testDeleteAllReturnsZeroWhenNoRecords(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('execute')
            ->willReturn(0);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('delete')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->deleteAll('prod');

        $this->assertSame(0, $result);
    }

    public function testDeleteAllWithEmptyStringEnv(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->method('execute')->willReturn(3);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('delete')->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('r.env = :env')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('env', '')
            ->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->deleteAll('');

        $this->assertSame(3, $result);
    }

    public function testDeleteAllWithStageEnv(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->method('execute')->willReturn(2);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('delete')->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('r.env = :env')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('env', 'stage')
            ->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->deleteAll('stage');

        $this->assertSame(2, $result);
    }
}
