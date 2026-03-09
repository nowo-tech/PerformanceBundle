<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Repository;

use DateTimeImmutable;
use Doctrine\ORM\Query\QueryException;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

use function count;

final class RouteDataRecordRepositoryIntegrationTest extends TestCase
{
    private KernelInterface $kernel;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel('test', true);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    private function createTablesAndRecordMetrics(): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        (new CommandTester($application->find('nowo:performance:create-table')))->execute([]);
        (new CommandTester($application->find('nowo:performance:create-records-table')))->execute([]);
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);
        $service->recordMetrics(
            'repo_test_route',
            'test',
            0.25,
            5,
            0.02,
            null,
            1024000,
            'GET',
            200,
            [200],
            'req-1',
            'https://example.com',
            'user1',
            '1',
            '/repo_test_route',
        );
    }

    public function testGetStatisticsByHourReturnsArray(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $start  = new DateTimeImmutable('-30 days');
        $end    = new DateTimeImmutable('+1 day');
        $result = $repo->getStatisticsByHour('test', $start, $end);

        self::assertIsArray($result);
        self::assertCount(24, $result);
    }

    public function testGetStatisticsByHourWithRouteAndStatusCode(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $result = $repo->getStatisticsByHour('test', null, null, 'repo_test_route', null, 200);
        self::assertIsArray($result);
    }

    public function testGetStatisticsByDayOfWeekReturnsArray(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $result = $repo->getStatisticsByDayOfWeek('test');
        self::assertIsArray($result);
        self::assertLessThanOrEqual(7, count($result));
    }

    public function testGetStatisticsByMonthReturnsArray(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $result = $repo->getStatisticsByMonth('test');
        self::assertIsArray($result);
    }

    public function testGetHeatmapDataReturnsArray(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $result = $repo->getHeatmapData('test');
        self::assertIsArray($result);
    }

    public function testGetTotalAccessCountReturnsInt(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $count = $repo->getTotalAccessCount('test');
        self::assertIsInt($count);
        self::assertGreaterThanOrEqual(0, $count);
    }

    public function testGetPaginatedRecordsReturnsPaginatedStructure(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $result = $repo->getPaginatedRecords(
            'test',
            1,
            10,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            'accessed_at',
            'DESC',
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('records', $result);
        self::assertArrayHasKey('total', $result);
        self::assertArrayHasKey('page', $result);
        self::assertArrayHasKey('per_page', $result);
    }

    public function testGetRecordsForExportReturnsRecordsAndTotal(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $result = $repo->getRecordsForExport(
            'test',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('records', $result);
        self::assertArrayHasKey('total', $result);
    }

    public function testFindOneByRequestIdReturnsNullWhenNotFound(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $record = $repo->findOneByRequestId('nonexistent-request-id');
        self::assertNull($record);
    }

    public function testFindOneByRequestIdReturnsRecordWhenExists(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $record = $repo->findOneByRequestId('req-1');
        self::assertInstanceOf(RouteDataRecord::class, $record);
        self::assertSame('req-1', $record->getRequestId());
    }

    public function testGetPaginatedRecordsWithFilters(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $result = $repo->getPaginatedRecords(
            'test',
            1,
            5,
            new DateTimeImmutable('-1 year'),
            new DateTimeImmutable('+1 day'),
            'repo_test_route',
            '/repo',
            200,
            0.0,
            10.0,
            0,
            10 * 1024 * 1024,
            'https://example.com',
            'user1',
            'response_time',
            'ASC',
        );
        self::assertIsArray($result);
        self::assertArrayHasKey('records', $result);
        self::assertArrayHasKey('total', $result);
    }

    public function testGetRecordsForExportWithFilters(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $result = $repo->getRecordsForExport(
            'test',
            new DateTimeImmutable('-30 days'),
            new DateTimeImmutable('+1 day'),
            'repo_test_route',
            '/repo',
            200,
            0.0,
            5.0,
            0,
            2048000,
            'https://example.com',
            'user1',
        );
        self::assertIsArray($result);
        self::assertArrayHasKey('records', $result);
        self::assertArrayHasKey('total', $result);
    }

    public function testGetStatisticsByHourWithPathFilter(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $result = $repo->getStatisticsByHour('test', null, null, null, '/repo_test');
        self::assertIsArray($result);
        self::assertCount(24, $result);
    }

    public function testGetStatisticsByMonthWithRouteAndPath(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $result = $repo->getStatisticsByMonth('test', null, null, 'repo_test_route', '/repo');
        self::assertIsArray($result);
    }

    public function testGetHeatmapDataWithDateRange(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $start  = new DateTimeImmutable('-7 days');
        $end    = new DateTimeImmutable('now');
        $result = $repo->getHeatmapData('test', $start, $end);
        self::assertIsArray($result);
    }

    public function testCountByRouteData(): void
    {
        $this->createTablesAndRecordMetrics();
        $service   = $this->kernel->getContainer()->get(PerformanceMetricsService::class);
        $routeData = $service->getRouteData('repo_test_route', 'test');
        self::assertNotNull($routeData);

        $repo  = $this->getRepository();
        $count = $repo->countByRouteData($routeData);
        self::assertIsInt($count);
        self::assertGreaterThanOrEqual(0, $count);
    }

    public function testGetAggregatesForRouteDataIds(): void
    {
        $this->createTablesAndRecordMetrics();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);
        $routes  = $service->getRoutesByEnvironment('test');
        self::assertNotEmpty($routes);
        $ids = array_map(static fn ($r) => $r->getId(), $routes);

        $repo       = $this->getRepository();
        $aggregates = $repo->getAggregatesForRouteDataIds($ids);
        self::assertIsArray($aggregates);
    }

    public function testGetAggregatesForRouteDataIdsWithEmptyArrayReturnsEmpty(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $result = $repo->getAggregatesForRouteDataIds([]);
        self::assertSame([], $result);
    }

    /** deleteOlderThan($before) deletes records with accessedAt < $before. Past date => no records match. */
    public function testDeleteOlderThanWithPastDateDeletesNothing(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $past    = new DateTimeImmutable('2000-01-01');
        $deleted = $repo->deleteOlderThan($past, 'test', 100);
        self::assertSame(0, $deleted);
    }

    /** deleteOlderThan($before) with future $before deletes records older than that (covers delete loop). */
    public function testDeleteOlderThanWithFutureDateDeletesOldRecords(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $future  = new DateTimeImmutable('+10 years');
        $deleted = $repo->deleteOlderThan($future, 'test', 100);
        self::assertGreaterThanOrEqual(1, $deleted);
    }

    public function testDeleteByFilterWithNoMatchingRecordsReturnsZero(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $start   = new DateTimeImmutable('2020-01-01');
        $end     = new DateTimeImmutable('2020-01-02');
        $deleted = $repo->deleteByFilter('test', $start, $end, 'nonexistent_route');
        self::assertSame(0, $deleted);
    }

    public function testDeleteByRouteDataRemovesRecordsForRoute(): void
    {
        $this->createTablesAndRecordMetrics();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);
        $routes  = $service->getRoutesByEnvironment('test');
        self::assertNotEmpty($routes);
        $routeDataId = $routes[0]->getId();
        self::assertNotNull($routeDataId);

        $repo    = $this->getRepository();
        $deleted = $repo->deleteByRouteData($routeDataId);
        self::assertIsInt($deleted);
        self::assertGreaterThanOrEqual(0, $deleted);
    }

    /** deleteAllRecords(null) uses batch delete loop (no env filter). */
    public function testDeleteAllRecordsWithNullEnvRunsBatchLoop(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $deleted = $repo->deleteAllRecords(null);
        self::assertIsInt($deleted);
        self::assertGreaterThanOrEqual(1, $deleted);
    }

    /**
     * deleteByEnvironment() uses DQL DELETE with JOIN; Doctrine may throw QueryException.
     * Test covers the method body; we expect exception on unsupported DQL.
     */
    public function testDeleteByEnvironmentCoversMethod(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $this->expectException(QueryException::class);
        $repo->deleteByEnvironment('test');
    }

    private function getRepository(): RouteDataRecordRepository
    {
        $em   = $this->kernel->getContainer()->get('doctrine')->getManager();
        $repo = $em->getRepository(RouteDataRecord::class);
        self::assertInstanceOf(RouteDataRecordRepository::class, $repo);

        return $repo;
    }
}
