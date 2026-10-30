<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Support;

use DateTimeImmutable;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Form\ClearPerformanceDataType;
use Nowo\PerformanceBundle\Form\DeleteRecordsByFilterType;
use Nowo\PerformanceBundle\Form\DeleteRecordType;
use Nowo\PerformanceBundle\Form\PerformanceFiltersType;
use Nowo\PerformanceBundle\Form\PurgeAccessRecordsType;
use Nowo\PerformanceBundle\Form\RecordFiltersType;
use Nowo\PerformanceBundle\Form\ReviewRouteDataType;
use Nowo\PerformanceBundle\Form\StatisticsEnvFilterType;
use Nowo\PerformanceBundle\Model\ClearPerformanceDataRequest;
use Nowo\PerformanceBundle\Model\DeleteRecordsByFilterRequest;
use Nowo\PerformanceBundle\Model\PurgeAccessRecordsRequest;
use Nowo\PerformanceBundle\Model\RecordFilters;
use Nowo\PerformanceBundle\Model\RouteDataWithAggregates;
use Nowo\PerformanceBundle\Model\StatisticsEnvFilter;
use Nowo\PerformanceBundle\Ui\PerformanceAreaNav;
use Nowo\PerformanceBundle\Ui\PerformancePageFactory;
use Nowo\PerformanceBundle\Ui\PerformancePages;
use Nowo\UiKitBundle\View\Pagination;
use ReflectionProperty;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function http_build_query;

/**
 * Page contexts of the REQ-UI-003 page contract test: the view models come from {@see PerformancePageFactory} (the
 * service the controller uses), the FormViews from the real form types (FormKit profile merger, CSRF), the other
 * variables mirror the controller.
 */
final class PageFixtures
{
    /**
     * @return array<string, mixed>
     */
    public static function index(bool $empty = false, bool $management = true, ?PerformancePageFactory $factory = null): array
    {
        $routes      = $empty ? [] : [self::route(1, 'app_home', 'GET', 0.042, 3, 0.004, 12_582_912, 120, [200 => 118, 404 => 2], true), self::route(2, 'app_product_edit', 'POST', 1.3, 64, 0.91, 62_914_560, 8, [200 => 6, 500 => 2])];
        $forms       = self::formFactory();
        $deleteForms = [];
        $reviewForms = [];
        if ($management) {
            foreach ($routes as $route) {
                $id               = (int) $route->getId();
                $deleteForms[$id] = $forms->create(DeleteRecordType::class, null, ['csrf_token_id' => 'delete_performance_record_' . $id])->createView();
                $reviewForms[$id] = $forms->create(ReviewRouteDataType::class, null, ['route_data' => $route->getRouteData(), 'enable_access_records' => true, 'csrf_token_id' => 'review_performance_record_' . $id])->createView();
            }
        }
        $filters = $forms->create(PerformanceFiltersType::class, null, ['environments' => ['dev', 'prod'], 'current_env' => 'dev', 'with_submit' => false])->createView();
        $clear   = $forms->create(ClearPerformanceDataType::class, new ClearPerformanceDataRequest('dev'), ['with_submit' => false])->createView();
        $factory ??= self::factory();
        $stats = ['total_routes' => 2, 'avg_queries' => 33.5, 'max_queries' => 64, 'avg_request_time' => 0.671, 'avg_query_time' => 0.457, 'max_request_time' => 1.3, 'max_query_time' => 0.91, 'total_records' => 128];

        return $factory->context([
            'page'                => $factory->index($routes, 'dev', new Pagination(1, 100, $empty ? 0 : 2, 1, 'nowo_performance.index', perPageParam: 'limit'), $filters, $management ? $clear : null, $deleteForms, $reviewForms, ['env' => 'dev'], true),
            'kpis'                => $factory->indexKpis($stats, 'dev', true),
            'trend'               => $empty ? ['labels' => [], 'values' => []] : ['labels' => ['Oct 24', 'Oct 25', 'Oct 26'], 'values' => [41.2, 0, 63.5]],
            'top_used_routes'     => $empty ? [] : [['name' => 'app_home', 'access_count' => 120]],
            'top_consumed_routes' => $empty ? [] : [['name' => 'app_product_edit', 'request_time' => 1.3]],
            'missingDependencies' => $empty ? [] : [['feature' => 'Async metrics', 'message' => 'Messenger is not installed.', 'install_command' => 'composer require symfony/messenger']],
            'reviewForms'         => $reviewForms,
            'thresholds'          => ['request_time' => ['warning' => 0.5, 'critical' => 1.0], 'query_count' => ['warning' => 20, 'critical' => 50], 'memory_usage' => ['warning' => 20.0, 'critical' => 50.0]],
            'dateTimeFormat'      => 'Y-m-d H:i:s',
            'dateFormat'          => 'Y-m-d H:i',
            'autoRefreshInterval' => $empty ? 0 : 30,
            'environment'         => 'dev',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function statistics(bool $empty = false, ?PerformancePageFactory $factory = null): array
    {
        $factory ??= self::factory();
        $metric = static fn (string $label, string $unit, float $scale): array => [
            'label'          => $label, 'unit' => $unit, 'count' => 2, 'mean' => 0.6 * $scale, 'median' => 0.6 * $scale, 'mode' => 0.04 * $scale,
            'std_dev'        => 0.6 * $scale, 'min' => 0.04 * $scale, 'max' => 1.3 * $scale, 'range' => 1.26 * $scale,
            'percentiles'    => [25 => 0.3 * $scale, 50 => 0.6 * $scale, 75 => 1.0 * $scale, 90 => 1.2 * $scale, 95 => 1.25 * $scale, 99 => 1.3 * $scale],
            'outliers_count' => 1, 'outliers' => [1.3 * $scale], 'distribution' => [1, 0, 0, 0, 0, 0, 0, 0, 0, 1], 'bucket_labels' => [0.04, 0.17, 0.29, 0.42, 0.54, 0.67, 0.8, 0.92, 1.05, 1.17],
        ];
        $stats  = $empty ? [] : ['request_time' => $metric('Request Time', 's', 1.0), 'query_count' => $metric('Query Count', '', 50.0), 'memory_usage' => $metric('Memory Usage', 'MB', 40.0)];
        $routes = [self::route(2, 'app_product_edit', 'POST', 1.3, 64, 0.91, 62_914_560, 8, [200 => 8])];
        $env    = self::formFactory()->create(StatisticsEnvFilterType::class, new StatisticsEnvFilter('dev'), ['environments' => ['dev', 'prod']])->createView();

        return $factory->context([
            'page'                     => $factory->statistics($stats, $empty ? 0 : 2, 'dev', $empty ? 0 : 1),
            'envForm'                  => $env,
            'advanced_stats'           => $stats,
            'routes_needing_attention' => $empty ? [] : ['slow_request_time' => [['route' => $routes[0], 'value' => 1.3, 'percentile' => 95]], 'high_query_count' => [['route' => $routes[0], 'value' => 64, 'percentile' => 95]], 'high_memory' => [['route' => $routes[0], 'value' => 60.0, 'percentile' => 95]], 'outliers' => []],
            'recommendations'          => $empty ? [] : [['type' => 'performance', 'priority' => 'high', 'title' => 'Slow 95th Percentile Response Time', 'description' => 'p95 is above one second.', 'action' => 'Profile app_product_edit.'], ['type' => 'traffic_distribution', 'priority' => 'low', 'title' => 'Uneven Traffic Distribution', 'description' => 'Most traffic goes to one route.', 'action' => null]],
            'correlations'             => $empty ? [] : ['request_time_vs_query_time' => ['coefficient' => 0.92, 'interpretation' => 'Strong positive', 'sample_size' => 2], 'request_time_vs_query_count' => ['coefficient' => 0.55, 'interpretation' => 'Moderate positive', 'sample_size' => 2], 'query_time_vs_query_count' => null, 'memory_vs_request_time' => ['coefficient' => 0.1, 'interpretation' => 'Weak', 'sample_size' => 2]],
            'efficiency'               => $empty ? [] : ['avg_query_ratio' => 70.0, 'query_bottleneck_routes' => [['route' => $routes[0], 'query_ratio' => 85.0, 'request_time' => 1.3, 'query_count' => 64]], 'inefficient_routes' => [['route' => $routes[0], 'request_time' => 1.3, 'query_count' => null]]],
            'traffic_distribution'     => $empty ? [] : ['traffic_concentration' => 93.8, 'total_accesses' => 128, 'hot_paths' => [['route' => $routes[0], 'access_count' => 120, 'percentage' => 93.8]]],
            'environment'              => 'dev',
            'environments'             => ['dev', 'prod'],
            'total_routes'             => $empty ? 0 : 2,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function accessStatistics(bool $empty = false, bool $management = true, ?PerformancePageFactory $factory = null): array
    {
        $factory ??= self::factory();
        $forms  = self::formFactory();
        $start  = new DateTimeImmutable('2026-10-23 00:00:00');
        $end    = new DateTimeImmutable('2026-10-30 10:00:00');
        $byHour = [];
        for ($hour = 0; $hour < 24; ++$hour) {
            $count    = $empty ? 0 : ($hour * 7) % 23;
            $byHour[] = ['hour' => $hour, 'count' => $count, 'avg_response_time' => 0.05, 'status_codes' => $count > 0 ? [200 => $count] : []];
        }
        $heatmap = [];
        for ($day = 0; $day < 7; ++$day) {
            for ($hour = 0; $hour < 24; ++$hour) {
                $heatmap[$day][$hour] = $empty ? 0 : ($day + $hour) % 5;
            }
        }
        $filters = $forms->create(RecordFiltersType::class, new RecordFilters($start, $end, 'dev'), ['environments' => ['dev', 'prod'], 'available_routes' => ['app_home'], 'record_fields' => false])->createView();
        $delete  = $forms->create(DeleteRecordsByFilterType::class, new DeleteRecordsByFilterRequest('dev', 'access_statistics', '2026-10-23T00:00', '2026-10-30T10:00', '', ''), ['from_value' => 'access_statistics', 'with_submit' => false])->createView();
        $total   = $empty ? 0 : 245;

        return $factory->context([
            'page'                      => $factory->accessStatistics($total, $start, $end, $byHour, ['env' => 'dev'], $management ? $delete : null),
            'filterForm'                => $filters,
            'statistics_by_hour'        => $byHour,
            'statistics_by_day_of_week' => $empty ? [] : [['day_of_week' => 1, 'day_name' => 'Monday', 'count' => 120, 'avg_response_time' => 0.04, 'status_codes' => [200 => 118, 404 => 2]], ['day_of_week' => 2, 'day_name' => 'Tuesday', 'count' => 125, 'avg_response_time' => 0.06, 'status_codes' => [200 => 120, 500 => 5]]],
            'statistics_by_month'       => $empty ? [] : [['month' => 10, 'month_name' => 'October', 'count' => 245, 'avg_response_time' => 0.05, 'status_codes' => [200 => 238, 302 => 7]]],
            'heatmap_data'              => $heatmap,
            'total_access_count'        => $total,
            'environment'               => 'dev',
            'start_date'                => $start,
            'end_date'                  => $end,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function accessRecords(bool $empty = false, bool $management = true, ?PerformancePageFactory $factory = null): array
    {
        $factory ??= self::factory();
        $forms   = self::formFactory();
        $route   = self::route(1, 'app_home', 'GET', 0.04, 3, 0.004, 12_582_912, 2, [200 => 2])->getRouteData();
        $records = $empty ? [] : [
            self::record(11, $route, '2026-10-30 09:12:01', 200, 0.041, 3, 0.004, 12_582_912, '/', 'https://example.com/', 'alice'),
            self::record(12, $route, '2026-10-30 09:15:44', 500, 1.2, 64, 0.9, 62_914_560, '/?page=2', null, null),
        ];
        $filters = $forms->create(RecordFiltersType::class, new RecordFilters(null, null, 'dev'), ['environments' => ['dev', 'prod'], 'available_routes' => ['app_home'], 'with_submit' => false])->createView();
        $delete  = $forms->create(DeleteRecordsByFilterType::class, new DeleteRecordsByFilterRequest('dev', 'access_records', null, null, '', ''), ['from_value' => 'access_records', 'with_submit' => false])->createView();
        $purge   = $forms->create(PurgeAccessRecordsType::class, new PurgeAccessRecordsRequest('dev', PurgeAccessRecordsRequest::PURGE_OLDER_THAN, 30), ['environments' => ['dev', 'prod'], 'default_days' => 30])->createView();

        return $factory->context([
            'page'           => $factory->accessRecords($records, new Pagination(1, 50, $empty ? 0 : 2, 1, 'nowo_performance.access_records'), $filters, ['env' => 'dev'], $management ? $delete : null, $management, 'accessedAt', 'desc'),
            'purgeForm'      => $purge,
            'dateTimeFormat' => 'Y-m-d H:i:s',
            'environment'    => 'dev',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function diagnose(bool $healthy = false, ?PerformancePageFactory $factory = null): array
    {
        $factory ??= self::factory();
        $diagnostic = [
            'configuration'       => ['bundle_enabled' => true, 'track_queries' => true, 'sampling_rate' => 1.0, 'connection_name' => 'default', 'ignore_routes' => ['_wdt*', '_profiler*'], 'allowed_environments' => []],
            'environment'         => ['current' => 'dev', 'allowed' => ['dev', 'test'], 'is_allowed' => true],
            'database_connection' => ['connection_name' => 'default', 'connected' => true, 'error' => null, 'database_name' => 'app', 'driver' => 'pdo_mysql'],
            'table_status'        => ['main_table_exists' => true, 'missing_columns' => [], 'records_table_name' => 'routes_data_records'],
            'data_status'         => ['has_data' => true, 'total_records' => 2, 'first_record_date' => new DateTimeImmutable('2026-10-23 08:00:00'), 'records_by_environment' => ['dev' => 2]],
            'route_tracking'      => [],
            'subscriber_status'   => ['tracking_conditions' => ['enabled' => true, 'sampled' => false]],
            'issues'              => $healthy ? [] : ['The routes_data_records table is missing.'],
            'warnings'            => $healthy ? [] : ['No recent activity.'],
            'suggestions'         => $healthy ? [] : ['Run nowo:performance:create-records-table.'],
        ];

        return $factory->context([
            'page'       => $factory->diagnose($diagnostic),
            'diagnostic' => $diagnostic,
        ]);
    }

    public static function factory(?PerformancePages $pages = null, bool $accessRecords = true): PerformancePageFactory
    {
        $urls = self::urlGenerator();

        return new PerformancePageFactory($urls, new PerformanceAreaNav($urls, $accessRecords), $pages ?? new PerformancePages());
    }

    /**
     * @param array<int, int> $statusCodes
     */
    public static function route(int $id, string $name, string $method, float $requestTime, int $queries, float $queryTime, int $memory, int $accesses, array $statusCodes, bool $reviewed = false): RouteDataWithAggregates
    {
        $route = (new RouteData())->setEnv('dev')->setName($name)->setHttpMethod($method)->setLastAccessedAt(new DateTimeImmutable('2026-10-30 09:15:44'));
        if ($reviewed) {
            $route->setReviewed(true)->setReviewedAt(new DateTimeImmutable('2026-10-29 17:00:00'))->setQueriesImproved(true)->setTimeImproved(false);
        }
        (new ReflectionProperty(RouteData::class, 'id'))->setValue($route, $id);

        return new RouteDataWithAggregates($route, [
            'request_time'  => $requestTime,
            'total_queries' => $queries,
            'query_time'    => $queryTime,
            'memory_usage'  => $memory,
            'access_count'  => $accesses,
            'status_codes'  => $statusCodes,
        ]);
    }

    public static function formFactory(): FormFactoryInterface
    {
        $builder = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension(new class implements CsrfTokenManagerInterface {
                public function getToken(string $tokenId): CsrfToken
                {
                    return new CsrfToken($tokenId, 'token-' . $tokenId);
                }

                public function refreshToken(string $tokenId): CsrfToken
                {
                    return $this->getToken($tokenId);
                }

                public function removeToken(string $tokenId): ?string
                {
                    return null;
                }

                public function isTokenValid(CsrfToken $token): bool
                {
                    return true;
                }
            }));
        foreach ([ClearPerformanceDataType::class, DeleteRecordType::class, DeleteRecordsByFilterType::class, PerformanceFiltersType::class, PurgeAccessRecordsType::class, RecordFiltersType::class, ReviewRouteDataType::class, StatisticsEnvFilterType::class] as $class) {
            $builder->addType(FormKitTestSupport::withMerger(new $class()));
        }

        return $builder->getFormFactory();
    }

    private static function record(int $id, RouteData $route, string $at, int $status, float $time, int $queries, float $queryTime, int $memory, string $path, ?string $referer, ?string $user): RouteDataRecord
    {
        $record = (new RouteDataRecord())
            ->setRouteData($route)
            ->setAccessedAt(new DateTimeImmutable($at))
            ->setStatusCode($status)
            ->setResponseTime($time)
            ->setTotalQueries($queries)
            ->setQueryTime($queryTime)
            ->setMemoryUsage($memory)
            ->setRoutePath($path)
            ->setReferer($referer)
            ->setUserIdentifier($user);
        (new ReflectionProperty(RouteDataRecord::class, 'id'))->setValue($record, $id);

        return $record;
    }

    private static function urlGenerator(): UrlGeneratorInterface
    {
        return new class implements UrlGeneratorInterface {
            private RequestContext $context;

            public function __construct()
            {
                $this->context = new RequestContext();
            }

            /**
             * @param array<string, mixed> $parameters
             */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '/' . $name . ($parameters === [] ? '' : '?' . http_build_query($parameters));
            }

            public function setContext(RequestContext $context): void
            {
                $this->context = $context;
            }

            public function getContext(): RequestContext
            {
                return $this->context;
            }
        };
    }
}
