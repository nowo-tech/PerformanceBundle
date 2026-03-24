<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DataCollector;

use Exception;
use Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\DependencyChecker;
use Nowo\PerformanceBundle\Service\TableStatusChecker;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

final class PerformanceDataCollectorTest extends TestCase
{
    public function testGetName(): void
    {
        $collector = new PerformanceDataCollector();
        $this->assertSame('performance', $collector->getName());
    }

    public function testIsEnabledFalseByDefault(): void
    {
        $collector = new PerformanceDataCollector();
        $this->assertFalse($collector->isEnabled());
    }

    public function testSetEnabled(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(true);
        $this->assertTrue($collector->isEnabled());
        $collector->setEnabled(false);
        $this->assertFalse($collector->isEnabled());
    }

    public function testResetClearsDataAfterCollect(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $collector->setRouteName('app_home');

        $request = new Request();
        $request->attributes->set('_route', 'app_home');
        $response = new Response();

        $collector->collect($request, $response);

        $this->assertSame('app_home', $collector->getRouteName());
        $this->assertSame(0, $collector->getQueryCount());

        $collector->reset();

        $this->assertNull($collector->getRouteName());
        $this->assertSame(0, $collector->getQueryCount());
        $this->assertFalse($collector->isEnabled());
    }

    public function testSetRouteName(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setRouteName('api_foo');
        $collector->setEnabled(false);

        $request = new Request();
        $request->attributes->set('_route', 'other');
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame('api_foo', $collector->getRouteName());
    }

    public function testGetFormattedRequestTimeReturnsNaWhenDisabled(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame('N/A', $collector->getFormattedRequestTime());
    }

    public function testGetFormattedRequestTimeReturnsSecondsWhenTimeAboveOneSecond(): void
    {
        $tableChecker = $this->createMock(TableStatusChecker::class);
        $tableChecker->method('getMainTableStatus')->willReturn([
            'exists' => false, 'complete' => false, 'table_name' => 'routes_data', 'missing_columns' => [],
        ]);
        $tableChecker->method('getRecordsTableStatus')->willReturn(null);
        $tableChecker->method('isAccessRecordsEnabled')->willReturn(false);

        $collector = new PerformanceDataCollector(null, null, $tableChecker);
        $collector->setEnabled(true);
        $collector->setStartTime(microtime(true) - 2.5);

        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $formatted = $collector->getFormattedRequestTime();
        $this->assertStringContainsString('s', $formatted);
        $this->assertStringNotContainsString('N/A', $formatted);
    }

    public function testGetFormattedQueryTimeReturnsSecondsWhenTimeAboveOneSecond(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $collector->setQueryMetrics(0, 1.5);
        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $formatted = $collector->getFormattedQueryTime();
        $this->assertStringContainsString('s', $formatted);
    }

    public function testGetFormattedRequestTimeReturnsMillisecondsWhenTimeBetween1And1000Ms(): void
    {
        $tableChecker = $this->createMock(TableStatusChecker::class);
        $tableChecker->method('getMainTableStatus')->willReturn([
            'exists' => false, 'complete' => false, 'table_name' => 'routes_data', 'missing_columns' => [],
        ]);
        $tableChecker->method('getRecordsTableStatus')->willReturn(null);
        $tableChecker->method('isAccessRecordsEnabled')->willReturn(false);

        $collector = new PerformanceDataCollector(null, null, $tableChecker);
        $collector->setEnabled(true);
        $collector->setStartTime(microtime(true) - 0.1);

        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $formatted = $collector->getFormattedRequestTime();
        $this->assertStringContainsString('ms', $formatted);
        $this->assertStringEndsWith('ms', $formatted);
    }

    public function testGetFormattedRequestTimeReturnsSubMillisecondFormatWhenTimeBelowOneMs(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $ref                  = new ReflectionClass($collector);
        $dataProp             = $ref->getProperty('data');
        $data                 = $dataProp->getValue($collector);
        $data['request_time'] = 0.0005;
        $dataProp->setValue($collector, $data);

        $formatted = $collector->getFormattedRequestTime();
        $this->assertSame('0.50 ms', $formatted);
    }

    public function testGetFormattedQueryTimeReturnsMillisecondsWhenTimeBetween1And1000Ms(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $collector->setQueryMetrics(0, 0.1);
        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $formatted = $collector->getFormattedQueryTime();
        $this->assertStringContainsString('ms', $formatted);
        $this->assertStringEndsWith('ms', $formatted);
    }

    public function testGetFormattedQueryTimeReturnsZeroMsWhenDisabled(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertStringContainsString('ms', $collector->getFormattedQueryTime());
        $this->assertSame(0, $collector->getQueryCount());
    }

    /**
     * Covers the catch block in collect() when query metrics provider throws (fallback to 0 and 0.0).
     */
    public function testCollectWhenQueryMetricsProviderThrowsUsesFallbackZero(): void
    {
        $provider = static function (): array {
            throw new Exception('query metrics unavailable');
        };
        $collector = new PerformanceDataCollector(null, null, null, null, null, true, $provider);
        $collector->setEnabled(true);
        $request = new Request();
        $request->attributes->set('_route', 'app_test');
        $response = new Response();

        $collector->collect($request, $response);

        $this->assertSame(0, $collector->getQueryCount());
        $this->assertSame(0.0, $collector->getQueryTime());
    }

    /**
     * Covers the provider path when return value is not a valid array (missing keys 0/1); uses fallback 0 and 0.0.
     */
    public function testCollectWhenQueryMetricsProviderReturnsInvalidArrayUsesFallbackZero(): void
    {
        $provider = (static fn(): array => []);
        $collector = new PerformanceDataCollector(null, null, null, null, null, true, $provider);
        $collector->setEnabled(true);
        $request = new Request();
        $request->attributes->set('_route', 'app_test');
        $response = new Response();

        $collector->collect($request, $response);

        $this->assertSame(0, $collector->getQueryCount());
        $this->assertSame(0.0, $collector->getQueryTime());
    }

    public function testGetProcessingModeReturnsSyncWhenDisabled(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame('sync', $collector->getProcessingMode());
        $this->assertFalse($collector->isAsync());
    }

    public function testSetRecordOperationAndGetRecordOperationStatus(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $collector->setRecordOperation(true, false);
        $this->assertTrue($collector->wasRecordNew());
        $this->assertFalse($collector->wasRecordUpdated());
        $this->assertSame($collector->wasRecordNew(), $collector->getWasRecordNew());
        $this->assertSame($collector->wasRecordUpdated(), $collector->getWasRecordUpdated());
        $this->assertSame('New record created', $collector->getRecordOperationStatus());

        $collector->setRecordOperation(false, true);
        $this->assertSame('Existing record updated', $collector->getRecordOperationStatus());

        $collector->setRecordOperation(false, false);
        $this->assertSame('No changes (metrics not worse than existing)', $collector->getRecordOperationStatus());

        $collector->setRecordOperation(true, true);
        $this->assertSame('New record created', $collector->getRecordOperationStatus());
    }

    public function testGetRecordOperationStatusUnknownWhenBothNull(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertNull($collector->wasRecordNew());
        $this->assertNull($collector->wasRecordUpdated());
        $this->assertSame('Unknown', $collector->getRecordOperationStatus());
    }

    public function testConfiguredEnvironmentsAndDisabledReasonStoredWhenDisabled(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setConfiguredEnvironments(['dev', 'prod']);
        $collector->setCurrentEnvironment('dev');
        $collector->setDisabledReason('Route ignored');
        $collector->setEnabled(false);

        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame(['dev', 'prod'], $collector->getConfiguredEnvironments());
        $this->assertSame('dev', $collector->getCurrentEnvironment());
        $this->assertSame('Route ignored', $collector->getDisabledReason());
    }

    public function testGetMissingDependenciesAndDependencyStatusWhenDisabled(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame([], $collector->getMissingDependencies());
        $this->assertSame([], $collector->getDependencyStatus());
    }

    public function testTableExistsTableIsCompleteGetTableNameGetMissingColumnsWhenDisabled(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertFalse($collector->tableExists());
        $this->assertFalse($collector->tableIsComplete());
        $this->assertNull($collector->getTableName());
        $this->assertSame([], $collector->getMissingColumns());
    }

    public function testGetRequestTimeAccessCountRankingAndTotalRoutesWhenDisabled(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertNull($collector->getRequestTime());
        $this->assertNull($collector->getAccessCount());
        $this->assertNull($collector->getRankingByRequestTime());
        $this->assertNull($collector->getRankingByQueryCount());
        $this->assertNull($collector->getTotalRoutes());
    }

    public function testCollectMustBeCalledBeforeReset(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);

        $collector->reset();

        $this->assertNull($collector->getRouteName());
        $this->assertSame(0, $collector->getQueryCount());
    }

    public function testSetStartTimeBeforeCollect(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(true);
        $collector->setStartTime(microtime(true) - 0.5);

        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $requestTime = $collector->getRequestTime();
        $this->assertNotNull($requestTime);
        $this->assertGreaterThanOrEqual(0.0, $requestTime);
    }

    public function testSetQueryMetricsAndGetQueryCountQueryTime(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(true);
        $collector->setQueryMetrics(7, 0.15);

        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame(7, $collector->getQueryCount());
        $this->assertStringContainsString('ms', $collector->getFormattedQueryTime());
    }

    public function testSetAsyncAndIsAsync(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(true);
        $collector->setAsync(true);

        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        // isAsync() returns true only when both setAsync(true) AND Symfony Messenger is available
        $messengerAvailable = interface_exists('Symfony\Component\Messenger\MessageBusInterface')
            || class_exists('Symfony\Component\Messenger\MessageBusInterface');
        if ($messengerAvailable) {
            $this->assertTrue($collector->isAsync());
            $this->assertSame('async', $collector->getProcessingMode());
        } else {
            $this->assertFalse($collector->isAsync());
            $this->assertSame('sync', $collector->getProcessingMode());
        }
    }

    public function testSetQueryCountAndSetQueryTimeBeforeCollect(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(true);
        $collector->setQueryCount(3);
        $collector->setQueryTime(0.12);

        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame(3, $collector->getQueryCount());
        $this->assertStringContainsString('ms', $collector->getFormattedQueryTime());
    }

    public function testSetRequestTimeCalledDoesNotThrow(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(true);
        $collector->setRequestTime(0.5);

        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->addToAssertionCount(1);
    }

    /** Covers setRecordOperation() when called after collect(): updates $this->data so wasRecordNew/wasRecordUpdated reflect it. */
    public function testSetRecordOperationAfterCollectUpdatesData(): void
    {
        $tableChecker = $this->createMock(TableStatusChecker::class);
        $tableChecker->method('getMainTableStatus')->willReturn([
            'exists' => true, 'complete' => true, 'table_name' => 'routes_data', 'missing_columns' => [],
        ]);
        $tableChecker->method('getRecordsTableStatus')->willReturn(null);
        $tableChecker->method('isAccessRecordsEnabled')->willReturn(false);

        $collector = new PerformanceDataCollector(null, null, $tableChecker);
        $collector->setEnabled(true);

        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertNull($collector->wasRecordNew());
        $this->assertNull($collector->wasRecordUpdated());

        $collector->setRecordOperation(true, false);

        $this->assertTrue($collector->wasRecordNew());
        $this->assertFalse($collector->wasRecordUpdated());

        $collector->setRecordOperation(false, true);
        $this->assertFalse($collector->wasRecordNew());
        $this->assertTrue($collector->wasRecordUpdated());
    }

    /**
     * When TableStatusChecker::getMainTableStatus() throws, collect() catches and continues (silent fail).
     *
     * @covers \Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector::collect
     */
    public function testCollectSwallowsExceptionWhenTableStatusCheckerThrows(): void
    {
        $tableChecker = $this->createMock(TableStatusChecker::class);
        $tableChecker->method('getMainTableStatus')->willThrowException(new Exception('DB unavailable'));
        $tableChecker->method('getRecordsTableStatus')->willReturn(null);
        $tableChecker->method('isAccessRecordsEnabled')->willReturn(false);

        $collector = new PerformanceDataCollector(null, null, $tableChecker, null, null, true);
        $collector->setEnabled(true);

        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertFalse($collector->tableExists());
        $this->assertFalse($collector->tableIsComplete());
        $this->assertSame([], $collector->getMissingColumns());
    }

    public function testCollectWithTableStatusCheckerAndDependencyCheckerEnabled(): void
    {
        $tableChecker = $this->createMock(TableStatusChecker::class);
        $tableChecker->method('getMainTableStatus')->willReturn([
            'exists'          => true,
            'complete'        => true,
            'table_name'      => 'routes_data',
            'missing_columns' => [],
        ]);
        $tableChecker->method('getRecordsTableStatus')->willReturn(null);
        $tableChecker->method('isAccessRecordsEnabled')->willReturn(false);

        $depChecker = $this->createMock(DependencyChecker::class);
        $depChecker->method('getMissingDependencies')->willReturn([]);
        $depChecker->method('getDependencyStatus')->willReturn([]);

        $collector = new PerformanceDataCollector(null, null, $tableChecker, $depChecker, null, true);
        $collector->setEnabled(true);

        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertTrue($collector->tableExists());
        $this->assertTrue($collector->tableIsComplete());
        $this->assertSame('routes_data', $collector->getTableName());
        $this->assertSame([], $collector->getMissingColumns());
        $this->assertSame([], $collector->getMissingDependencies());
        $this->assertSame([], $collector->getDependencyStatus());
    }

    public function testCollectWithRecordsTableStatusWhenAccessRecordsEnabled(): void
    {
        $tableChecker = $this->createMock(TableStatusChecker::class);
        $tableChecker->method('getMainTableStatus')->willReturn([
            'exists'          => true,
            'complete'        => true,
            'table_name'      => 'routes_data',
            'missing_columns' => [],
        ]);
        $tableChecker->method('getRecordsTableStatus')->willReturn([
            'exists'          => true,
            'complete'        => true,
            'table_name'      => 'routes_data_records',
            'missing_columns' => [],
        ]);
        $tableChecker->method('isAccessRecordsEnabled')->willReturn(true);

        $depChecker = $this->createMock(DependencyChecker::class);
        $depChecker->method('getMissingDependencies')->willReturn([]);
        $depChecker->method('getDependencyStatus')->willReturn([]);

        $collector = new PerformanceDataCollector(null, null, $tableChecker, $depChecker, null, true);
        $collector->setEnabled(true);

        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertTrue($collector->tableExists());
        $this->assertSame('routes_data', $collector->getTableName());
    }

    /**
     * When repository throws (e.g. table missing), collect() catches and continues (silent fail).
     *
     * @covers \Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector::collect
     */
    public function testCollectSwallowsExceptionWhenRepositoryThrows(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('findByRouteAndEnv')->willThrowException(new Exception('Connection failed'));

        $collector = new PerformanceDataCollector($repository, null, null, null, null, false);
        $collector->setEnabled(true);
        $collector->setRouteName('app_home');
        $collector->setCurrentEnvironment('dev');

        $request = new Request();
        $request->attributes->set('_route', 'app_home');
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame('app_home', $collector->getRouteName());
        $this->assertNull($collector->getAccessCount());
        $this->assertNull($collector->getRankingByRequestTime());
    }

    /**
     * When repository returns RouteData but recordRepository is null, accessCount is null (ternary else branch).
     *
     * @covers \Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector::collect
     */
    public function testCollectWithRepositoryWithoutRecordRepositorySetsAccessCountNull(): void
    {
        $route = new RouteData();
        $route->setName('api_foo')->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('findByRouteAndEnv')->with('api_foo', 'dev')->willReturn($route);
        $repository->method('getRankingByRequestTime')->with($route)->willReturn(1);
        $repository->method('getRankingByQueryCount')->with($route)->willReturn(2);
        $repository->method('getTotalRoutesCount')->with('dev')->willReturn(5);

        $collector = new PerformanceDataCollector($repository, null, null, null, null, false);
        $collector->setEnabled(true);
        $collector->setRouteName('api_foo');

        $request = new Request();
        $request->attributes->set('_route', 'api_foo');
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertNull($collector->getAccessCount());
        $this->assertSame(1, $collector->getRankingByRequestTime());
        $this->assertSame(2, $collector->getRankingByQueryCount());
        $this->assertSame(5, $collector->getTotalRoutes());
    }

    public function testCollectWithRepositoryAndRecordRepositoryFetchesRankingAndAccessCount(): void
    {
        $route = new RouteData();
        $route->setName('app_home')->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('findByRouteAndEnv')->with('app_home', 'dev')->willReturn($route);
        $repository->method('getRankingByRequestTime')->with($route)->willReturn(5);
        $repository->method('getRankingByQueryCount')->with($route)->willReturn(3);
        $repository->method('getTotalRoutesCount')->with('dev')->willReturn(10);

        $recordRepository = $this->createMock(RouteDataRecordRepository::class);
        $recordRepository->method('countByRouteData')->with($route)->willReturn(42);

        $collector = new PerformanceDataCollector($repository, null, null, null, $recordRepository, false);
        $collector->setEnabled(true);
        $collector->setRouteName('app_home');

        $request = new Request();
        $request->attributes->set('_route', 'app_home');
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame(42, $collector->getAccessCount());
        $this->assertSame(5, $collector->getRankingByRequestTime());
        $this->assertSame(3, $collector->getRankingByQueryCount());
        $this->assertSame(10, $collector->getTotalRoutes());
    }

    public function testCollectWhenTableStatusCheckerThrowsStillCompletes(): void
    {
        $tableChecker = $this->createMock(TableStatusChecker::class);
        $tableChecker->method('getMainTableStatus')->willThrowException(new Exception('DB unavailable'));

        $collector = new PerformanceDataCollector(null, null, $tableChecker, null, null, true);
        $collector->setEnabled(true);

        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertFalse($collector->tableExists());
        $this->assertFalse($collector->tableIsComplete());
        $this->assertSame([], $collector->getMissingColumns());
    }

    /** Covers the catch block when getRecordsTableStatus() throws after getMainTableStatus() succeeded. */
    public function testCollectWhenGetRecordsTableStatusThrowsStillCompletes(): void
    {
        $tableChecker = $this->createMock(TableStatusChecker::class);
        $tableChecker->method('getMainTableStatus')->willReturn([
            'exists'          => true,
            'complete'        => true,
            'table_name'      => 'routes_data',
            'missing_columns' => [],
        ]);
        $tableChecker->method('getRecordsTableStatus')->willThrowException(new Exception('records table check failed'));

        $collector = new PerformanceDataCollector(null, null, $tableChecker, null, null, true);
        $collector->setEnabled(true);

        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertTrue($collector->tableExists());
        $this->assertTrue($collector->tableIsComplete());
    }

    public function testCollectWhenGetParameterThrowsUsesDefaultRankingEnabled(): void
    {
        $container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('getParameter')->with('nowo_performance.dashboard.enable_ranking_queries')->willThrowException(new Exception('param missing'));

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn('dev');
        $kernel->method('getContainer')->willReturn($container);

        $route = new RouteData();
        $route->setName('r')->setEnv('dev');
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('findByRouteAndEnv')->with('r', 'dev')->willReturn($route);
        $repository->method('getRankingByRequestTime')->with($route)->willReturn(1);
        $repository->method('getRankingByQueryCount')->with($route)->willReturn(2);
        $repository->method('getTotalRoutesCount')->with('dev')->willReturn(5);

        $recordRepo = $this->createMock(RouteDataRecordRepository::class);
        $recordRepo->method('countByRouteData')->with($route)->willReturn(10);

        $collector = new PerformanceDataCollector($repository, $kernel, null, null, $recordRepo, false);
        $collector->setEnabled(true);
        $collector->setRouteName('r');

        $request = new Request();
        $request->attributes->set('_route', 'r');
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame(10, $collector->getAccessCount());
        $this->assertSame(1, $collector->getRankingByRequestTime());
        $this->assertSame(5, $collector->getTotalRoutes());
    }

    public function testCollectWhenEnableRankingQueriesFalseSkipsRankingQueries(): void
    {
        $container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('getParameter')->with('nowo_performance.dashboard.enable_ranking_queries')->willReturn(false);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn('dev');
        $kernel->method('getContainer')->willReturn($container);

        $route = new RouteData();
        $route->setName('home')->setEnv('dev');
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('findByRouteAndEnv')->with('home', 'dev')->willReturn($route);
        $repository->expects($this->never())->method('getRankingByRequestTime');
        $repository->expects($this->never())->method('getRankingByQueryCount');
        $repository->expects($this->never())->method('getTotalRoutesCount');

        $recordRepo = $this->createMock(RouteDataRecordRepository::class);
        $recordRepo->method('countByRouteData')->with($route)->willReturn(7);

        $collector = new PerformanceDataCollector($repository, $kernel, null, null, $recordRepo, false);
        $collector->setEnabled(true);
        $collector->setRouteName('home');

        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame(7, $collector->getAccessCount());
        $this->assertNull($collector->getRankingByRequestTime());
        $this->assertNull($collector->getRankingByQueryCount());
        $this->assertNull($collector->getTotalRoutes());
    }

    public function testCollectWhenRepositoryFindByRouteAndEnvThrowsStillCompletes(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('findByRouteAndEnv')->willThrowException(new Exception('DB error'));

        $collector = new PerformanceDataCollector($repository, null, null, null, null, false);
        $collector->setEnabled(true);
        $collector->setRouteName('app_home');

        $request = new Request();
        $request->attributes->set('_route', 'app_home');
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertNull($collector->getAccessCount());
        $this->assertNull($collector->getRankingByRequestTime());
        $this->assertNull($collector->getTotalRoutes());
    }

    public function testWasRecordNewAndWasRecordUpdatedUseDataFallbackWhenPropertiesNull(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $ref      = new ReflectionClass($collector);
        $dataProp = $ref->getProperty('data');
        $dataProp->setValue($collector, ['record_was_new' => true, 'record_was_updated' => false]);
        $recordWasNewProp = $ref->getProperty('recordWasNew');
        $recordWasNewProp->setValue($collector, null);
        $recordWasUpdatedProp = $ref->getProperty('recordWasUpdated');
        $recordWasUpdatedProp->setValue($collector, null);

        $this->assertTrue($collector->wasRecordNew());
        $this->assertFalse($collector->wasRecordUpdated());
        $this->assertSame('New record created', $collector->getRecordOperationStatus());
    }

    /** setRecordOperation() called after collect() updates the data array. */
    public function testSetRecordOperationAfterCollectUpdatesDataArray(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(true);
        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $collector->setRecordOperation(true, false);

        $this->assertTrue($collector->wasRecordNew());
        $this->assertFalse($collector->wasRecordUpdated());
        $this->assertSame('New record created', $collector->getRecordOperationStatus());
    }

    /** When queryCount/queryTime are null and QueryTrackingMiddleware throws, collector uses fallback 0. */
    public function testCollectWhenQueryTrackingMiddlewareThrowsUsesFallback(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(true);
        $collector->setStartTime(microtime(true) - 0.01);
        // Do not set query count/time so collect() tries to get them from QueryTrackingMiddleware
        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertIsInt($collector->getQueryCount());
        $this->assertGreaterThanOrEqual(0, $collector->getQueryCount());
    }

    /** getFormattedRequestTime with time >= 1000 ms returns seconds format. */
    public function testGetFormattedRequestTimeReturnsSecondsWhenTimeAbove1000Ms(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $ref                  = new ReflectionClass($collector);
        $dataProp             = $ref->getProperty('data');
        $data                 = $dataProp->getValue($collector);
        $data['request_time'] = 2.5;
        $dataProp->setValue($collector, $data);

        $formatted = $collector->getFormattedRequestTime();
        $this->assertStringContainsString('s', $formatted);
        $this->assertStringNotContainsString('ms', $formatted);
    }

    /** getFormattedQueryTime with time >= 1000 ms returns seconds format. */
    public function testGetFormattedQueryTimeReturnsSecondsWhenTimeAbove1000Ms(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $collector->setQueryMetrics(0, 1.5);
        $request  = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $formatted = $collector->getFormattedQueryTime();
        $this->assertStringContainsString('s', $formatted);
    }
}
