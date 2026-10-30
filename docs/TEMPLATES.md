# Templates

UiKit page contract ([REQ-UI-003](https://github.com/nowo-tech/UiKitBundle/blob/main/docs/PAGE_CONTRACT.md), since
3.15): every dashboard page extends a UiKit scaffold and receives a view model (`page`) built by
`Nowo\PerformanceBundle\Ui\PerformancePageFactory`. The scaffold renders the chrome (header, area tabs, actions,
filters, table frame, row actions, confirm dialogs, pagination, empty state, flashes); the bundle fills data blocks
only, with `nowo-ui-*` classes and `nowo_ui_class()` theme slots (bundle name `NowoPerformanceBundle` for
`nowo_ui_kit.theme.bundles` exceptions). The contract test is `tests/Integration/Contract/PageContractTest.php`.

## Table of contents

- [Pages](#pages)
- [View models](#view-models)
- [Shared partials](#shared-partials)
- [Unique pages](#unique-pages)
- [Per-page overrides](#per-page-overrides)
- [Layout](#layout)
- [Allowed exceptions](#allowed-exceptions)

## Pages

| Page | Template | Scaffold | View model | Data blocks filled |
| --- | --- | --- | --- | --- |
| Routes (dashboard home) | `Performance/index.html.twig` | `page/list` | `ListPage` (header actions: export CSV, export JSON, clear all records (POST + confirm, record management); filters: `PerformanceFiltersType`; columns: name (+ HTTP method badge), statusCodes, accessCount, requestTime, queryTime, totalQueries, memoryUsage, lastAccessedAt, review; rowActions: view records, review (dialog), delete (POST + confirm); `Pagination` (`limit` = per page); `EmptyState`) | `nowo_ui_list_filters` (dependency notice, KPI tiles, then `parent()`), `nowo_ui_list_row`, `nowo_ui_list_after` (trend chart, top routes), `nowo_ui_modals` (review dialogs), `nowo_ui_head` (auto refresh) |
| Advanced statistics | `Performance/statistics.html.twig` | `page/dashboard` | `DashboardPage` (KPI tiles: total routes, median request time / p95, mean queries / p95, routes needing attention) | `nowo_ui_dashboard_cards` (environment, recommendations, correlations, efficiency, traffic, routes needing attention, one card per metric with its histogram) |
| Access statistics | `Performance/access_statistics.html.twig` | `page/dashboard` | `DashboardPage` (header actions: view records, export records CSV / JSON, delete records matching the filter (POST + confirm, record management); KPI tiles: total accesses, period, busiest hour, average response time) | `nowo_ui_dashboard_cards` (by hour, heatmap, by day of week, by month — days with accesses only —, filters) |
| Access records | `Performance/access_records.html.twig` | `page/list` | `ListPage` (header actions: export records CSV / JSON, delete records matching the filter (POST + confirm), purge (dialog); filters: `RecordFiltersType` with `FilterField` placeholders; sortable columns: accessedAt, route, path, statusCode, responseTime, totalQueries, queryTime, memoryUsage (`sort` / `direction` query keys; `sort_by` / `order` still accepted); plus referer, user; `Pagination`; `EmptyState`) | `nowo_ui_list_row`, `nowo_ui_modals` (purge dialog) |
| Diagnose | `Performance/diagnose.html.twig` | `page/show` | `ShowPage` (one `DetailSection` per diagnostic group: configuration, environment, database connection, tables, recorded data, route tracking, subscriber; terms are the diagnostic keys) | `nowo_ui_show_sections` (issues / warnings / suggestions, then `parent()`) |

Every page header carries the area navigation (`PageHeader::tabs`, `Nowo\PerformanceBundle\Ui\PerformanceAreaNav`):
Routes · Statistics · Access statistics · Access records (both with `enable_access_records`) · Diagnose. The same
navigation is published as `nowo_ui_area_nav('performance')` for host menus (DashboardMenu).

## View models

| Page | Extra variables (besides `page`) |
| --- | --- |
| Routes | `kpis` (`list<KpiTile>`), `trend` (`{labels, values}`, average request time per day in ms), `top_used_routes`, `top_consumed_routes`, `missingDependencies`, `reviewForms`, `thresholds`, `dateTimeFormat`, `dateFormat`, `autoRefreshInterval`, `environment`; 3.14 variables kept for overrides: `routes`, `stats`, `paginator`, `form`, `clearForm`, `deleteForms`, `environments`, … |
| Advanced statistics | `envForm`, `advanced_stats`, `routes_needing_attention`, `recommendations`, `correlations`, `efficiency`, `traffic_distribution`, `environment`, `environments`, `total_routes` |
| Access statistics | `filterForm`, `statistics_by_hour`, `statistics_by_day_of_week`, `statistics_by_month`, `heatmap_data`, `total_access_count`, `environment`, `start_date`, `end_date`; 3.14: `deleteByFilterForm`, `available_routes`, … |
| Access records | `purgeForm`, `dateTimeFormat`, `environment`; 3.14: `paginated_data`, `filterForm`, `deleteByFilterForm`, `sort_by`, `order`, … |
| Diagnose | `diagnostic` (raw array) |

FormViews that back page actions (`clearForm`, `deleteForms`, `deleteByFilterForm`, the filter forms) are rendered by
the scaffold: an override must not render them a second time.

## Shared partials

| Template | Purpose |
| --- | --- |
| `Performance/_kpis.html.twig` | KPI tiles outside the dashboard scaffold (same markup and `kpis` / `kpi*` slots). |
| `Performance/_card.html.twig` | Card through the `card` / `card_header` / `card_title` / `card_intro` / `card_body` slots. |
| `Performance/_bar_chart.html.twig` | Server-rendered bar chart (inline SVG, `currentColor`, `<title>` per bar; no JavaScript). |
| `Performance/_heatmap.html.twig` | Server-rendered access heatmap (inline SVG, 7 × 24). |
| `Performance/_legacy_layout.html.twig` | Deprecated bridge around a 3.14 layout (see [Layout](#layout)). |
| `components/Statistics.html.twig`, `Filters.html.twig`, `Charts.html.twig`, `RoutesTable.html.twig` | Deprecated Twig components (`nowo_performance.*`), rendered through the partials above; removed in 4.0. |

Modals use the UiKit `dialog` partial (review, purge); destructive actions use the UiKit confirm `<dialog>`; flashes,
empty states and pagination come from UiKit. The bundle ships no local copy of those partials.

## Unique pages

- (none) — every page extends a scaffold.

## Per-page overrides

`nowo_performance.web_ui.templates.<page>` (`index`, `statistics`, `access_statistics`, `access_records`,
`diagnose`): a host template that extends `@!NowoPerformanceBundle/Performance/<page>.html.twig` and overrides one data
block. Unknown page ids fail the container build.

## Layout

The scaffolds extend `page.layout ?? nowo_ui_layout.admin`. `web_ui.layout_template: null` (default) uses
`nowo_ui_kit.layouts.admin`. A configured layout that defines `nowo_ui_content` is used as is; one that does not (a
3.14-style project `base.html.twig` with `title` / `stylesheets` / `body` / `javascripts`) is wrapped in
`Performance/_legacy_layout.html.twig` with a deprecation (removed in 4.0).

## Allowed exceptions

- (none) — `allowedClasses()` is not overridden in the contract test.
