<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Ui;

use DateTimeInterface;
use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Model\RouteDataWithAggregates;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\DashboardPage;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\DetailSection;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\FilterField;
use Nowo\UiKitBundle\View\KpiTile;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\UiKitBundle\View\ShowPage;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function array_filter;
use function array_is_list;
use function array_keys;
use function array_map;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;
use function json_encode;
use function max;
use function number_format;
use function round;
use function sprintf;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Builds the UiKit view models of the dashboard pages (REQ-UI-003): routes list (`page/list`), advanced statistics and
 * access statistics (`page/dashboard`), access records (`page/list`) and diagnose (`page/show`). Every POST action is a
 * CSRF {@see FormView} of the bundle form types behind a UiKit {@see Confirm}; forms with visible fields (review, purge)
 * open in a UiKit `dialog`. The controller and the page contract test share it. Stateless: safe in worker mode.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class PerformancePageFactory
{
    /** Id of the purge dialog on the access records page. */
    public const PURGE_DIALOG_ID = 'nowo-performance-purge';

    /** Id prefix of the review dialogs on the routes page (+ route id). */
    public const REVIEW_DIALOG_PREFIX = 'nowo-performance-review-';

    /** Sortable columns of the access records page (column key => repository sort key). */
    public const RECORD_SORTS = [
        'accessedAt'   => 'accessed_at',
        'route'        => 'route',
        'path'         => 'path',
        'statusCode'   => 'status_code',
        'responseTime' => 'response_time',
        'totalQueries' => 'total_queries',
        'queryTime'    => 'query_time',
        'memoryUsage'  => 'memory_usage',
    ];

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private PerformanceAreaNav $areaNav,
        private PerformancePages $pages = new PerformancePages(),
    ) {
    }

    /**
     * Template of a page (`web_ui.templates.<page>`, else the bundle template).
     */
    public function template(string $page): string
    {
        return $this->pages->template($page);
    }

    /**
     * Page variables plus the shared ones (legacy layout bridge).
     *
     * @param array<string, mixed> $vars
     *
     * @return array<string, mixed>
     */
    public function context(array $vars): array
    {
        return $this->pages->context($vars);
    }

    /**
     * Routes list (dashboard home): header actions (exports, clear all behind a confirm), filters, route rows with the
     * View records / Review (dialog) / Delete (confirm) row actions, pagination and empty state.
     *
     * @param list<RouteDataWithAggregates> $routes
     * @param array<int|string, FormView> $deleteForms route id => CSRF delete form
     * @param array<int|string, FormView> $reviewForms route id => review form (rendered in a dialog)
     * @param array<string, mixed> $query current query (exports keep the filters)
     */
    public function index(
        array $routes,
        string $env,
        Pagination $pagination,
        FormView $filters,
        ?FormView $clearForm = null,
        array $deleteForms = [],
        array $reviewForms = [],
        array $query = [],
        bool $enableAccessRecords = false,
        bool $trackStatusCodes = true,
    ): ListPage {
        $actions = [
            new Action('export', PerformancePages::label('dashboard.export_csv'), $this->url('nowo_performance.export_csv', $query), icon: 'download', attributes: ['data-testid' => 'performance-export-csv']),
            new Action('export', PerformancePages::label('dashboard.export_json'), $this->url('nowo_performance.export_json', $query), icon: 'file-earmark-code', attributes: ['data-testid' => 'performance-export-json']),
        ];
        if ($clearForm instanceof FormView) {
            $actions[] = new Action(
                'delete',
                PerformancePages::label('dashboard.clear_all_records'),
                $this->url('nowo_performance.clear'),
                method: 'POST',
                confirm: new Confirm(
                    PerformancePages::label('ui.confirm.clear_title', ['%env%' => $env]),
                    PerformancePages::label('confirmations.clear_all_records'),
                    PerformancePages::label('dashboard.clear_all_records'),
                ),
                icon: 'trash',
                variant: 'danger',
                attributes: ['data-testid' => 'performance-clear-all'],
                form: $clearForm,
            );
        }

        $rowActions = [];
        foreach ($routes as $route) {
            $id   = (int) $route->getId();
            $name = $route->getName();
            $list = [];
            if ($enableAccessRecords && $name !== null) {
                $list[] = new Action('view', PerformancePages::label('ui.routes.view_records'), $this->url('nowo_performance.access_records', ['route' => $name, 'env' => $env]), icon: 'eye', attributes: ['data-testid' => 'performance-route-records-' . $id]);
            }
            if (isset($reviewForms[$id])) {
                $list[] = new Action(
                    'edit',
                    PerformancePages::label($route->isReviewed() ? 'routes_table.edit_review' : 'routes_table.mark_as_reviewed'),
                    icon: $route->isReviewed() ? 'pencil-square' : 'check2-square',
                    attributes: ['data-testid' => 'performance-route-review-' . $id],
                    dialogId: self::REVIEW_DIALOG_PREFIX . $id,
                );
            }
            if (isset($deleteForms[$id])) {
                $list[] = new Action(
                    'delete',
                    PerformancePages::label('routes_table.delete_record'),
                    $this->url('nowo_performance.delete', ['id' => $id]),
                    method: 'POST',
                    confirm: new Confirm(
                        PerformancePages::label('ui.confirm.delete_route_title', ['%route%' => $name ?? '#' . $id]),
                        PerformancePages::label('confirmations.delete_record'),
                        PerformancePages::label('routes_table.delete'),
                    ),
                    icon: 'trash',
                    variant: 'danger',
                    attributes: ['data-testid' => 'performance-route-delete-' . $id],
                    form: $deleteForms[$id],
                );
            }
            $rowActions[$id] = $list;
        }

        $columns = [
            new Column('name', PerformancePages::label('routes_table.route_name'), required: true),
        ];
        if ($trackStatusCodes) {
            $columns[] = new Column('statusCodes', PerformancePages::label('routes_table.status_codes'), priority: 3);
        }
        $columns[] = new Column('accessCount', PerformancePages::label('routes_table.access_count'), align: 'end');
        $columns[] = new Column('requestTime', PerformancePages::label('routes_table.request_time'), align: 'end');
        $columns[] = new Column('queryTime', PerformancePages::label('routes_table.query_time'), align: 'end', priority: 2);
        $columns[] = new Column('totalQueries', PerformancePages::label('routes_table.total_queries'), align: 'end');
        $columns[] = new Column('memoryUsage', PerformancePages::label('routes_table.memory_usage'), align: 'end', priority: 2);
        $columns[] = new Column('lastAccessedAt', PerformancePages::label('routes_table.last_accessed_at'), priority: 3);
        if ($reviewForms !== []) {
            $columns[] = new Column('review', PerformancePages::label('routes_table.review_status'), priority: 2);
        }

        return new ListPage(
            header: new PageHeader(
                PerformancePages::label('dashboard.title'),
                PerformancePages::label('dashboard.subtitle'),
                $actions,
                tabs: $this->areaNav->forPage('index'),
            ),
            columns: $columns,
            rows: $routes,
            filters: $filters,
            rowActions: static fn (RouteDataWithAggregates $route): array => $rowActions[(int) $route->getId()] ?? [],
            pagination: $pagination,
            empty: new EmptyState(null, PerformancePages::label('dashboard.no_routes_found')),
            filtersClearHref: $this->url('nowo_performance.index'),
            caption: PerformancePages::label('routes_table.title'),
            bundle: PerformancePages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'performance-routes', 'data-env' => $env],
            actionsLabel: PerformancePages::label('routes_table.actions'),
            filterFields: [new FilterField('path', '/path')],
            captionVisible: false,
        );
    }

    /**
     * KPI tiles of the routes page (statistics of the environment).
     *
     * @param array<string, mixed> $stats {@see PerformanceController::index()} stats
     *
     * @return list<KpiTile>
     */
    public function indexKpis(array $stats, string $env, bool $enableAccessRecords = false): array
    {
        $tiles = [
            new KpiTile(PerformancePages::label('statistics.total_routes'), $this->int($stats['total_routes'] ?? 0)),
            new KpiTile(PerformancePages::label('statistics.avg_request_time'), $this->ms($stats['avg_request_time'] ?? 0.0), PerformancePages::label('ui.kpi.max', ['%value%' => $this->ms($stats['max_request_time'] ?? 0.0)])),
            new KpiTile(PerformancePages::label('statistics.avg_query_time'), $this->ms($stats['avg_query_time'] ?? 0.0), PerformancePages::label('ui.kpi.max', ['%value%' => $this->ms($stats['max_query_time'] ?? 0.0)])),
            new KpiTile(PerformancePages::label('statistics.avg_queries'), $this->number($stats['avg_queries'] ?? 0.0, 1), PerformancePages::label('ui.kpi.max', ['%value%' => $this->int($stats['max_queries'] ?? 0)])),
        ];
        if ($enableAccessRecords) {
            $tiles[] = new KpiTile(
                PerformancePages::label('statistics.total_records'),
                $this->int($stats['total_records'] ?? 0),
                PerformancePages::label('statistics.view_records'),
                $this->url('nowo_performance.access_records', ['env' => $env]),
                'info',
                true,
            );
        }

        return $tiles;
    }

    /**
     * Advanced statistics (`page/dashboard`): KPI tiles of the request-time distribution; the cards (environment,
     * recommendations, correlations, efficiency, traffic, routes needing attention, one card per metric) are data blocks.
     *
     * @param array<string, array<string, mixed>> $advancedStats metric => detailed statistics
     */
    public function statistics(array $advancedStats, int $totalRoutes, string $env, int $attentionCount = 0): DashboardPage
    {
        $request = $advancedStats['request_time'] ?? [];
        $queries = $advancedStats['query_count'] ?? [];

        return new DashboardPage(
            header: new PageHeader(
                PerformancePages::label('statistics_page.title'),
                PerformancePages::label('statistics_page.subtitle'),
                [],
                tabs: $this->areaNav->forPage('statistics'),
            ),
            kpis: [
                new KpiTile(PerformancePages::label('statistics.total_routes'), $this->int($totalRoutes), PerformancePages::label('ui.kpi.environment', ['%env%' => $env])),
                new KpiTile(PerformancePages::label('ui.kpi.median_request_time'), $this->ms($request['median'] ?? 0.0), PerformancePages::label('ui.kpi.p95', ['%value%' => $this->ms($request['percentiles'][95] ?? 0.0)])),
                new KpiTile(PerformancePages::label('ui.kpi.mean_queries'), $this->number($queries['mean'] ?? 0.0, 1), PerformancePages::label('ui.kpi.p95', ['%value%' => $this->number($queries['percentiles'][95] ?? 0.0, 1)])),
                new KpiTile(PerformancePages::label('statistics_page.routes_needing_attention'), $this->int($attentionCount), null, null, $attentionCount > 0 ? 'warning' : 'success'),
            ],
            bundle: PerformancePages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'performance-statistics', 'data-env' => $env],
        );
    }

    /**
     * Access statistics (`page/dashboard`): header actions (records list, exports, delete matching records behind a
     * confirm), KPI tiles; the cards (filters, by hour, heatmap, by weekday, by month) are data blocks.
     *
     * @param array<string, mixed> $query filter query (records list and exports keep it)
     * @param list<array<string, mixed>> $byHour {@see RouteDataRecordRepository::getStatisticsByHour()}
     */
    public function accessStatistics(
        int $totalAccesses,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        array $byHour,
        array $query = [],
        ?FormView $deleteByFilterForm = null,
        string $dateFormat = 'Y-m-d H:i',
    ): DashboardPage {
        $actions = [
            new Action('view', PerformancePages::label('access_statistics.view_records'), $this->url('nowo_performance.access_records', $query), icon: 'list-ul', attributes: ['data-testid' => 'performance-view-records']),
            new Action('export', PerformancePages::label('access_statistics.export_records_csv'), $this->url('nowo_performance.export_records_csv', $query), icon: 'download'),
            new Action('export', PerformancePages::label('access_statistics.export_records_json'), $this->url('nowo_performance.export_records_json', $query), icon: 'file-earmark-code'),
        ];
        if ($deleteByFilterForm instanceof FormView) {
            $actions[] = $this->deleteByFilterAction($deleteByFilterForm);
        }

        $busiest = null;
        $avg     = 0.0;
        $weight  = 0;
        foreach ($byHour as $row) {
            $count = $this->toInt($row['count'] ?? 0);
            if ($count > 0 && ($busiest === null || $count > $this->toInt($busiest['count'] ?? 0))) {
                $busiest = $row;
            }
            $avg += $this->toFloat($row['avg_response_time'] ?? 0.0) * $count;
            $weight += $count;
        }

        return new DashboardPage(
            header: new PageHeader(
                PerformancePages::label('access_statistics.title'),
                PerformancePages::label('ui.access_statistics.intro'),
                $actions,
                tabs: $this->areaNav->forPage('access_statistics'),
            ),
            kpis: [
                new KpiTile(PerformancePages::label('access_statistics.total_accesses'), $this->int($totalAccesses)),
                new KpiTile(PerformancePages::label('access_statistics.period'), $startDate->format($dateFormat), PerformancePages::label('ui.kpi.until', ['%date%' => $endDate->format($dateFormat)])),
                new KpiTile(PerformancePages::label('ui.kpi.busiest_hour'), $busiest !== null ? sprintf('%02d:00', $this->toInt($busiest['hour'] ?? 0)) : '—', $busiest !== null ? PerformancePages::label('ui.kpi.requests', ['%count%' => $this->toInt($busiest['count'] ?? 0)]) : null),
                new KpiTile(PerformancePages::label('access_statistics.avg_response_time'), $this->ms($weight > 0 ? $avg / $weight : 0.0)),
            ],
            bundle: PerformancePages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'performance-access-statistics'],
        );
    }

    /**
     * Access records (`page/list`): filters, sortable columns, pagination; header actions: statistics, exports and,
     * with record management, delete matching records (confirm) and purge (dialog).
     *
     * @param list<RouteDataRecord> $records
     * @param array<string, mixed> $query filter query (exports keep it)
     */
    public function accessRecords(
        array $records,
        Pagination $pagination,
        FormView $filters,
        array $query = [],
        ?FormView $deleteByFilterForm = null,
        bool $purge = false,
        ?string $sort = null,
        string $direction = 'desc',
    ): ListPage {
        $actions = [
            new Action('export', PerformancePages::label('access_statistics.export_records_csv'), $this->url('nowo_performance.export_records_csv', $query), icon: 'download', attributes: ['data-testid' => 'performance-records-export-csv']),
            new Action('export', PerformancePages::label('access_statistics.export_records_json'), $this->url('nowo_performance.export_records_json', $query), icon: 'file-earmark-code'),
        ];
        if ($deleteByFilterForm instanceof FormView) {
            $actions[] = $this->deleteByFilterAction($deleteByFilterForm);
        }
        if ($purge) {
            $actions[] = new Action('custom', PerformancePages::label('access_statistics.purge_records_title'), icon: 'eraser', variant: 'danger', attributes: ['data-testid' => 'performance-purge-open'], dialogId: self::PURGE_DIALOG_ID);
        }

        $columns = [];
        foreach ([
            'accessedAt'   => ['access_statistics.timestamp', 'start', null, true],
            'route'        => ['access_statistics.route', 'start', null, false],
            'path'         => ['access_statistics.path', 'start', 2, false],
            'statusCode'   => ['access_statistics.status_code', 'start', null, false],
            'responseTime' => ['access_statistics.response_time', 'end', null, false],
            'totalQueries' => ['access_statistics.total_queries', 'end', 2, false],
            'queryTime'    => ['access_statistics.query_time', 'end', 3, false],
            'memoryUsage'  => ['access_statistics.memory_usage', 'end', 3, false],
            'referer'      => ['access_statistics.referer', 'start', 3, false],
            'user'         => ['access_statistics.user', 'start', 3, false],
        ] as $key => [$label, $align, $priority, $required]) {
            $columns[] = new Column($key, PerformancePages::label($label), isset(self::RECORD_SORTS[$key]), $align, required: $required, priority: $priority);
        }

        return new ListPage(
            header: new PageHeader(
                PerformancePages::label('access_statistics.records'),
                PerformancePages::label('ui.access_records.intro'),
                $actions,
                tabs: $this->areaNav->forPage('access_records'),
            ),
            columns: $columns,
            rows: $records,
            filters: $filters,
            pagination: $pagination,
            empty: new EmptyState(null, PerformancePages::label('access_statistics.no_records')),
            filtersClearHref: $this->url('nowo_performance.access_records'),
            sort: $sort,
            direction: $direction,
            caption: PerformancePages::label('access_statistics.records'),
            bundle: PerformancePages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'performance-access-records'],
            filterFields: [
                new FilterField('min_query_time', '0.001'),
                new FilterField('max_query_time', '5'),
                new FilterField('min_memory_mb', '0'),
                new FilterField('max_memory_mb', '100'),
                new FilterField('referer', 'example.com'),
                new FilterField('user', 'username@example.com'),
            ],
            captionVisible: false,
        );
    }

    /**
     * Diagnose (`page/show`): one detail section per diagnostic group; issues / warnings / suggestions are rendered
     * by the page above the sections.
     *
     * @param array<string, mixed> $diagnostic {@see PerformanceController::diagnose()}
     */
    public function diagnose(array $diagnostic, string $dateTimeFormat = 'Y-m-d H:i:s'): ShowPage
    {
        $sections = [];
        foreach (['configuration', 'environment', 'database_connection', 'table_status', 'data_status', 'route_tracking', 'subscriber_status'] as $group) {
            $data = $diagnostic[$group] ?? null;
            if (!is_array($data) || $data === []) {
                continue;
            }
            $items = [];
            foreach ($data as $key => $value) {
                $items[] = $this->detailItem((string) $key, $value, $dateTimeFormat);
            }
            $sections[] = new DetailSection(PerformancePages::label('ui.diagnose.section.' . $group), $items, 'diagnose-' . $group);
        }

        $issues = is_array($diagnostic['issues'] ?? null) ? count($diagnostic['issues']) : 0;

        return new ShowPage(
            header: new PageHeader(
                PerformancePages::label('ui.diagnose.title'),
                PerformancePages::label('ui.diagnose.intro'),
                [],
                tabs: $this->areaNav->forPage('diagnose'),
            ),
            sections: $sections,
            bundle: PerformancePages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'performance-diagnose', 'data-issues' => (string) $issues],
        );
    }

    private function deleteByFilterAction(FormView $form): Action
    {
        return new Action(
            'delete',
            PerformancePages::label('access_statistics.delete_records_matching_filter'),
            $this->url('nowo_performance.delete_records_by_filter'),
            method: 'POST',
            confirm: new Confirm(
                PerformancePages::label('access_statistics.delete_records_matching_filter'),
                PerformancePages::label('access_statistics.delete_records_confirm'),
                PerformancePages::label('delete'),
            ),
            icon: 'trash',
            variant: 'danger',
            attributes: ['data-testid' => 'performance-delete-by-filter'],
            form: $form,
        );
    }

    private function detailItem(string $key, mixed $value, string $dateTimeFormat): DetailItem
    {
        if (is_bool($value)) {
            return new DetailItem($key, PerformancePages::label($value ? 'review.yes' : 'review.no'), badge: $value ? 'success' : 'neutral');
        }
        if ($value instanceof DateTimeInterface) {
            return new DetailItem($key, $value->format($dateTimeFormat));
        }
        if (in_array($value, [null, '', []], true)) {
            return new DetailItem($key, '—');
        }
        if (is_array($value)) {
            $text = array_is_list($value) && array_filter($value, static fn (mixed $v): bool => !is_scalar($v)) === []
                ? implode(', ', array_map(static fn (mixed $v): string => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v, $value))
                : (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return new DetailItem($key, $text);
        }

        return new DetailItem($key, is_scalar($value) ? $value : (string) json_encode($value));
    }

    /**
     * @param array<string, mixed> $params
     */
    private function url(string $route, array $params = []): string
    {
        return $this->urlGenerator->generate($route, array_filter($params, static fn (mixed $v): bool => $v !== null && $v !== ''));
    }

    private function ms(mixed $seconds): string
    {
        return number_format($this->toFloat($seconds) * 1000, 2) . ' ms';
    }

    private function int(mixed $value): string
    {
        return number_format($this->toInt($value));
    }

    private function number(mixed $value, int $decimals): string
    {
        return number_format(round($this->toFloat($value), $decimals), $decimals);
    }

    private function toFloat(mixed $value): float
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)) ? (float) $value : 0.0;
    }

    private function toInt(mixed $value): int
    {
        return (int) max(0, $this->toFloat($value));
    }

    /**
     * Keys of {@see self::RECORD_SORTS} by repository sort key (for the current sort of the records list).
     */
    public static function recordSortColumn(?string $repositorySort): ?string
    {
        foreach (self::RECORD_SORTS as $column => $sort) {
            if ($sort === $repositorySort) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Repository sort key of a records-list column key, null when the column is not sortable.
     */
    public static function recordSortKey(?string $column): ?string
    {
        return is_string($column) && in_array($column, array_keys(self::RECORD_SORTS), true) ? self::RECORD_SORTS[$column] : null;
    }
}
