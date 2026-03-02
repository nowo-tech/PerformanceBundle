<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Repository;

use DateTimeImmutable;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RouteDataRepository::findWithFilters() method.
 */
final class RouteDataRepositoryFindWithFiltersTest extends TestCase
{
    private ManagerRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
    }

    public function testFindWithFiltersNoFiltersReturnsArray(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $r1 = new RouteData();
        $r1->setName('r1')->setEnv('dev');

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('getResult')->willReturn([$r1]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('where')->with('r.env = :env')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->with('env', 'dev')->willReturnSelf();
        $qb->expects($this->once())->method('orderBy')->with('r.lastAccessedAt', 'DESC')->willReturnSelf();
        $qb->expects($this->never())->method('setMaxResults');
        $qb->expects($this->once())->method('getQuery')->willReturn($query);

        $repository->expects($this->once())->method('createQueryBuilder')->with('r')->willReturn($qb);

        $result = $repository->findWithFilters('dev', []);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertContainsOnlyInstancesOf(RouteData::class, $result);
    }

    public function testFindWithFiltersWithLimitCallsSetMaxResults(): void
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
        $qb->expects($this->once())->method('setMaxResults')->with(50)->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $repository->findWithFilters('dev', [], 'requestTime', 'DESC', 50);
    }

    public function testFindWithFiltersReturnsEmptyWhenNoRoutes(): void
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

        $result = $repository->findWithFilters('prod', []);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testFindWithFiltersSortByNameAsc(): void
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
        $qb->expects($this->once())->method('orderBy')->with('r.name', 'ASC')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $repository->findWithFilters('dev', [], 'name', 'ASC');
    }

    public function testFindWithFiltersInvalidSortByFallsBackToLastAccessedAt(): void
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
        $qb->expects($this->once())->method('orderBy')->with('r.lastAccessedAt', 'DESC')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $repository->findWithFilters('dev', [], 'requestTime', 'DESC');
    }

    public function testFindWithFiltersWithLimitZeroDoesNotCallSetMaxResults(): void
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
        $qb->expects($this->never())->method('setMaxResults');
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $repository->findWithFilters('dev', [], 'requestTime', 'DESC', 0);
    }

    public function testFindWithFiltersWithRouteNamesFilter(): void
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
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('r.name IN (:route_names)')
            ->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $repository->findWithFilters('dev', ['route_names' => ['app_home', 'api_foo']]);
    }

    public function testFindWithFiltersWithDateFromFilter(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $dateFrom = new DateTimeImmutable('2026-01-01');

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('r.createdAt >= :date_from')
            ->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $repository->findWithFilters('dev', ['date_from' => $dateFrom]);
    }

    public function testFindWithFiltersWithDateToFilter(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $dateTo = new DateTimeImmutable('2026-12-31');

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('r.createdAt <= :date_to')
            ->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $repository->findWithFilters('dev', ['date_to' => $dateTo]);
    }

    public function testFindWithFiltersWithRouteNamePatternFilter(): void
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
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('r.name LIKE :route_pattern')
            ->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $repository->findWithFilters('dev', ['route_name_pattern' => 'api']);
    }

    public function testFindWithFiltersSortByCreatedAtAsc(): void
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
        $qb->expects($this->once())->method('orderBy')->with('r.createdAt', 'ASC')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $repository->findWithFilters('dev', [], 'createdAt', 'ASC');
    }

    public function testFindWithFiltersOrderLowercaseAscProducesAsc(): void
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
        $qb->expects($this->once())->method('orderBy')->with('r.name', 'ASC')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $repository->findWithFilters('dev', [], 'name', 'asc');
    }

    public function testFindWithFiltersWithCombinedRouteNamesAndDateFrom(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $dateFrom = new DateTimeImmutable('2026-06-01');

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($qb);

        $repository->findWithFilters('dev', [
            'route_names' => ['api_foo', 'api_bar'],
            'date_from'   => $dateFrom,
        ]);
    }

    public function testFindWithFiltersWithEmptyEnv(): void
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

        $result = $repository->findWithFilters('', []);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }
}
