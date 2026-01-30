<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Repository;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RouteDataRepository::findByRouteAndEnv() method.
 */
final class RouteDataRepositoryFindByRouteAndEnvTest extends TestCase
{
    private ManagerRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
    }

    public function testFindByRouteAndEnvReturnsRouteWhenFound(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $route = new RouteData();
        $route->setName('app_home')->setEnv('dev');

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('getOneOrNullResult')->willReturn($route);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('where')->with('r.name = :name')->willReturnSelf();
        $qb->expects($this->once())->method('andWhere')->with('r.env = :env')->willReturnSelf();
        $qb->expects($this->exactly(2))->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->expects($this->once())->method('createQueryBuilder')->with('r')->willReturn($qb);

        $result = $repository->findByRouteAndEnv('app_home', 'dev');

        $this->assertSame($route, $result);
    }

    public function testFindByRouteAndEnvReturnsNullWhenNotFound(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('getOneOrNullResult')->willReturn(null);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $result = $repository->findByRouteAndEnv('missing', 'prod');

        $this->assertNull($result);
    }

    public function testFindByRouteAndEnvCallsQueryBuilderWithCorrectAlias(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn(null);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($qb);

        $repository->findByRouteAndEnv('app_home', 'dev');
    }

    public function testFindByRouteAndEnvCallsSetParameterTwice(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn(null);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->expects($this->exactly(2))->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $result = $repository->findByRouteAndEnv('api_users', 'prod');

        $this->assertNull($result);
    }
}
