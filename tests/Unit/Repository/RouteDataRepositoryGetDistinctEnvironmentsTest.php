<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Repository;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RouteDataRepository::getDistinctEnvironments() method.
 */
final class RouteDataRepositoryGetDistinctEnvironmentsTest extends TestCase
{
    private ManagerRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
    }

    public function testGetDistinctEnvironmentsReturnsArray(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([['env' => 'dev'], ['env' => 'prod'], ['env' => 'test']]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('select')->with('DISTINCT r.env')->willReturnSelf();
        $qb->expects($this->once())->method('where')->with('r.env IS NOT NULL')->willReturnSelf();
        $qb->expects($this->once())->method('orderBy')->with('r.env', 'ASC')->willReturnSelf();
        $qb->expects($this->once())->method('getQuery')->willReturn($query);

        $repository->expects($this->once())->method('createQueryBuilder')->with('r')->willReturn($qb);

        $result = $repository->getDistinctEnvironments();

        $this->assertIsArray($result);
        $this->assertSame(['dev', 'prod', 'test'], $result);
    }

    public function testGetDistinctEnvironmentsReturnsEmptyWhenNoEnvs(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $result = $repository->getDistinctEnvironments();

        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    public function testGetDistinctEnvironmentsReturnsSingleEnvironment(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([['env' => 'prod']]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $result = $repository->getDistinctEnvironments();

        $this->assertIsArray($result);
        $this->assertSame(['prod'], $result);
    }

    public function testGetDistinctEnvironmentsReturnsStageAndTest(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([['env' => 'stage'], ['env' => 'test']]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $result = $repository->getDistinctEnvironments();

        $this->assertSame(['stage', 'test'], $result);
    }
}
