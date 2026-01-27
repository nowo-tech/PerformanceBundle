<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class RouteDataRepositoryTest extends TestCase
{
    private RouteDataRepository $repository;
    private ManagerRegistry|MockObject $registry;
    private EntityManagerInterface|MockObject $entityManager;
    private QueryBuilder|MockObject $queryBuilder;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);

        $this->repository = new RouteDataRepository($this->registry);
    }

    public function testGetRankingByRequestTimeReturnsPosition(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');
        $routeData->setRequestTime(0.5);

        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['findByRouteAndEnv', 'createQueryBuilder'])
            ->getMock();

        $repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('2'); // 2 routes have higher request time (returned as string)

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('select')
            ->with('COUNT(r.id)')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('r.env = :env')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($queryBuilder);

        $result = $repository->getRankingByRequestTime('app_home', 'dev');

        $this->assertSame(3, $result); // Position 3 (2 + 1)
    }

    public function testGetRankingByRequestTimeReturnsNullWhenRouteNotFound(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['findByRouteAndEnv'])
            ->getMock();

        $repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $result = $repository->getRankingByRequestTime('app_home', 'dev');

        $this->assertNull($result);
    }

    public function testGetRankingByRequestTimeReturnsNullWhenRequestTimeIsNull(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');
        $routeData->setRequestTime(null);

        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['findByRouteAndEnv'])
            ->getMock();

        $repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        $result = $repository->getRankingByRequestTime('app_home', 'dev');

        $this->assertNull($result);
    }

    public function testGetRankingByQueryCountReturnsPosition(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');
        $routeData->setTotalQueries(10);

        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['findByRouteAndEnv', 'createQueryBuilder'])
            ->getMock();

        $repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('1'); // 1 route has higher query count (returned as string)

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('select')
            ->with('COUNT(r.id)')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('r.env = :env')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($queryBuilder);

        $result = $repository->getRankingByQueryCount('app_home', 'dev');

        $this->assertSame(2, $result); // Position 2 (1 + 1)
    }

    public function testGetRankingByQueryCountReturnsNullWhenRouteNotFound(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['findByRouteAndEnv'])
            ->getMock();

        $repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $result = $repository->getRankingByQueryCount('app_home', 'dev');

        $this->assertNull($result);
    }

    public function testGetTotalRoutesCount(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('5'); // Returned as string

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

        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($queryBuilder);

        $result = $repository->getTotalRoutesCount('dev');

        $this->assertSame(5, $result);
    }

    public function testFindByRouteAndEnvReturnsRouteData(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn($routeData);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('r.name = :name')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('r.env = :env')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnCallback(function ($key, $value) {
                return in_array($key, ['name', 'env'], true) ? $this->queryBuilder : null;
            });
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($queryBuilder);

        $result = $repository->findByRouteAndEnv('app_home', 'dev');

        $this->assertSame($routeData, $result);
    }

    public function testFindByRouteAndEnvReturnsNullWhenNotFound(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->findByRouteAndEnv('non_existent', 'dev');

        $this->assertNull($result);
    }

    public function testGetRankingByRequestTimeAcceptsRouteDataObject(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');
        $routeData->setRequestTime(0.5);

        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('0');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->getRankingByRequestTime($routeData, 'dev');

        $this->assertSame(1, $result);
    }

    public function testGetRankingByQueryCountAcceptsRouteDataObject(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');
        $routeData->setTotalQueries(10);

        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('0');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $repository->getRankingByQueryCount($routeData, 'dev');

        $this->assertSame(1, $result);
    }

    public function testFindAllForStatistics(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $routeData1 = new RouteData();
        $routeData2 = new RouteData();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$routeData1, $routeData2]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
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

        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($queryBuilder);

        $result = $repository->findAllForStatistics('dev');

        $this->assertCount(2, $result);
        $this->assertSame($routeData1, $result[0]);
        $this->assertSame($routeData2, $result[1]);
    }

    public function testDeleteAllWithoutEnvironment(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
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
        $queryBuilder->expects($this->never())
            ->method('where');
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($queryBuilder);

        $result = $repository->deleteAll(null);

        $this->assertSame(5, $result);
    }

    public function testDeleteAllWithEnvironment(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('execute')
            ->willReturn(3);

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

        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($queryBuilder);

        $result = $repository->deleteAll('dev');

        $this->assertSame(3, $result);
    }

    public function testMarkAsReviewedReturnsTrueWhenFound(): void
    {
        $routeData = new RouteData();
        $routeData->setId(1);
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('flush');

        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['find', 'getEntityManager'])
            ->getMock();

        $repository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($routeData);

        $repository
            ->expects($this->once())
            ->method('getEntityManager')
            ->willReturn($entityManager);

        $result = $repository->markAsReviewed(1, true, false, 'admin');

        $this->assertTrue($result);
        $this->assertTrue($routeData->isReviewed());
        $this->assertTrue($routeData->getQueriesImproved());
        $this->assertFalse($routeData->getTimeImproved());
        $this->assertSame('admin', $routeData->getReviewedBy());
    }

    public function testMarkAsReviewedReturnsFalseWhenNotFound(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['find'])
            ->getMock();

        $repository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $result = $repository->markAsReviewed(999);

        $this->assertFalse($result);
    }

    public function testDeleteByIdReturnsTrueWhenFound(): void
    {
        $routeData = new RouteData();
        $routeData->setId(1);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('remove')
            ->with($routeData);
        $entityManager->expects($this->once())
            ->method('flush');

        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['find', 'getEntityManager'])
            ->getMock();

        $repository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($routeData);

        $repository
            ->expects($this->once())
            ->method('getEntityManager')
            ->willReturn($entityManager);

        $result = $repository->deleteById(1);

        $this->assertTrue($result);
    }

    public function testDeleteByIdReturnsFalseWhenNotFound(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['find'])
            ->getMock();

        $repository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $result = $repository->deleteById(999);

        $this->assertFalse($result);
    }
}
