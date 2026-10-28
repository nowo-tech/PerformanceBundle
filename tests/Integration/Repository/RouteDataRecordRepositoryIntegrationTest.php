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

    public function testGetPaginatedRecordsClampsOutOfRangePagesAndReportsTheCap(): void
    {
        $this->createTablesAndRecordMetrics();
        $repo = $this->getRepository();

        $all     = $repo->getPaginatedRecords('test', 1, 1);
        $clamped = $repo->getPaginatedRecords('test', 999, 1);

        self::assertFalse($all['total_capped']);
        self::assertGreaterThan(0, $all['total']);
        self::assertSame($all['total_pages'], $clamped['page'], 'out-of-range page lands on the last page');
        self::assertCount(1, $clamped['records']);
        self::assertSame(10000, RouteDataRecordRepository::MAX_LISTED);
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

        $deleted = $repo->deleteAllRecords();
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

    /**
     * LIKE filters must treat `%`, `_` and `\` as literals (ESCAPE '\' clause), including on SQLite,
     * which has no default LIKE escape character.
     */
    public function testLikeFiltersEscapeWildcardsAndBackslashOnSqlite(): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        (new CommandTester($application->find('nowo:performance:create-table')))->execute([]);
        (new CommandTester($application->find('nowo:performance:create-records-table')))->execute([]);
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);

        $rows = [
            ['like_underscore', '/a_b', 'us_r', 'https://ref.test/a_b'],
            ['like_any_char', '/axb', 'usxr', 'https://ref.test/axb'],
            ['like_percent', '/50%off', 'u%1', 'https://ref.test/50%off'],
            ['like_no_percent', '/50xoff', 'ux1', 'https://ref.test/50xoff'],
            ['like_backslash', '/dir\\sub', 'dom\\joe', 'https://ref.test/dir\\sub'],
            ['like_no_backslash', '/dirsub', 'domjoe', 'https://ref.test/dirsub'],
        ];
        foreach ($rows as $i => [$route, $path, $user, $referer]) {
            $service->recordMetrics($route, 'test', 0.1, 1, 0.01, null, 1024, 'GET', 200, [200], 'like-req-' . $i, $referer, $user, null, $path);
        }

        $repo  = $this->getRepository();
        $paths = static function (array $result): array {
            $out = array_map(static fn (RouteDataRecord $r): ?string => $r->getRoutePath(), $result['records']);
            sort($out);

            return $out;
        };
        $page = static fn (?string $path, ?string $referer = null, ?string $user = null): array => $repo->getPaginatedRecords('test', 1, 50, null, null, null, $path, null, null, null, null, null, $referer, $user);

        self::assertSame(['/a_b'], $paths($page('/a_b')));
        self::assertSame(['/50%off'], $paths($page('/50%')));
        self::assertSame(['/dir\\sub'], $paths($page('/dir\\s')));

        self::assertSame(['/a_b'], $paths($page(null, null, 'us_r')));
        self::assertSame(['/50%off'], $paths($page(null, null, 'u%')));
        self::assertSame(['/dir\\sub'], $paths($page(null, null, 'dom\\')));

        self::assertSame(['/a_b'], $paths($page(null, '/a_b')));
        self::assertSame(['/dir\\sub'], $paths($page(null, 'dir\\s')));

        $export = $repo->getRecordsForExport('test', null, null, null, '/a_b');
        self::assertSame(1, $export['total']);

        $hour = $repo->getStatisticsByHour('test', null, null, null, '/50%');
        self::assertSame(1, array_sum(array_column($hour, 'count')));
    }

    private function getRepository(): RouteDataRecordRepository
    {
        $em   = $this->kernel->getContainer()->get('doctrine')->getManager();
        $repo = $em->getRepository(RouteDataRecord::class);
        self::assertInstanceOf(RouteDataRecordRepository::class, $repo);

        return $repo;
    }
}
