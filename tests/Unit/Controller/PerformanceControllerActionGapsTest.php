<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\BeforeRecordReviewedEvent;
use Nowo\PerformanceBundle\Form\ClearPerformanceDataType;
use Nowo\PerformanceBundle\Form\DeleteRecordsByFilterType;
use Nowo\PerformanceBundle\Form\DeleteRecordType;
use Nowo\PerformanceBundle\Form\PerformanceFiltersType;
use Nowo\PerformanceBundle\Form\PurgeAccessRecordsType;
use Nowo\PerformanceBundle\Form\RecordFiltersType;
use Nowo\PerformanceBundle\Form\ReviewRouteDataType;
use Nowo\PerformanceBundle\Model\ClearPerformanceDataRequest;
use Nowo\PerformanceBundle\Model\DeleteRecordsByFilterRequest;
use Nowo\PerformanceBundle\Model\PurgeAccessRecordsRequest;
use Nowo\PerformanceBundle\Model\RecordFilters;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Nowo\PerformanceBundle\Tests\Support\PageFixtures;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * REQ-TEST-003: branches of the HTTP actions not reached by the other controller tests (fallback environments, date
 * filters, cache-less clear, review variants, purge / delete-by-filter error and redirect branches).
 */
final class PerformanceControllerActionGapsTest extends TestCase
{
    use ControllerTestHarness;

    private MockObject&PerformanceMetricsService $metrics;
    private MockObject&RouteDataRepository $repository;
    private MockObject&RouteDataRecordRepository $records;
    private MockObject&PerformanceCacheService $cache;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RouteDataRepository::class);
        $this->records    = $this->createMock(RouteDataRecordRepository::class);
        $this->metrics    = $this->createMock(PerformanceMetricsService::class);
        $this->metrics->method('getRepository')->willReturn($this->repository);
        $this->cache = $this->createMock(PerformanceCacheService::class);
    }

    public function testIndexFallsBackToDefaultEnvironmentsAndAppliesDateFilters(): void
    {
        $this->cache->method('cacheEnvironments')->willThrowException(new RuntimeException('cache down'));
        $from                                       = new DateTimeImmutable('2026-10-01');
        $to                                         = new DateTimeImmutable('2026-10-30');
        $this->forms[PerformanceFiltersType::class] = $this->form(['env' => 'test', 'date_from' => $from, 'date_to' => $to]);
        $this->repository->expects(self::once())->method('countWithFilters')
            ->with('test', ['date_from' => $from, 'date_to' => $to])
            ->willReturn(0);

        $response = $this->controller($this->metrics, ['cacheService' => $this->cache])->index(Request::create('/performance'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['dev', 'test', 'prod'], $this->rendered['environments']);
    }

    public function testStatisticsFallsBackToDefaultEnvironments(): void
    {
        $this->cache->method('cacheEnvironments')->willThrowException(new RuntimeException('cache down'));
        $this->metrics->method('getRoutesWithAggregates')->willReturn([]);

        $this->controller($this->metrics, ['cacheService' => $this->cache])->statistics(Request::create('/performance/statistics'));

        self::assertSame(['dev', 'test', 'prod'], $this->rendered['environments']);
    }

    public function testRoutesNeedingAttentionListsOutliers(): void
    {
        $route = PageFixtures::route(1, 'app_slow', 'GET', 1.23456, 99, 0.5, 1_048_576, 3, [200 => 3]);
        $stats = ['request_time' => ['outliers' => [1.2346]], 'query_count' => ['outliers' => [99]], 'memory_usage' => []];

        $result = (new ReflectionMethod(PerformanceController::class, 'getRoutesNeedingAttention'))
            ->invoke($this->controller($this->metrics), [$route], $stats);

        self::assertCount(1, $result['outliers']);
        self::assertSame($route, $result['outliers'][0]['route']);
    }

    public function testChartDataUsesRequestTimeForUnknownMetric(): void
    {
        $this->metrics->method('getRoutesWithAggregatesFiltered')->willReturn([PageFixtures::route(1, 'app_home', 'GET', 0.25, 1, 0.1, 1024, 1, [200 => 1])]);
        $controller = $this->controller($this->metrics, ['clock' => new MockClock('2026-10-30 12:00:00')]);

        $data = (new ReflectionMethod(PerformanceController::class, 'getChartData'))->invoke($controller, 'test', 'app_home', 1, 'unknown');

        self::assertSame('Average Request Time (s)', $data['datasets'][0]['label']);
    }

    public function testExportRecordsJsonWithoutRepositoryIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller($this->metrics, ['enableAccessRecords' => true])->exportRecordsJson(Request::create('/x'));
    }

    public function testClearIsNotFoundWhenDashboardDisabled(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller($this->metrics, ['enabled' => false])->clear(Request::create('/x', 'POST'));
    }

    public function testClearWithoutEnvironmentClearsEnvironmentCache(): void
    {
        $this->forms[ClearPerformanceDataType::class] = $this->form(new ClearPerformanceDataRequest(''));
        $this->repository->method('deleteAll')->with('')->willReturn(4);
        $this->cache->expects(self::once())->method('clearEnvironments');

        $this->controller($this->metrics, ['enableRecordManagement' => true, 'cacheService' => $this->cache])->clear(Request::create('/x', 'POST'));

        self::assertTrue($this->flashed('success', 'from all environments'));
    }

    public function testDeleteFlashesRepositoryErrors(): void
    {
        $this->forms[DeleteRecordType::class] = $this->form();
        $this->repository->method('find')->willThrowException(new RuntimeException('db gone'));

        $this->controller($this->metrics, ['enableRecordManagement' => true])->delete(5, Request::create('/x', 'POST'));

        self::assertTrue($this->flashed('error', 'db gone'));
    }

    public function testReviewWithNegativeAnswersSavesAccessRecordsAndUpdatesReview(): void
    {
        $route = (new RouteData())->setEnv('test')->setName('app_home');
        $route->markAsReviewed(true, true);
        $this->repository->method('find')->willReturn($route);
        $this->repository->method('markAsReviewed')->willReturnCallback(static function (int $id, ?bool $queries, ?bool $time): bool {
            self::assertNull($queries);
            self::assertNull($time);

            return true;
        });
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $this->metrics->method('getEntityManager')->willReturn($em);
        $this->forms[ReviewRouteDataType::class] = $this->form(['queries_improved' => 'maybe', 'time_improved' => 'banana', 'save_access_records' => false]);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters, ?string $domain): string => $domain . ':' . $id);
        $controller = $this->controller(
            $this->metrics,
            ['enableReviewSystem' => true, 'enableAccessRecords' => true, 'clock' => new MockClock('2026-10-30 12:00:00'), 'translator' => $translator],
            ['getUser'],
        );

        $controller->review(1, Request::create('/x', 'POST'));

        self::assertFalse($route->getSaveAccessRecords());
        self::assertTrue($this->flashed('success', 'NowoPerformanceBundle:flash.review_updated'));
    }

    public function testReviewPreventedByListener(): void
    {
        $this->repository->method('find')->willReturn((new RouteData())->setEnv('test'));
        $this->forms[ReviewRouteDataType::class] = $this->form(['queries_improved' => '0', 'time_improved' => 'false']);
        $dispatcher                              = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static function (object $event): object {
            if ($event instanceof BeforeRecordReviewedEvent) {
                $event->preventReview();
            }

            return $event;
        });
        $this->repository->expects(self::never())->method('markAsReviewed');

        $this->controller($this->metrics, ['enableReviewSystem' => true, 'eventDispatcher' => $dispatcher], ['getUser'])->review(1, Request::create('/x', 'POST'));

        self::assertTrue($this->flashed('warning', 'prevented'));
    }

    public function testReviewFlashesRepositoryErrors(): void
    {
        $this->repository->method('find')->willThrowException(new RuntimeException('db gone'));

        $this->controller($this->metrics, ['enableReviewSystem' => true])->review(1, Request::create('/x', 'POST'));

        self::assertTrue($this->flashed('error', 'db gone'));
    }

    public function testAccessStatisticsIsNotFoundWithoutAccessRecords(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller($this->metrics)->accessStatistics(Request::create('/x'));
    }

    public function testAccessStatisticsWithoutRepositoryWarnsAndUsesDefaultEnvironments(): void
    {
        $this->cache->method('cacheEnvironments')->willThrowException(new RuntimeException('cache down'));

        $this->forms[RecordFiltersType::class] = $this->form(new RecordFilters(null, null, 'test'));

        $this->controller($this->metrics, ['enableAccessRecords' => true, 'cacheService' => $this->cache])->accessStatistics(Request::create('/x'));

        self::assertSame(['dev', 'test', 'prod'], $this->rendered['environments']);
        self::assertTrue($this->flashed('warning', 'RouteDataRecordRepository is not available'));
    }

    public function testAccessRecordsWithoutRepositoryWarnsAndUsesDefaultEnvironments(): void
    {
        $this->cache->method('cacheEnvironments')->willThrowException(new RuntimeException('cache down'));

        $this->forms[RecordFiltersType::class] = $this->form(new RecordFilters(null, null, 'test'));

        $this->controller($this->metrics, ['enableAccessRecords' => true, 'cacheService' => $this->cache])->accessRecords(Request::create('/x'));

        self::assertSame(['dev', 'test', 'prod'], $this->rendered['environments']);
        self::assertTrue($this->flashed('warning', 'Cannot fetch access records'));
    }

    public function testPurgeIsNotFoundWithoutAccessRecords(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller($this->metrics)->purgeRecords(Request::create('/x', 'POST'));
    }

    public function testPurgeIsDeniedWithoutRecordManagement(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->controller($this->metrics, ['enableAccessRecords' => true])->purgeRecords(Request::create('/x', 'POST'));
    }

    public function testPurgeOlderThanZeroDaysUsesThirtyDaysAndClearsAllEnvironmentCaches(): void
    {
        $clock                                      = new MockClock('2026-10-30 12:00:00');
        $this->forms[PurgeAccessRecordsType::class] = $this->form(new PurgeAccessRecordsRequest('', PurgeAccessRecordsRequest::PURGE_OLDER_THAN, 0));
        $this->records->expects(self::once())->method('deleteOlderThan')
            ->with(self::callback(static fn (DateTimeImmutable $before): bool => $before->format('Y-m-d') === '2026-09-30'), null)
            ->willReturn(2);
        $this->cache->expects(self::once())->method('clearEnvironments');

        $this->controller($this->metrics, [
            'enableAccessRecords'    => true,
            'enableRecordManagement' => true,
            'recordRepository'       => $this->records,
            'cacheService'           => $this->cache,
            'clock'                  => $clock,
        ])->purgeRecords(Request::create('/x', 'POST'));

        self::assertTrue($this->flashed('success', 'older than 30 days'));
    }

    public function testPurgeFlashesRepositoryErrors(): void
    {
        $this->forms[PurgeAccessRecordsType::class] = $this->form(new PurgeAccessRecordsRequest('test', PurgeAccessRecordsRequest::PURGE_ALL));
        $this->records->method('deleteAllRecords')->willThrowException(new RuntimeException('db gone'));

        $this->controller($this->metrics, ['enableAccessRecords' => true, 'enableRecordManagement' => true, 'recordRepository' => $this->records])
            ->purgeRecords(Request::create('/x', 'POST'));

        self::assertTrue($this->flashed('error', 'db gone'));
    }

    public function testDeleteByFilterRequiresEnvironment(): void
    {
        $this->forms[DeleteRecordsByFilterType::class] = $this->form(new DeleteRecordsByFilterRequest(''));

        $response = $this->deleteByFilterController()->deleteRecordsByFilter(Request::create('/x', 'POST'));

        self::assertTrue($this->flashed('error', 'Environment is required'));
        self::assertStringStartsWith('/nowo_performance.access_records', $response->getTargetUrl());
    }

    public function testDeleteByFilterWithoutRepositoryIgnoresInvalidDates(): void
    {
        $this->forms[DeleteRecordsByFilterType::class] = $this->form(new DeleteRecordsByFilterRequest('test', 'access_records', 'not a date', 'neither'));

        $this->controller($this->metrics, ['enableAccessRecords' => true, 'enableRecordManagement' => true])
            ->deleteRecordsByFilter(Request::create('/x', 'POST'));

        self::assertTrue($this->flashed('error', 'repository is not available'));
    }

    public function testDeleteByFilterErrorKeepsEveryFilterInTheRedirect(): void
    {
        $this->forms[DeleteRecordsByFilterType::class] = $this->form($this->fullFilter('access_records'));
        $this->records->method('deleteByFilter')->willThrowException(new RuntimeException('db gone'));

        $response = $this->deleteByFilterController()->deleteRecordsByFilter(Request::create('/x', 'POST'));

        self::assertTrue($this->flashed('error', 'db gone'));
        $url = urldecode($response->getTargetUrl());
        foreach (['path=/foo', 'min_query_time=0.1', 'max_query_time=2', 'min_memory_mb=1', 'max_memory_mb=2', 'referer=example.com', 'user=alice'] as $part) {
            self::assertStringContainsString($part, $url);
        }
    }

    public function testDeleteByFilterSuccessRedirectsToAccessStatisticsWithEveryFilter(): void
    {
        $this->forms[DeleteRecordsByFilterType::class] = $this->form($this->fullFilter('access_statistics'));
        $this->records->method('deleteByFilter')->willReturn(3);

        $response = $this->deleteByFilterController()->deleteRecordsByFilter(Request::create('/x', 'POST'));

        $url = urldecode($response->getTargetUrl());
        self::assertStringStartsWith('/nowo_performance.access_statistics', $url);
        foreach (['start_date=2026-10-01T00:00', 'end_date=2026-10-30T00:00', 'route=app_home', 'status_code=500', 'min_query_time=0.1', 'max_query_time=2', 'min_memory_mb=1', 'max_memory_mb=2', 'referer=example.com', 'user=alice'] as $part) {
            self::assertStringContainsString($part, $url);
        }
    }

    private function deleteByFilterController(): PerformanceController&MockObject
    {
        return $this->controller($this->metrics, [
            'enableAccessRecords'    => true,
            'enableRecordManagement' => true,
            'recordRepository'       => $this->records,
            'cacheService'           => $this->cache,
        ]);
    }

    private function fullFilter(string $from): DeleteRecordsByFilterRequest
    {
        return new DeleteRecordsByFilterRequest('test', $from, '2026-10-01T00:00', '2026-10-30T00:00', 'app_home', '/foo', '500', '0.1', '2', '1048576', '2097152', 'example.com', 'alice');
    }
}
