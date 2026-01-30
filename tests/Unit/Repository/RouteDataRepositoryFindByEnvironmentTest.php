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
 * Tests for RouteDataRepository::findByEnvironment() method.
 */
final class RouteDataRepositoryFindByEnvironmentTest extends TestCase
{
    private ManagerRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
    }

    public function testFindByEnvironmentReturnsArray(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $r1 = new RouteData();
        $r1->setName('r1')->setEnv('dev');
        $r2 = new RouteData();
        $r2->setName('r2')->setEnv('dev');

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('getResult')->willReturn([$r1, $r2]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('where')->with('r.env = :env')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->with('env', 'dev')->willReturnSelf();
        $qb->expects($this->once())->method('orderBy')->with('r.lastAccessedAt', 'DESC')->willReturnSelf();
        $qb->expects($this->once())->method('getQuery')->willReturn($query);

        $repository->expects($this->once())->method('createQueryBuilder')->with('r')->willReturn($qb);

        $result = $repository->findByEnvironment('dev');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(RouteData::class, $result);
    }

    public function testFindByEnvironmentReturnsEmptyArrayWhenNoRoutes(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $result = $repository->findByEnvironment('prod');

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testFindByEnvironmentFiltersByEnvParameter(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('env', 'stage')
            ->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $repository->findByEnvironment('stage');
    }

    public function testFindByEnvironmentWithEmptyEnv(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('env', '')
            ->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $result = $repository->findByEnvironment('');

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }
}
