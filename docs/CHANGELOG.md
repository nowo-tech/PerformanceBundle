# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[3.15.3] - 2026-10-31](#3153---2026-10-31)
- [[3.15.2] - 2026-10-30](#3152---2026-10-30)
- [[3.15.1] - 2026-10-30](#3151---2026-10-30)
- [[3.15.0] - 2026-10-30](#3150---2026-10-30)
- [[3.14.1] - 2026-10-30](#3141---2026-10-30)
- [[3.14.0] - 2026-10-29](#3140---2026-10-29)
- [[3.13.0] - 2026-10-28](#3130---2026-10-28)
- [[3.12.0] - 2026-10-28](#3120---2026-10-28)
- [[3.11.0] - 2026-10-28](#3110---2026-10-28)
- [[3.10.0] - 2026-10-26](#3100---2026-10-26)
- [[3.9.0] - 2026-10-24](#390---2026-10-24)
- [[3.8.0] - 2026-10-22](#380---2026-10-22)
- [[3.7.3] - 2026-10-18](#373---2026-10-18)
- [[3.7.2] - 2026-10-13](#372---2026-10-13)
- [[3.7.1] - 2026-10-13](#371---2026-10-13)
- [[3.7.0] - 2026-10-13](#370---2026-10-13)
- [[3.6.0] - 2026-10-13](#360---2026-10-13)
- [[3.5.2] - 2026-10-13](#352---2026-10-13)
- [[3.5.1] - 2026-10-09](#351---2026-10-09)
- [[3.5.0] - 2026-09-28](#350---2026-09-28)
- [[3.4.8] - 2026-09-27](#348---2026-09-27)
- [[3.4.7] - 2026-09-24](#347---2026-09-24)
- [[3.4.6] - 2026-08-29](#346---2026-08-29)
- [[3.4.5] - 2026-08-24](#345---2026-08-24)
- [[3.4.4] - 2026-08-19](#344---2026-08-19)
- [[3.4.3] - 2026-08-18](#343---2026-08-18)
- [[3.4.2] - 2026-08-07](#342---2026-08-07)
- [[3.4.1] - 2026-08-07](#341---2026-08-07)
- [[3.4.0] - 2026-08-04](#340---2026-08-04)
- [[3.3.1] - 2026-08-03](#331---2026-08-03)
- [[3.3.0] - 2026-08-03](#330---2026-08-03)
- [[3.2.2] - 2026-07-30](#322---2026-07-30)
- [[3.2.1] - 2026-07-29](#321---2026-07-29)
- [[3.2.0] - 2026-07-22](#320---2026-07-22)
- [[3.1.4] - 2026-07-16](#314---2026-07-16)
- [[3.1.3] - 2026-07-16](#313---2026-07-16)
- [[3.1.2] - 2026-07-14](#312---2026-07-14)
- [[3.1.1] - 2026-07-09](#311---2026-07-09)
- [[3.1.0] - 2026-07-09](#310---2026-07-09)
- [[3.0.1] - 2026-07-09](#301---2026-07-09)
- [[3.0.0] - 2026-06-30](#300---2026-06-30)
- [[2.0.21] - 2026-06-30](#2021---2026-06-30)
- [[2.0.20] - 2026-06-09](#2020---2026-06-09)
- [[2.0.19] - 2026-06-09](#2019---2026-06-09)
- [[2.0.18] - 2026-04-15](#2018---2026-04-15)
- [[2.0.17] - 2026-04-13](#2017---2026-04-13)
- [[2.0.16] - 2026-04-13](#2016---2026-04-13)
- [[2.0.15] - 2026-03-24](#2015---2026-03-24)
- [[2.0.14] - 2026-03-09](#2014---2026-03-09)
- [[2.0.13] - 2026-03-02](#2013---2026-03-02)
- [[2.0.12] - 2026-02-02](#2012---2026-02-02)
- [[2.0.11] - 2026-02-12](#2011---2026-02-12)
- [[2.0.10] - 2026-02-03](#2010---2026-02-03)
- [[2.0.9] - 2026-02-02](#209---2026-02-02)
- [[2.0.8] - 2026-01-28](#208---2026-01-28)
- [[2.0.7] - 2026-01-30](#207---2026-01-30)
- [[2.0.6] - 2026-01-30](#206---2026-01-30)
- [[2.0.5] - 2026-01-28](#205---2026-01-28)
- [[2.0.4] - 2026-01-28](#204---2026-01-28)
- [[2.0.3] - 2026-01-29](#203---2026-01-29)
- [[2.0.2] - 2026-01-29](#202---2026-01-29)
- [[2.0.1] - 2026-01-28](#201---2026-01-28)
- [[2.0.0] - 2026-01-28](#200---2026-01-28)
- [[1.0.8] - 2026-01-27](#108---2026-01-27)
- [[1.0.7] - 2026-01-27](#107---2026-01-27)
- [[1.0.6] - 2026-01-27](#106---2026-01-27)
- [[1.0.5] - 2026-01-27](#105---2026-01-27)
- [[1.0.4] - 2026-01-27](#104---2026-01-27)
- [[1.0.3] - 2026-01-27](#103---2026-01-27)
- [[1.0.2] - 2026-01-27](#102---2026-01-27)
- [[1.0.1] - 2026-01-27](#101---2026-01-27)
- [[1.0.0] - 2026-01-27](#100---2026-01-27)
- [[0.0.7] - 2026-01-27](#007---2026-01-27)
- [[0.0.6] - 2026-01-27](#006---2026-01-27)
- [[0.0.5] - 2026-01-27](#005---2026-01-27)
- [[0.0.4] - 2026-01-27](#004---2026-01-27)
- [[0.0.3] - 2026-01-27](#003---2026-01-27)
- [[0.0.2] - 2026-01-27](#002---2026-01-27)
- [[0.0.1] - 2026-01-26](#001---2026-01-26)

## [Unreleased]

## [3.15.3] - 2026-10-31

Twig templates pass twig-cs-fixer (formatting only, rendered HTML unchanged); twig lint in CI and release-check. See [UPGRADING](UPGRADING.md#to-3153).

### Fixed

- **Twig lint clean:** the 6 pre-existing `twig-cs-fixer` errors (hash brace spacing, quoted `_` hash key) are fixed with no change to the rendered HTML or e-mails; `make release-check` and CI now run `composer twig:lint`.

## [3.15.2] - 2026-10-30

Chart filters form type is a FormKit type (REQ-TWIG-005 rule 11, no markup change). See [UPGRADING](UPGRADING.md#to-3152).

### Changed

- **All page form types are FormKit Types (REQ-TWIG-005 rule 11):** the deprecated `ChartFiltersType` (chart filters of the `nowo_performance.Charts` component) now carries `#[FormKitConfig('performance')]` + `FormOptionsTrait` like the dashboard forms. Field names, ids (`chart-metric`, `chart-days`), GET method and markup are unchanged. A unit test asserts every `src/Form/*Type.php` is bound to the `performance` profile.

## [3.15.1] - 2026-10-30

Bundle routes are opt-in again: controllers are no longer autoconfigured, so a host's routing.controllers import does not expose them (REQ-SF-004 rule 7). See [UPGRADING](UPGRADING.md#to-3151).

### Fixed

- **Dashboard routes registered without being imported (REQ-SF-004 rule 7):** on Symfony >= 7.3 the `#[Route]` attributes on `PerformanceController` made the autoconfigured service carry the `routing.controller` tag, so a host with the default `controllers: { resource: routing.controllers }` got the `nowo_performance.*` routes without importing `@NowoPerformanceBundle/Resources/config/routes.yaml` and outside `web_ui.path_prefix` (still behind the dashboard roles). The controller service is no longer autoconfigured (explicit `controller.service_arguments` + `container.service_subscriber` tags); the routes exist only where the host imports them. Regression test with a kernel that only loads `routing.controllers`.

## [3.15.0] - 2026-10-30

Performance: UiKit page contract (scaffolds, dialogs, server-side SVG charts), PSR-20 clock, 100% coverage; review/Twig components/DDL fixes; SEC-004 BP: literal-boolean switches, bad dates ignored, export-ignore See [UPGRADING](UPGRADING.md#to-3150).

Performance: dashboard on the UiKit page contract (REQ-UI-003), canonical `web_ui` keys, PSR-20 clock. See
[UPGRADING](UPGRADING.md#unreleased).

### Security (SEC-004 re-audit 2026-10-30, Appendix BP: Pass (good) / Low)

- **L1 — `web_ui.enabled` must be a literal boolean.** The 3.15 load-time cast turned an `%env(bool:…)%` placeholder
  (also through the deprecated `dashboard.enabled`) into `true`, so an environment variable could no longer switch the
  dashboard off. Anything but `true` / `false` now fails the container build with an `InvalidConfigurationException`.
- **I1** — the same literal-boolean rule for `security.allow_unauthenticated` (an env placeholder was truthy in dev /
  test kernels).
- **I3** — malformed date query parameters (`date_from`, `date_to`, `start_date`, `end_date`) are ignored instead of
  raising a 500 (routes page, exports, access statistics, access records).
- **I4** — `.gitattributes` keeps `demo/`, `tests/`, `specs/`, `.specify/`, `.cursor/`, `.scripts/`, `scripts/`,
  `.githooks/`, `.github/`, `docs/images/` and the tooling configs out of the GitHub / Packagist dist archives.

### Added

- **UiKit page contract (REQ-UI-003).** The dashboard pages extend the UiKit scaffolds: routes and access records
  `page/list`, advanced statistics and access statistics `page/dashboard`, diagnose `page/show`. View models come from
  `Ui\PerformancePageFactory`; layout / per-page template resolution from `Ui\PerformancePages`. Page map, data blocks
  and view-model fields: [TEMPLATES.md](TEMPLATES.md). Contract test: `tests/Integration/Contract/PageContractTest.php`.
- **Canonical configuration keys** `web_ui.enabled`, `web_ui.path_prefix`, `web_ui.layout_template` (default `null` =
  `nowo_ui_kit.layouts.admin`) and `web_ui.templates.<page>` (`index`, `statistics`, `access_statistics`,
  `access_records`, `diagnose`). The route prefix is `%nowo_performance.web_ui.path_prefix%`.
- **Area navigation** `Ui\PerformanceAreaNav`: page-header tabs on every page and `nowo_ui_area_nav('performance')` for
  host menus (DashboardMenu).
- **Server-side charts**: daily request-time trend, metric histograms, accesses by hour and the access heatmap are
  inline SVG partials (`_bar_chart`, `_heatmap`); no chart library, no JavaScript.
- `with_submit` option (default `true`) on `PerformanceFiltersType`, `RecordFiltersType`, `ClearPerformanceDataType` and
  `DeleteRecordsByFilterType`: the pages pass `false` because the UiKit filters / Action render the button.
- **PSR-20 clock (REQ-DI-001).** `PerformanceController`, `PerformanceMetricsService` and `PurgeAccessRecordsCommand`
  take an optional `Psr\Clock\ClockInterface` (`symfony/clock` is now required); `RouteData::markAsReviewed()` /
  `RouteDataRepository::markAsReviewed()` accept an optional review time.

### Changed

- **Template contract.** Destructive actions (clear all, delete route, delete matching records) use the UiKit confirm
  `<dialog>` with their CSRF form; review and purge open in a UiKit `dialog`; flashes, filters, pagination and empty
  states come from UiKit. No inline `onclick` / `onsubmit` / `confirm()`, no CDN (Bootstrap, Tailwind, Chart.js).
  Auto refresh is a `<meta http-equiv="refresh">`; the statistics environment selector auto-submits through UiKit
  (`data-nowo-ui-autosubmit`).
- The form types and the FormKit `performance` profile carry no framework utility classes any more (`form-control`,
  `form-select`, `btn btn-*`, `mb-2`): the host form theme and the UiKit theme slots style them.
- The UiKit `css_framework` / `icon_set` seed only happens when the host still sets the deprecated
  `dashboard.css_framework` / `dashboard.template` and `nowo_ui_kit` has no `css_framework` of its own.
- `nowo-tech/ui-kit-bundle` constraint raised to `^2.17.3`.
- `api/chart-data` rounds averages / maxima to 4 decimals (was 2).
- **100% statement coverage without exclusions (REQ-TEST-003)**: `PerformanceController`, both table commands and
  `QueryTrackingMiddlewareRegistry` are no longer excluded in `phpunit.xml.dist`; `make test-coverage` and CI fail
  below 100%. Code that cannot run on the supported dependencies was removed instead of ignored: DBAL 2 fallbacks
  (`getSchemaManager()`, `quoteIdentifier()`, `Type::getType()`), impossible `catch` blocks, a dead `$limit >= 1000`
  branch, the internal-connection probe of the middleware registry and the composer.json / class heuristics of its
  DoctrineBundle version detection (Composer runtime data only). The CSV export streams throw a `RuntimeException`
  when `php://output` cannot be opened instead of silently returning nothing; diagnose always uses
  `Connection::createSchemaManager()`; `NotificationChannelsPass` builds its tagged iterator with one
  version-agnostic constructor call; the deprecated `LogHelper` no longer guards `function_exists('error_log')`. No `@codeCoverageIgnore` is left in `src/`:
  the DBAL 2 fallback of `TableStatusChecker`, the unreachable outer `catch` of `QueryTrackingConnectionSubscriber`
  and the pool guard of the statistics cache generation (now a `LogicException` invariant) are gone; the deprecated
  `ChartFiltersType` no longer sets an inline `onsubmit`. `make test-coverage` now fails on test failures too
  (`pipefail`) and runs without `--process-isolation` (PHPUnit `memory_limit` 1G).

### Deprecated

- `dashboard.enabled`, `dashboard.path`, `dashboard.prefix`, `dashboard.layout_template` → `web_ui.*`;
  `dashboard.css_framework` and `dashboard.template` → `nowo_ui_kit.css_framework` (removed in 4.0).
- A `layout_template` that does not render the UiKit canonical blocks is wrapped in
  `Performance/_legacy_layout.html.twig` (deprecated bridge, removed in 4.0).
- The Twig components `nowo_performance.Statistics`, `.Filters`, `.Charts`, `.RoutesTable` (they render through the new
  slot-based partials) and `ChartFiltersType`; the Twig globals `nowo_performance_layout_template` /
  `nowo_performance_css_framework`; the `DeleteRecordType` option `submit_attr_class` (ignored).

### Removed

- Templates `Performance/base.html.twig`, `Performance/layout.html.twig` (CDN demo shell) and the per-framework partials
  `Performance/components/_{statistics,filters,charts,routes_table}_{bootstrap,tailwind}.html.twig`, `_paginator`,
  `_dependency_modal`. The `chartFiltersForm` variable is no longer passed to the routes page.
- `NowoPerformanceBundle::boot()` no longer looks up the kernel in the container nor replaces the global VarDumper
  handler in CLI debug runs (REQ-DI-001).

### Fixed

- **Review from the dashboard crashed.** `review()` called an undefined `$this->trans()` after a successful review
  (fatal "Call to undefined method"); the controller now takes an optional `TranslatorInterface` (last constructor
  argument) for its flashes. With access records enabled, the review form was also rebuilt without `route_data` /
  `enable_access_records`, so every review was rejected as "Invalid form data".
- **Twig components were never registered**: the extension checked a `ComponentInterface` that
  `symfony/ux-twig-component` does not ship; it now checks `AsTwigComponent`.
- `nowo:performance:create-table` / `create-records-table`:
  - the MySQL `AUTO_INCREMENT` patch now matches only the `id` column (it also hit `route_data_id INT`, giving two auto
    columns and a failed `CREATE TABLE` for access records);
  - `--update --drop-obsolete` no longer treats the `route_data_id` join column as obsolete (dropped on
    PostgreSQL/SQLite, failed on MySQL because of the foreign key);
  - `--update` no longer flags the `id` column as changed on every run (the comparison includes the platform's
    auto-increment / identity declaration) and no longer emits the MySQL-only `MODIFY COLUMN ... AUTO_INCREMENT` repair
    on other platforms (a warning points to Doctrine migrations);
  - `MODIFY COLUMN id` no longer declares `AUTO_INCREMENT` twice;
  - a string field without a length no longer crashes on MySQL (`TypeError`); it falls back to `VARCHAR(255)`.
- Translated label for the statistics environment selector (was the literal `Environment`).
- Flex recipe: `security.access_roles: ['ROLE_ADMIN']` instead of the former `dashboard.roles: []`, which an empty list
  now refuses at container compilation.

## [3.14.1] - 2026-10-30

Performance: track_user and kernel environment detection wired (optional services) See [UPGRADING](UPGRADING.md#to-3141).

### Fixed

- **`track_user` and kernel environment detection never worked:** `PerformanceMetricsSubscriber` used
  `#[Autowire(service: '?security.helper')]` / `'?kernel'` / `'?stopwatch'`, which are not optional references (the
  attribute keeps `?` in the service id) and, the arguments being nullable, compiled to `null`. Access records never
  stored the logged-in user and the `environments` filter fell back to `APP_ENV` instead of the kernel environment.
  The security helper is now wired in `services.yaml` (`@?security.helper`, still optional without SecurityBundle) and
  the kernel is autowired by type. The stopwatch stays unwired on purpose (the query-time fallback resets it, so it must
  not be the profiler's debug stopwatch); behaviour unchanged. Kernel test: `PerformanceMetricsSubscriberWiringTest`.

## [3.14.0] - 2026-10-29

Performance: tokens redacted in stored referers/route params; CSV formula guard See [UPGRADING](UPGRADING.md#to-3140).

### Security (SEC-004 re-audit 2026-10-29, Low)

- **No secrets in access records.** The stored route path, referer and route parameters kept full query strings, so
  reset / OAuth / signed-URL tokens ended up in the database, the dashboard and the exports. Values of sensitive keys
  (`token`, `code`, `state`, `signature`, `password`, `secret`, `key`, `hash`, `expires`, `jwt`, `otp`, `session`, …)
  are stored as `[redacted]` (`Service\SensitiveDataRedactor`); fragments are dropped.
- **CSV formula injection.** Cells starting with `=`, `+`, `-`, `@`, TAB or CR are prefixed with `'` in the routes and
  access-records CSV exports (the referer is attacker-controlled).
- **Referer links.** The access-records pages link the referer only when it is an `http(s)://` URL; anything else is
  printed as text.
- **Bounded chart window.** `api/chart-data` clamps `days` to 1–365.

## [3.13.0] - 2026-10-28

Performance: the access-records list counts and paginates at most 10 000 records and clamps out-of-range pages. See [UPGRADING](UPGRADING.md#to-3130).

### Performance

- **Bounded access-records list.** Every page of the access records ran a full `COUNT` and an unbounded `OFFSET` on
  the growing records table. The list now counts and paginates at most 10 000 matching records
  (`RouteDataRecordRepository::MAX_LISTED`, totals shown as "10,000+"; narrow the filters to reach older records)
  and clamps out-of-range pages to the last one.

## [3.12.0] - 2026-10-28

Security: allow_unauthenticated is refused in production (prod or non-debug kernels). See [UPGRADING](UPGRADING.md#to-3120).

### Security

- **`allow_unauthenticated` refused in production.** The switch opens the performance dashboard to anonymous users; the
  container now refuses to compile with it in the `prod` environment or in any non-debug kernel outside `test*`
  environments (as ContactFormBundle). The demo keeps it in `dev` and requires `ROLE_ADMIN` in its production mode
  (`when@prod`).

## [3.11.0] - 2026-10-28

Security: empty role lists deny instead of granting everyone; CSRF fails closed without form CSRF protection. See [UPGRADING](UPGRADING.md#to-3110).

### Security

- **Empty role lists deny (P1):** `ConfigurablePerformanceAccessChecker` and the controller's BC fallback no longer treat
  an empty `security.access_roles` (or deprecated `dashboard.roles`, mapped into it) as "no role check": an empty list
  grants nothing. With `dashboard.enabled: true`, `PerformanceExtension` throws a `LogicException` at compile time
  when the list is empty and neither `security.access_checker` nor `security.allow_unauthenticated` is set.
- **CSRF fails closed (P2):** new `CsrfProtectionPass` refuses to compile when the dashboard is enabled without
  `security.csrf.token_manager` and `form.type_extension.csrf`, or with `framework.form.csrf_protection: false`. Before,
  clear / delete / review / purge / delete-by-filter accepted forged POSTs on such hosts. `symfony/security-csrf` is now
  a required dependency. See [UPGRADING](UPGRADING.md#unreleased).

### Fixed

- The dashboard no longer throws "The SecurityBundle is not registered" with `security.allow_unauthenticated: true` and
  no SecurityBundle: the access check passes a null user when no token storage exists.
- Demo (`demo/symfony8`) opens its login-less dashboard with `security.allow_unauthenticated: true` instead of an empty
  role list.

## [3.10.0] - 2026-10-26

Forms in the bundle views are Symfony Form Types rendered with form_start / child loop / form_end, no raw form controls (REQ-TWIG-005). See [UPGRADING](UPGRADING.md#to-3100).

### Changed

- **Developer tooling (REQ-MAKE-002):** `make release-check` runs the full REQ-MAKE-002 chain in order (ensure-up, co-author and open-PR gates, check-twig-extra, composer-sync, cs-fix, cs-check, rector-dry, phpstan, igor, validate-translations, test-coverage, demo release-check). `make check-no-raw-html-form` (`.scripts/check-no-raw-html-form.sh`, REQ-TWIG-005) now runs in the chain.
- **Dashboard charts (REQ-TWIG-005):** the metric / days selects of the "Performance Trends" chart are rendered from the new unmapped `ChartFiltersType` form (`form_start` / `form_label` / `form_widget` / child loop / `form_end`) in `_charts_bootstrap`, `_charts_tailwind` and the `Charts` Twig component; the controller passes `chartFiltersForm`, the component exposes `filtersForm`. Same ids and values for the chart script. See [UPGRADING](UPGRADING.md#unreleased).
- Demo: the product list delete button posts through a `CsrfOnlyType` Symfony Form (`form_start` / `form_end`, `handleRequest` + `isValid()`) instead of a raw `<form>` with a hand-rolled `_token` (REQ-TWIG-005).

## [3.9.0] - 2026-10-24

Bundle views render form bodies through the child loop (REQ-TWIG-003). See [UPGRADING](UPGRADING.md#to-390).

### Changed

- Bundle views render their Symfony forms through the child loop (`{% for child in form %}{% if not child.rendered %}{{ form_row(child) }}{% endif %}{% endfor %}`): access records / access statistics filters and the purge form, the dashboard clear-data form, the delete and review forms of the routes tables (both partials and the `RoutesTable` component), the statistics environment selector and the dashboard filters (`Filters` component and `_filters_*` partials keep their grouped custom layout and add the loop for the remaining children). Submit buttons are captured before the loop so they stay in their action rows. Fields that the FormType had but the template skipped are now rendered (`path` in the Tailwind access statistics and in the `Filters` component). `RecordFiltersType` gains a `record_fields` option (default `true`); the access statistics page sets it to `false`, so it keeps its six filters. The statistics environment selector gets its auto-submit `onchange` from the FormType `attr_extra` option instead of the template, so its `attr_class` is no longer dropped (REQ-TWIG-003).
- Demo: product create/edit forms use the child loop instead of `form_widget(form)` (REQ-TWIG-003).

## [3.8.0] - 2026-10-22

PSR-3 logger instead of error_log, release-check chain and CI gates, Rector PHP 8.2, demo fixes, dependency refresh. See [UPGRADING](UPGRADING.md#to-380).

### Changed

- Dependencies refreshed (Composer and pnpm/npm lock files of the bundle and its demos, campaign 2026-10-19).
- `.php-cs-fixer.dist.php`: `new ParallelConfig(2)` (REQ-CS-001).
- Logging goes through an injected PSR-3 `logger` (`PerformanceMetricsSubscriber`, `PerformanceMetricsService`, `NotificationService`, e-mail and webhook channels): diagnostics at `debug` (still gated by `enable_logging`), delivery failures at `warning`. `Helper\LogHelper` (`error_log`) is deprecated; `psr/log` is required (REQ-DI-001 / REQ-OBS-001).
- `release-check` is sequential and starts with `ensure-up`; the misspelled `check-twig-extra-since-release` prerequisite is replaced by `check-twig-extra`, and `check-open-prs` and `validate-translations` (now with key parity across the 12 shipped locales) are added; new `clean` target (REQ-MAKE-001 / REQ-MAKE-002 / REQ-MAKE-004).
- CI: coverage gate raised from 80% to 99%; `git-hygiene` checks the whole history (Claude trailers before the baseline stay grandfathered); new `Demo smoke` workflow (REQ-TEST-003 / REQ-GIT-001 / REQ-TEST-011).
- Rector targets the PHP 8.2 floor (`withPhpSets(php82: true)`) and its safe suggestions are applied (readonly classes, `??=`); rules that broke runtime contracts (empty Doctrine listener method, `#[Required]` optional properties, reflection-read test properties) are skipped in `rector.php` (REQ-CS-003); PHPUnit `@covers` / `@dataProvider` docblocks replaced (REQ-TEST-010); Twig extensions rely on autoconfigure (REQ-SF-003).
- Demo: `release-check` refreshes the bundle first (REQ-DEMO-007), Makefile uses `$(COMPOSE)` everywhere (REQ-MAKE-010), Caddyfiles set the timeout stack (REQ-RUNTIME-001), smoke follows redirects; the root dev PostgreSQL no longer publishes host port 5433 (REQ-DEMO-006).
- `composer.json`: keyword list trimmed, `support.docs` added (REQ-PKG-004).
- Docs: PSR evaluation (PSR-3 adopted, PSR-20 re-evaluated), SECURITY logging policy, CSRF section and AI-audit checklist row, Messenger retry/failure transport policy, demo timeout hierarchy, extension points of the non-final classes (REQ-CS-007, REQ-SEC-002/005, REQ-MSG-001, REQ-PHP-001).

### Fixed

- Webhook notification timeouts are covered by tests (timeout options, clamped minimum, logged failure) (REQ-RUNTIME-001).
- `.scripts/check-no-cursor-coauthor.sh` also runs inside git worktrees (`.git` file instead of directory).
- Demo `make test-coverage` called a non-existent `composer test` script (the demo has no PHPUnit suite): it now checks that the demo kernel boots, so the demo `release-check` runs end to end.
- Demo `make up` installs Composer dependencies in a one-off container before FrankenPHP starts: a fresh checkout crash-looped the worker on the missing `vendor/` and `up` failed ("service php is not running").

### Documentation

- Documentation: wave-1 REQ compliance — README documentation list order, GitHub stars and Coverage badges, canonical FrankenPHP worker claim, canonical issue templates, per-variable comments in the demo `.env.example`, CONTRIBUTING Cursor Agent settings, TOCs for long docs, Spec Kit inventory refresh (120/120, `NowoPerformanceBundle.*` catalog names, FR-SEC-001 and FR-TWIG-003).

## [3.7.3] - 2026-10-18

Allows nowo-tech/ui-kit-bundle 2.x (^1.4 || ^2.2) so hosts can move to UiKit 2.2. See [UPGRADING](UPGRADING.md#to-373).

### Changed

- Allows nowo-tech/ui-kit-bundle 2.x (`^1.4 || ^2.2`); the dev lock and the demo lock use 2.2.0. Templates are unchanged (compatibility release). With UiKit 2.x `nowo-ui.css` ships in `@layer nowo-ui`, so unlayered host/framework CSS wins equal-specificity conflicts with kit rules in the dashboard; to keep the 1.x cascade, override the `Performance/base.html.twig` stylesheets block and link `css/nowo-ui.unlayered.css` (UiKit 2.x only).

## [3.7.2] - 2026-10-13

### Fixed

- **Dev only — `composer test-coverage-90` / `test-coverage-100`:** the scripts were written as `"@test-coverage && php scripts/check-coverage.php …"`, so Composer appended `&& php … --min-percent=N` as arguments to PHPUnit, which rejected `--min-percent`. They now run PHPUnit with coverage and then `scripts/check-coverage.php` with the threshold as two script steps; `make test-coverage-100` uses the Composer script. `test-coverage` disables Composer's 300 s process timeout (the isolated coverage run takes ~4 min).
- **Dashboard filters — "Show/Hide Advanced Filters" toggle:** the Bootstrap filter card (`nowo_performance.Filters` component and `_filters_bootstrap.html.twig` include) rendered both labels at once because no CSS hid the inactive one until the collapse was toggled. The initial state is now rendered server-side: when no advanced filter has a value the panel is collapsed and only "Show Advanced Filters" is visible; when one is set the panel renders expanded (`.collapse.show`, `aria-expanded="true"`) with only "Hide Advanced Filters". The JS auto-expand on load was removed; the collapse events still swap the labels when toggled.

## [3.7.1] - 2026-10-13

### Fixed

- **FrankenPHP worker mode: metrics were never saved.** `PerformanceMetricsSubscriber::onKernelTerminate()` called `ob_start()` after the response had been sent; in worker mode the output layer is already closed, so PHP emitted `ob_start(): Failed to create buffer`, the debug error handler turned it into an exception and the request was never recorded (empty dashboard). The output buffer is now best effort: the failure is silenced/caught and recording continues without a buffer; a buffer is still only opened when none is active and always closed in `finally`. Per-request state keeps being cleared by `reset()` (`kernel.reset`).
- **Dashboard wrongly reported "Symfony UX Icons is required".** `DependencyChecker::isIconsAvailable()` probed `Symfony\UX\Icons\Twig\IconExtension`, which never existed; it now probes `UXIconsBundle` / `UXIconExtension` (present in every symfony/ux-icons 2.17+ and 3.x release). Other probes cleaned up: the non-existent `Symfony\UX\TwigComponent\ComponentInterface` and the legacy `twig_get_function()` checks were removed, and HttpClient availability now probes the `symfony/http-client` component (`HttpClient`) instead of the contracts interface, which can be installed without an implementation.
- **Access statistics / records filters showed raw keys** `access_statistics.all_routes` and `access_statistics.all_status_codes`. The "all" entries were choices rendered with `choice_translation_domain: false`; they are now the select's empty option, translated with the `NowoPerformanceBundle` domain in every locale. Submitting the empty option still means "all" (`null` route / status code).

## [3.7.0] - 2026-10-13

### Security

- **Webhook notifications:** the HTTP request now sets `max_redirects: 0`, so a redirect from the (validated, https/public) webhook URL can no longer forward the alert payload to an `http://` or private/internal host.

### Fixed

- **Records filters (path, referer, user):** `LIKE` patterns now escape `\` (first), `%` and `_` and use an explicit `ESCAPE '\'` clause. Previously a backslash in the filter was not escaped, and on SQLite (no default escape character) `%`/`_` in the filter matched nothing; the escape literal is quoted by the DBAL platform, so the clause is portable across MySQL/MariaDB, PostgreSQL and SQLite.

### Changed

- **Requirements:** Symfony floor raised from `^7.0` to `^7.4` (`^7.4 || ^8.0`), matching what CI actually tests; the redundant Symfony `7.0` CI matrix cell (which resolved to the latest 7.x anyway) was removed and the coverage runs now target 7.4. README/INSTALLATION updated.
- **CI:** new `PHPStan` job runs `composer phpstan` (level 8) on every push/PR.

## [3.6.0] - 2026-10-13

### Security

- Dashboard POST actions (clear, delete, review, purge) no longer redirect to a foreign `Referer` (open redirect, e.g. from the invalid-CSRF branch): only same-host http(s) referers are followed, anything else falls back to the dashboard.
- `POST /clear` now requires `dashboard.enable_record_management: true` like the other destructive actions (403 otherwise); the **Clear all records** button is hidden when it is disabled.
- Confirmation dialogs are JS-escaped (`|e('js')`): translations containing an apostrophe (fr "d'accès") broke the `onsubmit` handler and the purge / delete ran without confirmation.

### Fixed

- Sub-requests (`render(controller())`, ESI, the ErrorListener error page) no longer overwrite the main request's route and disable the collector: main requests that run sub-requests were never recorded, and with `track_sub_requests` the metrics went to the wrong route. Tracked sub-requests are now measured and recorded separately.
- The query counters are no longer reset by sub-requests, so the main request's query count includes every query.
- `PerformanceMetricsSubscriber` implements `ResetInterface` (`kernel.reset`) so per-request state never leaks between requests in long-running workers.
- Translations: added the 17 missing keys to ca, cs, pl, ru and tr; a test now enforces key parity across locales and that every template key exists.

### Changed

- `query_tracking_threshold` is deprecated and ignored: it never had any effect. Setting it triggers a deprecation notice; remove it from your configuration.
- Removed the unused `Performance/access_statistics_disabled.html.twig` template (never rendered; it referenced non-existent translation keys).

## [3.5.2] - 2026-10-13

### Changed

- Development: `composer.json` pins `config.platform.php` to 8.2.0 so the committed lock stays installable on the minimum PHP; CI overrides the platform per matrix cell.
- Repository: `.twig-cs-fixer.cache` is now git-ignored.

### Dependencies

- `nowo-tech/form-kit-bundle` 2.6.1 → 2.7.1 (development lock; #59).
- `doctrine/orm` 3.7.4 → 3.7.5 (development lock; #60).
- `nowo-tech/ui-kit-bundle` 1.9.1 → 1.11.0 (development lock; #61).

## [3.5.1] - 2026-10-09

### Fixed

- **Query timing:** `QueryTrackingCounters` and `QueryLogger` measure query duration with `hrtime()` instead of `microtime()`, so very fast queries no longer report `0` seconds (seen on PHP 8.5 CI).
- **Query metrics fallbacks:** `PerformanceMetricsSubscriber` now imports `Exception`; the unqualified `catch (Exception)` previously referred to a non-existent class in the bundle namespace, so errors from the query counters, profiler collector or stopwatch escaped instead of falling back silently.

### Changed

- Dependencies (Dependabot + lock refresh): `doctrine/orm` 3.7.4, FormKitBundle 2.6.1, UiKitBundle 1.9.1; dev PHPStan 2.3.1 (+ phpunit/symfony extensions 2.1), PHPUnit 10.5.66, PHP-CS-Fixer 3.95.27, Rector 2.7.0, `igor-php/igor-php` 0.10.1, `nowo-tech/phpstan-frankenphp` 1.2.3.
- Demo (Symfony 8): `doctrine/orm` 3.7.4, `doctrine/dbal` 4.5.0, Twig 3.30.0, HotReloadBundle 1.5.5, TwigInspectorBundle 1.1.7; regenerated `config/reference.php`.

## [3.5.0] - 2026-09-28

### Security

- Webhook channel only POSTs to `https://` public hosts (blocks http, localhost, private/reserved IPs).
- Route CSV/JSON exports are capped by `export.max_rows` (default **5000**, max **50000**).

### Changed

- **Doctrine ORM SortDirection:** replace string `'ASC'`/`'DESC'` in `#[ORM\OrderBy]` and QueryBuilder `orderBy`/`addOrderBy` with `SortDirection::Ascending`/`Descending` (doctrine/orm deprecation, https://github.com/doctrine/orm/issues/11313); require `doctrine/orm` `^3.7` where applicable.

## [3.4.8] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[3.15.3]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.15.3
[3.15.2]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.15.2
[3.15.1]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.15.1
[3.15.0]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.15.0
[3.14.1]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.14.1
[3.14.0]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.14.0
[3.13.0]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.13.0
[3.12.0]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.12.0
[3.11.0]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.11.0
[3.10.0]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.10.0
[3.9.0]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.9.0
[3.8.0]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.8.0
[3.7.3]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.7.3
[3.7.2]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.7.2
[3.7.1]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.7.1
[3.7.0]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.7.0
[3.6.0]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.6.0
[3.5.2]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.5.2
[3.5.1]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.5.1
[3.5.0]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.5.0
[3.4.8]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.4.8

## [3.4.7] - 2026-09-24

### Fixed

- **FrankenPHP worker mode (no kernel reset between requests)** – see [`docs/FRANKENPHP-WORKER-AUDIT.md`](FRANKENPHP-WORKER-AUDIT.md):
  - `PerformanceMetricsSubscriber` calls `memory_reset_peak_usage()` at the start of each tracked main request, so `memoryUsage` (and memory alerts) no longer report the worker-lifetime peak.
  - `PerformanceMetricsSubscriber` / `PerformanceMetricsService` no longer call `error_reporting(0)`; warnings are silenced with `@` (restored by the engine even on exceptions) and the output buffer is closed in `finally`. `onKernelTerminate()` now catches `\Throwable` (not only `\Exception`), so an `\Error` while recording metrics is logged and no longer escapes `kernel.terminate`.
  - `PerformanceMetricsService::recordMetricsSync()` detaches the persisted `RouteDataRecord` and the `RouteData` after flush (and after `AfterMetricsRecordedEvent`), so the shared identity map does not grow per request and the next request reloads a fresh `RouteData`.
  - The profiler collector is reset at the start of each main request and gets the configured `async` flag again (the toolbar no longer shows "sync" after the first reset nor the previous request's record status).
- **`services_twig_component.yaml`** – Fixed invalid YAML indentation of the `Nowo\PerformanceBundle\Form\` block (the file failed to load when `symfony/ux-twig-component` was installed).

### Documentation

- Added `docs/FRANKENPHP-WORKER-AUDIT.md` (scenario A/B audit, W-01…W-05 findings and remediation status).
- Spec `FR-WORKER-001`: metrics path MUST remain correct under FrankenPHP worker with no kernel reboot / no `services_resetter`.

## [3.4.6] - 2026-08-29

### Fixed

- **PerformanceMetricsSubscriber** – Removed duplicate kernel event registration caused by combining `EventSubscriberInterface` / `getSubscribedEvents()` with `#[AsEventListener]` on the same methods. Registration is now **only** via `EventSubscriberInterface` (priorities unchanged: `kernel.request` 31, `kernel.terminate` -1024). Hosts that forced `autoconfigure: false` to avoid double metrics can drop that override after upgrading.

[3.4.6]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.4.6

## [3.4.5] - 2026-08-24

### Changed

- **QA:** add `phpstan-frankenphp` extension (REQ-CS-005).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).

### Notes

- **No API or configuration changes** for integrators unless noted above.

[3.4.5]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.4.5

## [3.4.4] - 2026-08-19

### Security

- **Flex recipe:** `security.access_control` for `^/performance` (REQ-SEC-004).
- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

[3.4.4]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.4.4

## [3.4.3] - 2026-08-18

### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).

[3.4.3]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.4.3

## [3.4.2] - 2026-08-07

### Fixed

- **CI:** refresh `composer.lock` content-hash after FormKit `^2.2` constraint so `composer validate --strict` passes.

[3.4.2]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.4.2

## [3.4.1] - 2026-08-07

### Fixed

- **CI / FormKit:** register `NowoFormKitBundle` + `NowoUiKitBundle` in integration `TestKernel*` so `FormOptionsMerger` is injected into dashboard forms (fixes `$formOptionsMerger` uninitialized errors).
- Seed FormKit profile `performance` with `auto_help` / `auto_placeholder` false (SubmitType rejects `help`); require `nowo-tech/form-kit-bundle` `^2.2`.

### Tests

- `FormKitTestSupport` merger mirrors `auto_help` / `auto_placeholder` false for unit form tests.

[3.4.1]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.4.1

## [3.4.0] - 2026-08-04

### Changed

- **FormKitBundle:** depend on [`nowo-tech/form-kit-bundle`](https://github.com/nowo-tech/FormKitBundle) ^2.0. Admin form types use `FormOptionsTrait` + profile `performance` (`#[FormKitConfig]`). Extension prepends that profile when missing; form types are tagged `form.type` so `FormOptionsMerger` is injected.
- **UiKit:** Dashboard templates use `ui.btn` / `ui.row_actions` macros with `nowo_performance_css_framework` instead of hard-coded Bootstrap button classes.

### Added
- **REQ-TWIG-004:** require `twig/extra-bundle` + `twig/string-extra`; `make check-twig-extra` in `release-check`; demos register `TwigExtraBundle`.
- **Twig-CS-Fixer:** `vincentlanglet/twig-cs-fixer`, `.twig-cs-fixer.php`, `composer twig:lint` / `twig:fix`.

### Changed

- **REQ-UI-001-kit:** Require **[UiKitBundle](https://github.com/nowo-tech/UiKitBundle)** (`nowo-tech/ui-kit-bundle` `^1.4`). Dashboard shell loads `asset('css/nowo-ui.css', 'nowo_ui_kit')` and imports `@NowoUiKitBundle/macros/ui.html.twig` (flashes via `ui.flash`). `PerformanceExtension` seeds `nowo_ui_kit.css_framework` / `icon_set` from `dashboard.css_framework` when the host has not set UiKit. Demo registers UiKit and ships `nowo_ui_kit.yaml`.

---

[3.4.0]: https://github.com/nowo-tech/PerformanceBundle/releases/tag/v3.4.0

## [3.3.1] - 2026-08-03

### Fixed

- **`PerformanceMetricsService`** – Initialize optional `$cacheService` and `$eventDispatcher` to `null` so unit construction without `#[Required]` setters does not fatal on `instanceof` checks (CI PHPUnit on Symfony 7.0 / 7.4).

### Documentation

- [UPGRADING.md](UPGRADING.md), [RELEASE.md](RELEASE.md) updated for 3.3.1.

---

## [3.3.0] - 2026-08-03

### Added

- **REQ-UI-002** – Canonical `security` config: `access_roles` (default `['ROLE_ADMIN']`), optional `access_checker`, `allow_unauthenticated` (default `false`). Ships `PerformanceAccessCheckerInterface`, `ConfigurablePerformanceAccessChecker`, and `AllowAllPerformanceAccessChecker`.
- Compile-time `LogicException` when the dashboard is enabled without `symfony/security-bundle` and `allow_unauthenticated` is `false`.
- **REQ-UI-001** – `dashboard.css_framework` enum (`bootstrap`→`bootstrap5`, `bootstrap4`, `bootstrap5`, `tabler`, `tailwind`, `foundation`, `custom`, `none`). Twig global **`nowo_performance_css_framework`**.
- Unit tests for access checkers, extension security wiring, and additional DBAL/helper coverage.

### Changed

- Dashboard authorization goes through `PerformanceAccessCheckerInterface` (`assertAccess()` in `PerformanceController`) instead of duplicated `isGranted` loops.
- Templates prefer `nowo_performance_css_framework` (with BC fallbacks to `css_framework` / `ui_template` / `template`).
- README coverage note updated (~99% lines).
- Stop tracking generated `coverage-php.txt` (already listed in `.gitignore`).

### Deprecated

- **`dashboard.roles`** – mapped to `security.access_roles` when `access_roles` is not set; prefer `security.access_roles`.
- **`dashboard.template`** – mapped to `css_framework` when absent (`bootstrap`→`bootstrap5`, `tailwind`→`tailwind`); prefer `dashboard.css_framework`.

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md), [SECURITY.md](SECURITY.md), [UPGRADING.md](UPGRADING.md) updated for `security.*` and `css_framework`.

---

## [3.2.2] - 2026-07-30

### Added

- **`dashboard.layout_template`** – Configurable Twig layout for dashboard pages (default `@NowoPerformanceBundle/Performance/layout.html.twig`). Host apps can set e.g. `base.html.twig` so the dashboard uses project chrome. Exposed as Twig global **`nowo_performance_layout_template`** via `PerformanceLayoutExtension` (REQ-UI-001).
- **`Performance/layout.html.twig`** – Demo full HTML shell (Bootstrap/Tailwind CDN); `Performance/base.html.twig` now extends the configured layout and stacks assets with `{{ parent() }}`.
- **`TwigPathsPass`** – Registers `@NowoPerformanceBundle` paths so `templates/bundles/NowoPerformanceBundle/` overrides win over vendor views (`prependPath` + `addPath`; REQ-TWIG-001). Twig paths are no longer prepended from `PerformanceExtension::prepend()`.

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) documents `dashboard.layout_template`.
- [USAGE.md](USAGE.md) adds “Overriding templates (REQ-TWIG-001)” and layout-without-copy guidance.

---

## [3.2.1] - 2026-07-29

### Added

- FrankenPHP Friendly Worker Mode banner (REQ-DOCS-017); `make demo-smoke` / `down-dev` (REQ-TEST-011, REQ-MAKE-007).
- PHPUnit `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` (REQ-SF-005).
- `QueryTrackingCounters` service (instance state + `kernel.reset`) replacing mutable static middleware counters (FrankenPHP-safe; REQ-CS-005 / REQ-CS-006).
- `QueryTrackingMiddlewareResetter` and `DbalSchemaNameHelper` for worker-safe DBAL schema naming.
- **REQ-CS-005:** `phpstan.neon.dist` includes `nowo-tech/phpstan-frankenphp` classic + worker rulesets.
- Demo Symfony 8 entrypoint selects classic vs worker via `FRANKENPHP_MODE` (default `worker`).
- GitHub About homepage + topics; Packagist keyword `php` (REQ-DOCS-018 / REQ-PKG-004).

### Changed

- Demo Symfony 8 image bumped to FrankenPHP **PHP 8.5**.
- Root/demo `.gitignore` ignore `.pnpm-store` (REQ-GITIGNORE-003).

### Fixed

- **DBAL 4.4 deprecation** – Replaced deprecated `AbstractAsset::getName()` / `getQuotedName()` with `getObjectName()` via `DbalSchemaNameHelper` in `TableStatusChecker`, schema commands, and the performance controller (see [doctrine/dbal#7094](https://github.com/doctrine/dbal/pull/7094)).
- Portuguese translations: `review.true`/`review.false` → `review.yes`/`review.no` (YAML boolean keys broke `cache:clear`).
- Demo Makefile: absolute `DOCKER_BIN` so GNU make does not execvp the local `docker/` directory (Permission denied).

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md), [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md), [UPGRADING.md](UPGRADING.md), [RELEASE.md](RELEASE.md) updated for 3.2.1.

---

## [3.2.0] - 2026-07-22

### Changed

- **Translations (REQ-I18N-003)** – Domain and catalog filenames renamed from `nowo_performance` to **`NowoPerformanceBundle`** (`src/Resources/translations/NowoPerformanceBundle.{locale}.yaml`). Twig, forms, and flash messages updated accordingly. See [UPGRADING.md](UPGRADING.md#upgrading-to-320-2026-07-22).
- **Docs** – [USAGE.md](USAGE.md) translation override examples updated; baseline [spec.md](../specs/001-baseline/spec.md) FR-I18N-001 updated.

### Notes

- **Breaking for apps that override translations** – rename override files and replace the domain string `nowo_performance` → `NowoPerformanceBundle` in custom Twig/forms. Config alias, routes, and UX component names are unchanged.

---

## [3.1.4] - 2026-07-16

### Added

- **REQ-GIT-001** – Reject Cursor `Co-authored-by` trailers: `.scripts/check-no-cursor-coauthor.sh`, `.scripts/strip-cursor-coauthor-from-history.sh`, `.githooks/commit-msg`, `.cursor/rules/01-git-commits.mdc`, and [GITHUB_CI.md](GITHUB_CI.md). CI job `git-hygiene` checks commits introduced by each push/PR.
- **Code of Conduct** – [Contributor Covenant](../CODE_OF_CONDUCT.md); linked from README and CONTRIBUTING.
- **Makefile** – `check-no-cursor-coauthor`, `strip-cursor-coauthor-from-history`; `setup-hooks` installs `.githooks`; `release-check` runs the co-author check first.

### Changed

- **PHPUnit** – Exclude `QueryTrackingMiddlewareRegistry` and `PerformanceController` from aggregate coverage (covered via dedicated unit/integration tests; REQ-TEST-003).
- **Docs** – CONTRIBUTING and RELEASE note REQ-GIT-001 hooks and post-tag verification.
- **Demo** – `demo/symfony8` `composer.lock` / `reference.php` refreshed.

---

## [3.1.3] - 2026-07-16

### Removed

- **Demo** – Removed `demo/symfony7`. Local verification demos are limited to **`demo/symfony8`**. The bundle still supports Symfony **7** and **8** at runtime (`composer.json`: `^7.0 || ^8.0`).

### Changed

- **Documentation** – [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md) and [demo/README.md](../demo/README.md) updated for the Symfony 8-only demo layout; `demo/Makefile` targets for symfony7 removed.

---

## [3.1.2] - 2026-07-14

### Fixed

- **Async metrics** – `RecordMetricsMessageHandler` now calls `recordMetricsSync()` instead of `recordMetrics()`, fixing re-dispatch loops and ensuring metrics are persisted when `async: true`.
- **Async access records** – `RecordMetricsMessage` includes optional `statusCode` so HTTP status is stored on `RouteDataRecord` in async mode.

### Changed

- **Performance** – `recordMetricsSync()` persists `RouteData` and `RouteDataRecord` in a single Doctrine `flush()` (was two).
- **Performance** – Statistics cache invalidation bumps a generation counter instead of calling `deleteItem()` on every write ([`PerformanceCacheService`](src/Service/PerformanceCacheService.php)).
- **Performance** – Query IDs in `QueryTrackingMiddleware` use a lightweight counter instead of `md5()` + `uniqid()` per query.
- **Performance** – Removed unused `QueryLogger` wiring and redundant middleware reset from `PerformanceMetricsSubscriber` (reset remains in `QueryTrackingConnectionSubscriber`).
- **Performance** – `QueryTrackingConnectionSubscriber` applies middleware once per request (removed `usleep` retry loop).
- **API** – `PerformanceMetricsService::recordMetricsSync()` is now public (used by the async handler).

### Notes

- **Breaking for positional `RecordMetricsMessage` construction** – optional `statusCode` was inserted after `httpMethod`. Use named arguments or drain the Messenger queue before upgrading if `async: true`. See [UPGRADING.md](UPGRADING.md#upgrading-to-312-2026-07-14).

---

## [3.1.1] - 2026-07-09

### Fixed

- **Tests** – `ConfigurationTest` expects default `dashboard.roles` `['ROLE_ADMIN']` (aligned with v3.1.0 `Configuration`).

### Changed

- **CI** – GitHub Actions bumps: `actions/cache` v6, `actions/checkout` v7, `actions/github-script` v9.

---

## [3.1.0] - 2026-07-09

### Added

- **Spec Kit** – [SPEC-KIT.md](SPEC-KIT.md), baseline specs under `specs/001-baseline/`, `.specify/` scaffolding, and Cursor `speckit-*` skills; [SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md) updated for the three-layer model.

### Changed

- **Dashboard security** – Default `dashboard.roles` is now `['ROLE_ADMIN']` instead of `[]`. Set `roles: []` explicitly to restore unrestricted access (e.g. local dev). See [CONFIGURATION.md](CONFIGURATION.md#dashboardroles).
- **Translations** – YAML structure normalized for `de`, `fr`, `it`, `nl`, and `pt` locales (keys unchanged).
- **Demos** – `composer.lock` and generated `reference.php` refreshed.

---

## [3.0.1] - 2026-07-09

### Fixed

- **CI** – Symfony 8 matrix jobs pin `doctrine/doctrine-bundle` ^3.2; Symfony 7 jobs pin ^2.18; `require-dev` Symfony packages use `--dev`. Excludes Symfony 8 on PHP 8.2/8.3 (Symfony 8 and DoctrineBundle 3.2+ need PHP ^8.4).
- **Tests** – `NowoPerformanceBundleTest` expects both compiler passes registered in `build()` (`NotificationChannelsPass`, then `QueryTrackingMiddlewarePass`).

### Changed

- **Documentation** – COMPATIBILITY and INSTALLATION note that Symfony 8 stacks require PHP 8.4+ in practice (DoctrineBundle 3.2+).

---

## [3.0.0] - 2026-06-30

### Removed

- **Symfony 6.x** and **PHP 8.1** support. The bundle now requires **PHP 8.2+** and **Symfony 7.0+** (`^7.0 || ^8.0`).

### Changed

- **Requirements** – `composer.json` constraints raised to PHP `>=8.2 <8.6` and Symfony `^7.0 || ^8.0` for all bundle dependencies.
- **CI** – Test matrix aligned with the new minimums (PHP 8.2–8.5, Symfony 7.0 / 7.4 / 8.0 / 8.1).
- **Documentation** – README, INSTALLATION, COMPATIBILITY, and ROADMAP updated for PHP 8.2+ and Symfony 7+.

---

## [2.0.21] - 2026-06-30

### Added

- **CI** – Test matrix extended to Symfony **7.4** and **8.1** (with existing PHP/Symfony exclusions).
- **CodeRabbit** – `.coderabbit.yaml` and GitHub Actions workflow for automated PR review.
- **Makefiles** – Standard `update-deps` / `update-deps-all` targets (`REQ-MAKE-008`) for the bundle root and Symfony 7/8 demos (`COMPOSE` / `SERVICE_PHP` wiring).

### Changed

- **README** – Symfony compatibility badge updated (6.0+, 7.4+, 8.0, 8.1+).
- **composer.json** – GitHub `homepage`, `issues`, and `source` URLs corrected to `nowo-tech/PerformanceBundle`.
- **Demos** – Symfony 7 and 8 demo `composer.lock` refreshed; Symfony 7 `config/reference.php` added; Symfony 8 `reference.php` updated.

### Fixed

- **`make update-deps`** – Bundle and demo Makefiles no longer call `exec -T` without `docker-compose` (empty `COMPOSE`), and demo `update-deps-all` no longer loops indefinitely when demo targets were missing.

---

## [2.0.20] - 2026-06-09

### Added

- **Documentation** – [SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md): product spec, user stories, and `REQ-*` traceability for maintainers; linked from root [README](../README.md) and [ENGRAM.md](ENGRAM.md).

### Changed

- **Symfony 8.1** – `NotificationChannelsPass` detects the `TaggedIteratorArgument` constructor by the third parameter name (`needsIndexes`) instead of reflection type, so tagged notification channels stay wired correctly across Symfony versions.

---

## [2.0.19] - 2026-06-09

### Fixed

- **Symfony 8.1** – `NotificationService` no longer triggers the `symfony/dependency-injection` deprecation about `$defaultIndexMethod` / `$defaultPriorityMethod` on tagged iterators. A `NotificationChannelsPass` compiler pass wires notification channels with a version-safe `TaggedIteratorArgument`; custom channels still use the `nowo_performance.notification_channel` tag.

### Added

- **Tests** – Unit tests for `NotificationChannelsPass`.

---

## [2.0.18] - 2026-04-15

### Changed

- **Demos** – Symfony 7 and 8 demo `composer.lock` path-repository metadata for `nowo-tech/performance-bundle` refreshed after v2.0.17. Symfony 8 demo `config/reference.php` (auto-generated) updated for PHP-CS-Fixer (`declare(strict_types=1);`).

---

## [2.0.17] - 2026-04-13

### Fixed

- **Composer** – The `test` script invoked PHPUnit with `color=always`, which PHPUnit treats as a test file path and fails (e.g. in CI). It now uses `--colors=always`, matching `test-coverage`.

---

## [2.0.16] - 2026-04-13

### Added

- **GitHub** – Issue templates (bug / feature / support), `CODEOWNERS`, pull request template, repository `SECURITY.md`, and `sync-releases.yml` workflow to backfill GitHub Releases from tags.
- **Tooling** – `.scripts/` helpers for PHP coverage summary and translation YAML validation (used by `Makefile` / Composer scripts where applicable); Cursor rules and related contributor metadata (optional for end users).

### Changed

- **Composer** – `symfony/ux-twig-component` is no longer a hard `require`. The dashboard already falls back to Twig `include` partials when UX Twig Component is absent; add `composer require symfony/ux-twig-component` only if you want `{{ component('nowo_performance.*') }}` mode. The package stays in `require-dev` for this repository’s tests. Twig component classes are registered via `services_twig_component.yaml`, loaded only when `ComponentInterface` exists.
- **CI** (`.github/workflows/ci.yml`) – Stricter alignment of Symfony and related packages per matrix job and full-tree `composer update` where needed for PHP 8.1–8.5 and Symfony 6.4 / 7 / 8; integration tests adjusted (Doctrine native lazy objects on PHP 8.4+, `WebTestCase` kernel shutdown).
- **Docker** – Root and demo `docker-compose` MySQL services no longer publish host ports; use the Docker network (e.g. service hostname `mysql`) or `docker compose exec`.
- **Demos** – `.env.example` / `.env.test`, grouped demo `.gitignore` (including archives), `make up` copies `.env.example` to `.env` when missing and prints `Demo started at: <DEFAULT_URI>`.
- **Documentation** – [INSTALLATION](INSTALLATION.md): optional UX Twig Component and requirements; [CONFIGURATION](CONFIGURATION.md) / [USAGE](USAGE.md): doc index links and translation override notes; removed duplicate `docs/README.md` in favour of the root [README](../README.md).

### Fixed

- **Composer** – Resolves install/update failures when another project dependency conflicts with a mandatory `symfony/ux-twig-component` constraint; the bundle no longer forces that package on consumers.

---

## [2.0.15] - 2026-03-24

### Added

- **Composer** – `test-coverage-90` script: runs `test-coverage` then `scripts/check-coverage.php` with `--min-percent=90` (statements and elements). Intended for maintainers; **CI** still enforces **80%** (see `.github/workflows/ci.yml`).
- **Makefile** – `make test-coverage-90` runs the same check inside Docker.

### Changed

- **PHPUnit coverage** – `CreateTableCommand.php` and `CreateRecordsTableCommand.php` are **excluded** from the Clover/HTML coverage source again. Their DBAL/SQL surface inflates the denominator and makes a 90% aggregate impractical; they remain exercised by **unit** (`CreateTableCommandTest`, `CreateRecordsTableCommandTest`) and **integration** CLI tests.
- **Tests** – Dashboard integration follows redirects before asserting status; chart data API coverage; `PerformanceMetricsService` integration cases (route params on access records, duplicate `requestId`, `saveAccessRecords` disabled); unit tests for `recordMetricsSync` with closed `EntityManager`, subscriber environment fallback without `Kernel`, and extra schema-command failure paths (metadata/`SchemaTool` errors, force + drop). Test docblocks and related comments use **English** only.
- **Refactoring** – Type hints and assignments aligned for consistency across bundle and tests.
- **SetRouteMetricsCommand** – Inline help describes actual behaviour (no “worse than before” gate).

### Documentation

- **README** – Entity section for **v2** (`RouteData` vs `RouteDataRecord`); removed obsolete metric columns on `RouteData`; “How it works” and FrankenPHP note aligned with demos (`Caddyfile.dev` vs worker). Manual metrics section clarifies the recording pipeline.
- **COMMANDS.md** (`nowo:performance:set-route`) – Removed incorrect “only update if worse” behaviour; documented v2 recording path.
- **DEMO-FRANKENPHP.md** – `bundles.php` example aligned with Symfony 8 demo (Doctrine, Web Profiler, UX Twig Component, UX Icons, Twig Inspector).
- **NOTIFICATIONS.md** – Example listener uses `AfterMetricsRecordedEvent::getRequestTime()` (v2) instead of removed `RouteData::getRequestTime()`.

---

## [2.0.14] - 2026-03-09

### Added
- **Tests** – Unit tests for `CreateTableCommand` and `CreateRecordsTableCommand` (execute with `--update` when table does not exist in schema manager; failure path shows Doctrine command suggestions). Unit tests for `TableStatusChecker` when registry returns non-Connection (`tableExists`, `getMissingColumns`). Integration test for `RouteDataRecordRepository::findOneByRequestId` when record exists.

### Changed
- **Coverage** – Removed exclusions of `CreateTableCommand` and `CreateRecordsTableCommand` from PHPUnit coverage; all `src/` code is now included in the report.
- **CI** – GitHub Actions now enforces a minimum code coverage of 80% (statements and elements) via `scripts/check-coverage.php --min-percent=80` in both the test job (PHP 8.2) and the coverage job.

---

## [2.0.13] - 2026-03-02

### Added
- **Docker and development** – Dockerfiles updated to PHP 8.2 Alpine; docker-compose streamlined with consistent HTTP ports across demos; Makefile gains commands for rebuilding images, restarting containers, and updating the bundle; PHP-CS-Fixer configuration enhanced with Symfony conventions; RELEASE.md and SECURITY.md added under docs.
- **Demo** – FrankenPHP Caddyfile for Symfony 7 and 8 demos; demo Makefiles and READMEs updated with access URLs and setup instructions.

### Changed
- **CI** – GitHub Actions workflow simplified; testing integrated into main docker-compose setup.
- **Demo** – Removed deprecated `docker-compose.test.yml`; demo docker-compose and Makefiles refactored.

### Removed
- **docker-compose.test.yml** – Deprecated test compose file removed; use main docker-compose for tests.

---

## [2.0.12] - 2026-02-02

### Added
- **BEHAVIOUR_AND_CHANGES.md** – New doc explaining notable behaviours and non-obvious changes (e.g. VarDumper in web vs CLI). Documentation index and root README updated to link to it.

### Changed
- **VarDumper in `NowoPerformanceBundle::boot()`** – In web context the bundle no longer replaces the VarDumper handler; it returns before calling `VarDumper::setHandler()`, so Symfony’s default handler (DumpDataCollector) is used and `dump()` / `dd()` output appears in the Web Debug Toolbar. The fallback handler (writing to stderr via CliDumper) is now registered only when `PHP_SAPI === 'cli'`. See [BEHAVIOUR_AND_CHANGES.md](BEHAVIOUR_AND_CHANGES.md#vardumper-dump--dd-behaviour-in-nowoperformancebundleboot).
- **Documentation** – Docs index and references translated to English (docs/README.md, CONFIGURATION.md, INSTALLATION.md).

### Fixed
- **QueryTrackingMiddleware** – Each query execution now gets a unique `queryId` (using `uniqid`). Previously the same SQL reused the same id, so repeated executions (e.g. N+1) were under-counted in metrics.

---

## [2.0.11] - 2026-02-12

### Added
- **README screenshots** – New dashboard section with three screenshots (Performance Metrics Dashboard, Advanced Performance Statistics, Access Statistics by Hour) and brief descriptions. Images in `docs/images/`.

### Changed
- **Documentation** – Full configuration example in CONFIGURATION.md aligned with `Configuration.php` (all options and defaults). INSTALLATION.md Step 5 verify list now includes `create-records-table` and `check-dependencies`. README: badges fixed, Requirements normalized (Symfony/Doctrine), Commands list and Quick Start (table creation) updated; Documentation section descriptions clarified.
- **Recipe** – Default `ignore_routes` now includes `web_profiler*` to match bundle default.

---

## [2.0.10] - 2026-02-03

### Changed
- **Documentation** – Updated BRANCHING.md and CONTRIBUTING.md.
- **Demo** – Updated `bin/console` in Symfony 7 and Symfony 8 demo projects.

---

## [2.0.9] - 2026-02-02

### Changed
- **Statistics templates** – Normalized end-of-file in `_statistics_bootstrap.html.twig` and `_statistics_tailwind.html.twig` (no newline at EOF) for consistency.

---

## [2.0.8] - 2026-01-28

### Added
- **Access records: Referer and User filters** – The Access Records page now supports filtering by Referer and User. Both use partial match (LIKE). The form includes Referer and User text fields; filters apply to pagination, export (CSV/JSON), and delete-by-filter. `RecordFilters`, `RouteDataRecordRepository`, `DeleteRecordsByFilterRequest`, and export endpoints support the new filters.
- **Routes table: Scroll preservation** – When sorting by column or changing page in the routes table, the browser scrolls to the `#routes-table` anchor so the table stays in view instead of jumping to the top. Sort links and paginator links append the fragment.

### Fixed
- **RouteDataRecordRepository tests** – Fixed `testDeleteByFilterReturnsZeroWhenNoRecordsMatch` (updated call for new `referer` and `user` parameters) and `testGetPaginatedRecordsReturnsStructure` (added missing `addSelect` mock on QueryBuilder).

---

## [2.0.7] - 2026-01-30

### Added
- **Access records: route params and path** – Each `RouteDataRecord` now stores the route parameters (`route_params`, JSON) and the full request path including query string (`route_path`, VARCHAR 2048, e.g. `/user/123?page=2`) for the specific access. The Access Records table shows a **Path** column with a clickable link to the exact URL that was hit, so you can re-access it at any time. When path is not available, params are shown as JSON. CSV and JSON exports include `path` and `params`. Run `php bin/console nowo:performance:create-records-table --update` or `nowo:performance:sync-schema` after updating.

See [UPGRADING](UPGRADING.md#upgrading-to-207-2026-01-30) for migration steps.

---

## [2.0.6] - 2026-01-30

### Added
- **Dedicated cache pool** – The bundle now registers a dedicated filesystem cache pool `nowo_performance.cache` (TTL 1h) via `framework.cache.pools`. `PerformanceCacheService` uses this pool by default instead of `cache.app`, isolating bundle cache from application cache.
- **Cache configuration** – New `nowo_performance.cache.pool` option to override the cache pool (e.g. `cache.app` to share with the application). See [CONFIGURATION](CONFIGURATION.md#cache).
- **TableStatusChecker: cache getMissingColumns** – `getMissingColumns()` results are now cached (filesystem, 5 min TTL) to reduce `information_schema` queries. Uses `CACHE_TTL_SECONDS` constant (300s) shared with `tableExists` and `tableIsComplete`.
- **symfony/yaml** – Added as explicit `require` dependency for `YamlFileLoader` when loading services config.

### Added (tests)
- **TableStatusCheckerTest** – `testGetMissingColumnsReturnsCachedValueWhenCacheHit`, `testTableExistsWhenNoCacheAndDatabaseReturnsTrue`, `testRecordsTableExistsReturnsFalseWhenConnectionFails`, and additional cache/exception path tests.
- **PerformanceExtensionTest** – `testLoadCustomCachePoolConfiguration`.
- **ConfigurationTest** – Default `cache.pool` assertion.
- **RouteDataRecordRepositoryTest** – `testGetStatisticsByHourWithRouteNameAndStatusCodeFilters`.
- **PerformanceAnalysisServiceTest** – Correlations, efficiency with null metrics, traffic distribution with single route.
- **RouteDataWithAggregatesTest** – `getStatusCodes` null, `getStatusCodeCount` for non-existent code, `getStatusCodeRatio` with empty codes.
- **PerformanceMetricsServiceGetRouteDataAndRoutesByEnvTest** – `testSetMessageBusAcceptsNull`, `testSetMessageBusAcceptsObject`.

See [UPGRADING](UPGRADING.md#upgrading-to-206-2026-01-30) for migration steps.

---

## [2.0.5] - 2026-01-28

### Added
- **Access records: logged-in user** – When access records and `track_user` are enabled, each `RouteDataRecord` can store the logged-in user: `user_identifier` (e.g. username, email from `UserInterface::getUserIdentifier()`) and `user_id` (stringified ID from `User::getId()` if present). New columns `user_identifier` (VARCHAR 255, nullable) and `user_id` (VARCHAR 64, nullable) on `routes_data_records`. Config: `nowo_performance.track_user` (default `false`). The Access Records UI shows a User column; CSV and JSON exports include both fields. Run `php bin/console nowo:performance:create-table --update` or `nowo:performance:sync-schema` after updating.
- **Makefile: validate-translations** – New target `make validate-translations` runs the translation YAML validation script inside the PHP container (starts the container if needed).
- **Translation validation: block-aware duplicate detection** – The script `scripts/validate-translations-yaml.php` now treats duplicate keys only when they appear in the same parent block. The same key under different parents (e.g. `statistics.max_queries` and `filters.max_queries`) is no longer reported as a duplicate.

### Fixed
- **Security autowiring (Symfony 7/8)** – Resolved "Cannot autowire service PerformanceMetricsSubscriber: argument $security has type Symfony\Component\Security\Core\Security but this class was not found." The Security dependency is now optional (`?security.helper`); the bundle works when SecurityBundle is not installed or when using Symfony 7+ where the old Security class was removed. User tracking is applied only when `track_user` is true and the security helper is available.

### Added (tests)
- **PerformanceMetricsSubscriberSecurityTest** – Tests for `track_user` with security null, with user (identifier/id), and user without `getId()`.
- **ValidateTranslationsYamlTest** – Nested-block duplicate detection, three-level nesting, comments/blank lines, default dir when no argument, duplicate at root level.
- **RecordMetricsMessageTest** – getUserIdentifier/getUserId getters; **RouteDataRecordTest** – userIdentifier/userId setters and getters.
- **DeleteRecordsByFilterRequestTest**, **StatisticsEnvFilterTest**, **ArrayExtensionTest**, **PerformanceAlertTest**, **RecordFiltersTest**, **NowoPerformanceBundleTest**, **RouteDataWithAggregatesTest**, **Event/Twig component tests** – Additional edge-case and coverage tests.

See [UPGRADING](UPGRADING.md#upgrading-to-205-2026-01-28) for migration steps.

---

## [2.0.4] - 2026-01-28

### Added
- **Access records: HTTP Referer** – When access records are enabled, each `RouteDataRecord` now stores the HTTP `Referer` header (page that linked to the request). New column `referer` (VARCHAR 2048, nullable) on `routes_data_records`. The Access Records UI shows a Referer column (with link); CSV and JSON exports include the referer. Run `php bin/console nowo:performance:create-table --update` or `nowo:performance:sync-schema` after updating.
- **Per-route: disable saving access records** – When access records are enabled, you can now turn off saving access records for individual routes. In the review/config modal for each route (same form as "Mark as reviewed"), a checkbox **"Save access records for this route"** appears. If unchecked, the bundle still updates aggregate metrics (RouteData) for that route but does not create new `RouteDataRecord` rows. Useful for high-traffic or internal routes where you want aggregates but not per-request history. New column `save_access_records` (boolean, default true) on `routes_data`. Run `php bin/console nowo:performance:create-table --update` or `nowo:performance:sync-schema` after updating.

See [UPGRADING](UPGRADING.md#upgrading-to-204-2026-01-28) for migration steps.

---

## [2.0.3] - 2026-01-29

### Fixed
- **Request ID on sub-requests** – Fixed "undefined method getMainRequest of Request" when running on Symfony or HttpFoundation versions where `Request::getMainRequest()` does not exist. The subscriber now uses `RequestStack::getMainRequest()` (or `getMasterRequest()` on older Symfony) to resolve the main request for sharing the request ID with sub-requests. No schema or config changes.

---

## [2.0.2] - 2026-01-29

### Added
- **Request ID deduplication** – When access records are enabled, the bundle assigns a unique `request_id` per HTTP request (shared between main and sub-requests). At most one `RouteDataRecord` is created per logical request, avoiding duplicate entries when multiple `TERMINATE` events fire (e.g. main request + fragment).
- **`routes_data_records`** – New optional column `request_id` (VARCHAR 64, nullable, unique). Existing records keep `request_id = NULL`. Run `php bin/console nowo:performance:sync-schema` or your Doctrine migrations after updating.
- **Translation YAML validation** – Script `scripts/validate-translations-yaml.php` checks translation YAML files for valid syntax and duplicate keys. CI runs it in the test job. Composer: `composer validate-translations`; included in `composer qa`.
- **Collector & diagnose: records table status** – When `enable_access_records` is true, the Web Profiler Performance panel shows **Access Records Table** (exists, complete, missing columns). TableStatusChecker gains `recordsTableExists()`, `recordsTableIsComplete()`, `getRecordsMissingColumns()`, `getRecordsTableName()`, `isAccessRecordsEnabled()`. CLI `nowo:performance:diagnose` includes a **Database Tables** section (main table + records table) with missing columns. Missing `request_id` (or any entity column) is detected and the UI suggests running `sync-schema` or `create-records-table --update`.

### Fixed
- **CreateRecordsTableCommand** – Creating the records table from scratch now sets `AUTO_INCREMENT` on the `id` column for MySQL/MariaDB. `--update` now creates missing **unique constraints** (e.g. `uniq_record_request_id` on `request_id`) and uses the same operation order as the main table (Drop → Add → Update).
- **CreateTableCommand** – `addMissingIndexes()` now uses `getSchemaManager()` for DBAL 2.x compatibility.

See [UPGRADING](UPGRADING.md#upgrading-to-202-2026-01-29) for migration steps.

---

## [2.0.1] - 2026-01-28

### Added
- **Review system: edit existing review** – Routes already marked as reviewed can now be edited. An "Edit review" button (pencil icon) appears for reviewed routes; the same modal opens with the form pre-filled with current values (Queries improved, Time improved). Modal title and submit button label differ when editing ("Edit Review" / "Update Review"). Flash message "Review updated" when saving an existing review. New translation keys in all locales: `routes_table.edit_review`, `review.modal_title_edit`, `review.edit_review`, `flash.review_updated`.

### Fixed
- **Routes table: sort by Memory usage** – Sorting by the Memory usage column now uses the numeric value (int bytes). `PerformanceController::getSortValue()` now includes a `memoryUsage` case so the table orders correctly instead of falling back to request time.
- **Charts: initialization after DOM** – Chart scripts (dashboard Performance Trends, Statistics histograms, Charts component) run inside `DOMContentLoaded` and check for `Chart` and canvas before use. Avoids console errors when Chart.js is loaded in `{% block scripts %}` after the inline script in the content.

### Changed
- **Routes table: Status Codes and Access Count** – Removed the "Total responses" line from the Status Codes column (same value as Access Count). Status Codes and Access Count columns are now adjacent for easier reading.
- **Routes table: View access records** – The link to access records now uses the Symfony UX Icons eye icon (`bi:eye`) instead of the 👁 emoji.

---

## [2.0.0] - 2026-01-28

**Breaking:** Entity normalization. See [V2_MIGRATION.md](V2_MIGRATION.md) and [ENTITY_NORMALIZATION_PLAN.md](ENTITY_NORMALIZATION_PLAN.md).

### Removed (from RouteData)
- `totalQueries`, `requestTime`, `queryTime`, `memoryUsage`, `accessCount`, `statusCodes`, `updatedAt` — metrics move to aggregates from `RouteDataRecord` or a dedicated aggregates layer.

### Added (RouteDataRecord)
- `totalQueries`, `queryTime`, `memoryUsage` per access record.

### Added
- **Export access records (CSV / JSON)** – On the Access Records page, export individual `RouteDataRecord` rows via **Export Records (CSV)** and **Export Records (JSON)**. Uses the same filters as the records view (env, date range, route, status code, query time, memory). Requires `enable_access_records: true`. New routes: `nowo_performance.export_records_csv`, `nowo_performance.export_records_json`.
- **Access Records: query time, memory, filters** – Access Records page shows columns Queries, Query Time, Memory (formatted). Form filters: min/max query time (s), min/max memory (MB). Export and pagination preserve these filter params. RecordFilters and RouteDataRecordRepository support `minQueryTime`, `maxQueryTime`, `minMemoryUsage`, `maxMemoryUsage`.
- **Index dashboard: Status Codes column** – Routes table shows status codes with percentage and total responses per code (descending), only codes that have records. Total responses and error-rate warning when >10% non-2xx.
- **Schema sync command** – `nowo:performance:sync-schema` syncs both `routes_data` and `routes_data_records` with entity metadata (add missing, alter differing, optional drop with `--drop-obsolete`). Primary key `id` is never dropped.
- **Drop obsolete option** – `--drop-obsolete` for `nowo:performance:create-table`, `nowo:performance:create-records-table`, and `nowo:performance:sync-schema`. Drops columns that exist in DB but not in the entity.
- **Create-records-table column updates** – `--update` now also alters existing columns when type, nullable or default differ (previously only added missing columns).
- **Translations** – New/updated keys for status codes, total responses, error rate, query time, memory usage, min/max query time and memory in all supported locales.

### Changed
- RouteData only holds identity (env, name, httpMethod, params) and metadata (createdAt, lastAccessedAt, reviewed, reviewedAt, reviewedBy, queriesImproved, timeImproved). Dashboard, API, notifications and exports use aggregated data or records instead of RouteData getters for metrics.
- **Collector diagnostics when disabled** – Web Profiler Performance panel shows full diagnostics even when tracking is disabled (route, request time, query count, query time, environment, processing mode, table status, dependency status). Subscriber always sets `configured_environments`, `current_environment`, and `route_name` in the collector before any early return.
- RebuildAggregatesCommand updates RouteData `lastAccessedAt` from RouteDataRecord; metrics live in records.

### Fixed
- **Advanced Performance Statistics** – Histograms not rendering: chart script moved to `scripts_extra` block so Chart.js is loaded before use; creation wrapped in IIFE with `typeof Chart !== 'undefined'` check.
- **Access statistics with empty route filter** – When `route=` was present in the URL, the controller passed an empty string and no rows matched. Empty route is now normalized to `null` so statistics are returned for all routes.

### Added (tests)
- Models: `RecordFiltersTest` (query time/memory filters), `DeleteRecordsByFilterRequestTest` (min/max query time and memory), `StatisticsEnvFilterTest`, `ClearPerformanceDataRequestTest`.
- Form types: `StatisticsEnvFilterTypeTest`, `ClearPerformanceDataTypeTest`, `DeleteRecordTypeTest`; extended `RecordFiltersTypeTest` and `DeleteRecordsByFilterTypeTest` for new fields.
- Repository: `RouteDataRecordRepositoryAdvancedTest` – `getPaginatedRecords` and `deleteByFilter` with query time and memory filters; explicit calls with full parameter list.
- DataCollector: `disabledReason`, `configuredEnvironments` / `currentEnvironment`, diagnostics when disabled, table/dependency status, `getProcessingMode`.
- `PerformanceMetricsSubscriberCollectorDiagnosticsTest`, `RouteDataWithAggregatesTest`, `SyncSchemaCommandTest`.

### Documentation
- COMMANDS.md: `--drop-obsolete` and `nowo:performance:sync-schema`.
- INSTALLATION.md: Step 5 lists main commands; note on sync-schema after entity changes.
- UPGRADING.md: "Upgrading to 2.0.0" with migration steps; removed duplicate sections.
- PHPDoc: all bundle PHP docblocks and comments in English (constructors, params, returns, form types, models, commands, examples).

## [1.0.8] - 2026-01-27

### Changed
- **Default environments now include production** - Changed default value for `environments` configuration
  - Default changed from `['dev', 'test']` to `['prod', 'dev', 'test']`
  - Bundle now tracks performance in production by default
  - This is more appropriate for a performance monitoring bundle
  - Existing configurations are not affected (only applies when not explicitly configured)
  - Fixes issue where production environments were not tracked by default

### Fixed
- **Demo environments configuration** - Fixed demo projects to allow `APP_ENV=prod`
  - Updated `docker-compose.yml` in both Symfony 7 and Symfony 8 demos
  - Changed from hardcoded `APP_ENV=dev` to `APP_ENV=${APP_ENV:-dev}`
  - Added `APP_DEBUG=${APP_DEBUG:-0}` for better environment control
  - Updated Makefiles to include `APP_DEBUG=1` in default `.env` creation
  - Removed `when@dev:` condition from `nowo_performance.yaml` in demos
  - Configuration now applies to all environments, not just `dev`

### Added
- **Comprehensive environment configuration tests** - Added extensive test coverage for environment handling
  - 5 tests for `PerformanceExtension` environment defaults and configurations
  - 6 tests for `Configuration` environment defaults and edge cases
  - 4 tests for `PerformanceMetricsSubscriber` environment filtering
  - 3 tests for `PerformanceController` diagnose suggestions
  - All tests verify the new default includes `prod`
  - Tests cover edge cases like empty configs, single environments, and custom environments
  - Updated existing tests to reflect new default values

## [1.0.7] - 2026-01-27

### Added
- **Enhanced debug logging** - Added comprehensive logging throughout the performance tracking flow
  - Detailed logs in `onKernelRequest` showing collector state and configuration
  - Detailed logs in `onKernelTerminate` showing start state, route checks, and environment validation
  - Logs for request time calculation (when tracked and when not)
  - Logs for query metrics calculation (count and time)
  - Logs for memory usage calculation
  - Logs when attempting to save metrics with all details (route, env, method, statusCode, requestTime, queryCount, queryTime, memoryUsage, samplingRate)
  - Logs showing the result of `recordMetrics` (is_new, was_updated)
  - Logs in `recordMetrics` showing start state and async/sync mode
  - Logs in `recordMetricsSync` showing search for existing records, creation of new records, access count increments, shouldUpdate checks, before/after flush, and success/error states
  - All logs include route name and environment for easy filtering
  - Helps diagnose why data might not be saved in production environments

### Added
- **Comprehensive test coverage for debugging** - Added 33 new tests for logging and edge cases
  - 13 tests for `PerformanceMetricsSubscriber` debug logging
  - 12 tests for `PerformanceMetricsService` debug logging
  - 8 tests for edge cases where data might not be saved
  - Tests verify all debug logs are generated correctly
  - Tests cover scenarios like collector disabled between REQUEST and TERMINATE, route name lost, environment changed, null startTime/startMemory, all metrics null, and sampling skips
  - Improves reliability and helps diagnose production issues

## [1.0.6] - 2026-01-27

### Fixed
- **Symfony 7 compatibility** - Fixed compatibility issue with Symfony 7.x commands
  - Moved `help` parameter from `#[AsCommand]` attribute to `configure()` method using `setHelp()`
  - Fixed "Unknown named parameter $help" error in all commands
  - All commands now use `configure()` method for help text (compatible with Symfony 6.x and 7.x)
  - Commands affected: `check-dependencies`, `diagnose`, `create-table`, `create-records-table`, `set-route`

### Added
- **Comprehensive test coverage** - Added 60+ new tests for improved reliability
  - 7 tests for `getChartData()` method covering all metrics (requestTime, queryTime, totalQueries, memoryUsage)
  - 5 tests for `accessStatistics()` method covering disabled states, exceptions, and date ranges
  - 7 tests for subscriber detection in diagnose page
  - 13 tests for `getAvailableEnvironments()` method (already in v1.0.4)
  - 16 tests for export functionality (CSV and JSON) (already in v1.0.4)
  - 12 tests for `buildFiltersFromRequest()` method (already in v1.0.4)
  - Improves code quality and ensures edge cases are properly handled

## [1.0.5] - 2026-01-27

### Fixed
- **Subscriber detection in diagnose** - Fixed issue where diagnose page showed subscriber as not registered even when it was
  - Improved subscriber detection with multiple fallback methods
  - Checks event dispatcher listeners for both REQUEST and TERMINATE events
  - Falls back to container service lookup
  - Final fallback to class existence and interface verification
  - Now correctly detects subscriber registration in all scenarios
  - Added detection method information to diagnostic output

### Added
- **Test coverage for subscriber detection** - Added 7 new tests for diagnose subscriber detection
  - Tests for detection via event dispatcher listeners (REQUEST and TERMINATE)
  - Tests for detection via container service lookup
  - Tests for fallback to class existence check
  - Tests for error handling scenarios
  - Tests for detection method reporting

## [1.0.4] - 2026-01-27

### Fixed
- **Environment filter empty when no data** - Fixed issue where environment filter dropdown was empty when no data was recorded yet
  - `getAvailableEnvironments()` now uses `allowedEnvironments` from configuration as fallback when database is empty
  - Falls back to current environment if `allowedEnvironments` is empty
  - Final fallback to default environments (`['dev', 'test', 'prod']`) if all else fails
  - Ensures filter always has options even when no performance data has been recorded
  - Fixes issue where users couldn't select an environment in the dashboard filter

### Added
- **Comprehensive test coverage** - Added 41 new tests for improved reliability
  - 13 tests for `getAvailableEnvironments()` method covering cache, database, and fallback scenarios
  - 16 tests for export functionality (CSV and JSON) covering disabled states, access control, empty data, and exceptions
  - 12 tests for `buildFiltersFromRequest()` method covering all filter types and edge cases
  - Improves code quality and ensures edge cases are properly handled

## [1.0.3] - 2026-01-27

### Fixed
- **Driver name detection with middleware** - Fixed error when getting driver name from wrapped drivers
  - Fixed "Attempted to call an undefined method named 'getName' of class 'AbstractDriverMiddleware'" error
  - Created `getDriverName()` helper method in `PerformanceController` that handles wrapped drivers
  - Uses reflection to access underlying driver when wrapped with middleware
  - Falls back to platform class name inference when direct methods are unavailable
  - Compatible with all DBAL versions and middleware configurations
  - Fixes error in diagnose command when driver is wrapped with `QueryTrackingMiddleware`

## [1.0.2] - 2026-01-27

### Fixed
- **Modal dependency display** - Fixed modal rendering issues in Bootstrap/Tailwind templates
  - Tailwind modal is now only included when `template == 'tailwind'`
  - Bootstrap modal always available when using Bootstrap
  - Improved Bootstrap vs Tailwind detection in JavaScript
  - Prevents Tailwind modal from appearing broken in Bootstrap projects
  - The correct modal is shown according to the active template
- **Division by zero error** - Fixed `DivisionByZeroError` in `PerformanceAnalysisService::calculateCorrelation()`
  - Improved variance checks before computing `sqrt()`
  - Check for `NaN` and `INF` in the denominator
  - Check of the final correlation result
  - Returns `null` when correlation cannot be computed safely
  - Prevents errors when data has zero variance or constant values
- **Data Collector "Unknown" status** - Fixed issue where "Data Saved to Database" showed "⚠ Unknown"
  - `wasRecordNew()` and `wasRecordUpdated()` now read from the properties first (set in `onKernelTerminate`)
  - `setRecordOperation()` now updates both the properties and the `$this->data` array
  - `wasUpdated` is now always `true` when `accessCount` is incremented (because it updates `last_accessed_at`)
  - The collector now correctly shows "✓ Saved (new record created)" or "✓ Saved (existing record updated)"

### Changed
- **PerformanceMetricsService** - Improved `wasUpdated` logic
  - `wasUpdated` is now always `true` when an existing record is updated
  - This is correct because `incrementAccessCount()` always updates `last_accessed_at`
  - Improves the accuracy of the status shown in the Web Profiler

## [1.0.1] - 2026-01-27

### Added
- **Dependency detection and management** - Extended DependencyChecker to detect optional dependencies
  - Detects Symfony Messenger availability for async metrics recording
  - Detects Symfony Mailer availability for email notifications
  - Detects Symfony HttpClient availability for Slack, Teams, and webhook notifications
  - Provides information about missing dependencies with installation commands
  - Informative modal on the dashboard to show missing dependencies
  - Compatible with Bootstrap and Tailwind CSS templates
- **Automatic routes file creation** - Symfony Flex recipe now creates `config/routes/nowo_performance.yaml`
  - Routes are automatically imported when installing the bundle
  - Uses configured prefix and path from bundle configuration
  - Can be customized or overridden by users
- **Performance optimizations** - Significant reduction in database queries
  - Table status caching: `TableStatusChecker` now caches table existence and completeness (5 minutes TTL)
  - Reduces ~10 `information_schema` queries per request to 0 (cached)
  - Optional ranking queries: New `enable_ranking_queries` configuration option
  - When disabled, eliminates 3 ranking queries per request
  - Overall reduction: ~90-95% fewer queries from the bundle per request
- **Generic cache methods** - Added generic cache methods to PerformanceCacheService
  - `getCachedValue()` - Get any cached value by key
  - `cacheValue()` - Cache any value with custom TTL
  - `invalidateValue()` - Invalidate cached value by key
  - Enables caching of any bundle data, not just statistics

### Changed
- **DataCollector ranking queries** - Made ranking queries optional and configurable
  - New configuration: `nowo_performance.dashboard.enable_ranking_queries` (default: `true`)
  - When disabled, ranking information is not calculated in WebProfiler
  - Reduces database load for applications that don't need ranking information
  - Backward compatible: enabled by default
- **TableStatusChecker** - Now uses cache to avoid expensive `information_schema` queries
  - Caches `tableExists()` results for 5 minutes
  - Caches `tableIsComplete()` results for 5 minutes
  - Automatically uses PerformanceCacheService if available
  - Falls back to direct queries if cache is not available

### Fixed
- **CreateTableCommand DBAL 3.x compatibility** - Fixed error when adding columns with `--update`
  - Added required `'name'` key to column array for `getSQLDeclaration()` in DBAL 3.x
  - Fixes "Undefined array key 'name'" error when running `nowo:performance:create-table --update`
  - Added proper column properties (precision, scale, unsigned, fixed) to column definition
  - Maintains full backward compatibility with DBAL 2.x
- **Doctrine DBAL deprecation warnings** - Fixed deprecation warnings in CreateTableCommand and TableStatusChecker
  - Replaced deprecated `AbstractPlatform::quoteIdentifier()` with helper method compatible with DBAL 2.x and 3.x
  - Replaced deprecated `Column::getName()` with `getColumnName()` helper method
  - Replaced deprecated `AbstractAsset::getName()` (for Index objects) with `getAssetName()` helper method
  - All helper methods use `getQuotedName()` for DBAL 3.x and fallback to `getName()` for DBAL 2.x
  - Eliminates deprecation warnings when running `nowo:performance:create-table --update`
  - Maintains full backward compatibility with DBAL 2.x

## [1.0.0] - 2026-01-27

### Added
- **Sub-request tracking support** - Added `track_sub_requests` configuration option
  - Allows tracking performance metrics for sub-requests (ESI, fragments, includes) in addition to main requests
  - Default: `false` (maintains backward compatibility, only main requests tracked)
  - When enabled, tracks both main requests and sub-requests separately
  - Useful for monitoring ESI performance, fragment rendering, and debugging sub-request bottlenecks
  - Includes request type (main/sub) in logging for better diagnostics

## [0.0.7] - 2026-01-27

### Added
- **Environment information in PerformanceDataCollector** - Added environment configuration and current environment display
  - Shows configured environments (where tracking is enabled) in collector toolbar and panel
  - Displays current system environment with status indicator
  - Visual indicators: green if current environment is enabled, red if disabled
  - Helps diagnose why tracking might be disabled (environment not in allowed list)
  - Information available in both toolbar tooltip and detailed panel view

### Fixed
- **Environment detection in PerformanceMetricsSubscriber** - Improved environment detection robustness
  - Enhanced environment detection to try multiple methods: kernel, request server, `$_SERVER`, `$_ENV`
  - Added debug logging to show which method was used for environment detection
  - More reliable fallback chain to ensure environment is always detected
  - Fixes issues when kernel is not available or `APP_ENV` is not in expected location
- **PerformanceDataCollector visibility** - Collector now always visible in profiler toolbar
  - Collector displays even when disabled to provide status information
  - Shows "Disabled" status when tracking is disabled
  - Displays reason for being disabled (bundle disabled or environment not tracked)
  - Still shows table status even when disabled
  - Fixes issue where collector was completely hidden when disabled

### Changed
- **COMPATIBILITY.md documentation** - Updated with discovered compatibility information
  - Added detailed information about DoctrineBundle 2.17.1 not supporting YAML middleware config
  - Expanded middleware registration section with universal approach explanation
  - Added troubleshooting section with common issues and solutions
  - Updated compatibility matrix with tested versions (2.17.1)
  - Documented reflection-based middleware application approach
  - Added error messages and solutions for YAML configuration issues

## [0.0.6] - 2026-01-27

### Added
- **Tests for QueryTrackingConnectionSubscriber** - Added comprehensive test coverage
  - Tests for `getSubscribedEvents()` method
  - Tests for `onKernelRequest()` with different configurations (enabled/disabled, trackQueries on/off)
  - Tests for error handling when connection is not found
  - Tests for query tracking reset functionality
  - Total: 6 new tests covering all scenarios

## [0.0.5] - 2026-01-27

### Fixed
- **DoctrineBundle middleware configuration** - Removed YAML middleware configuration completely
  - YAML middleware options (`middlewares` and `yamlMiddleware`) are not reliably available across all DoctrineBundle versions
  - Some versions (like 2.17.1) do not support these options, causing "Unrecognized option" errors
  - Changed to use only reflection-based middleware application via `QueryTrackingConnectionSubscriber`
  - This approach works consistently across all DoctrineBundle versions (2.x and 3.x)
  - No YAML configuration required, avoiding compatibility issues
  - Updated documentation to reflect this change
- **QueryTrackingConnectionSubscriber getSubscribedEvents method** - Added required method for EventSubscriberInterface
  - Added `getSubscribedEvents()` method to comply with `EventSubscriberInterface`
  - Events are registered via `#[AsEventListener]` attributes, but method is required by interface
  - Fixes "Class contains 1 abstract method" error

## [0.0.4] - 2026-01-27

### Fixed
- **PerformanceDataCollector Throwable import** - Fixed fatal error when autoloading PerformanceDataCollector
  - Added missing `use Throwable;` import statement
  - Fixes "Class 'Nowo\PerformanceBundle\DataCollector\Throwable' not found" error
  - Resolves ReflectionException during container compilation

## [0.0.3] - 2026-01-27

### Fixed
- **DoctrineBundle middleware configuration** - Fixed compatibility issue with `yamlMiddleware` option
  - Changed from using `yamlMiddleware` to always using `middlewares` for DoctrineBundle 2.x
  - `yamlMiddleware` is not reliably available across all DoctrineBundle 2.x versions
  - `middlewares` is more widely supported and works consistently across all 2.x versions
  - Fixes "Unrecognized option 'yamlMiddleware'" errors when installing the bundle
  - Updated documentation to reflect this change

## [0.0.2] - 2026-01-27

### Added
- **Comprehensive test coverage for CreateTableCommand** - Added 14+ new tests
  - Tests for AUTO_INCREMENT detection and fixing
  - Tests for foreign key handling during AUTO_INCREMENT fixes
  - Tests for column type, length, and default value differences
  - Tests for boolean and numeric default value comparison
  - Tests for nullable differences detection
  - Tests for missing indexes addition
  - Tests for platform compatibility (MySQL vs PostgreSQL)
  - Tests for error handling when restoring foreign keys
  - Total: 20+ tests covering all edge cases

### Changed
- **Symfony Flex recipe** - Added uninstall hook
  - Automatically removes bundle from `config/bundles.php` when uninstalling
  - Automatically removes configuration files (`config/packages/nowo_performance.yaml` and dev-specific config)
  - Proper cleanup when running `composer remove nowo-tech/performance-bundle`

### Fixed
- **AUTO_INCREMENT for id column** - Fixed issue where `id` column was not configured as AUTO_INCREMENT in MySQL/MariaDB
  - Command `nowo:performance:create-table --update` now automatically detects and fixes missing AUTO_INCREMENT
  - Handles foreign key constraints by temporarily dropping and restoring them during column modification
  - Improved regex patterns for adding AUTO_INCREMENT when creating tables via SchemaTool
  - Enhanced column definition generation to include AUTO_INCREMENT for identifier columns
  - Detects autoincrement status from entity metadata (GeneratedValue strategy)
  - Fixes "Field 'id' doesn't have a default value" errors when inserting records
  - Query INFORMATION_SCHEMA correctly using JOIN between KEY_COLUMN_USAGE and REFERENTIAL_CONSTRAINTS
  - Properly restores foreign keys with original UPDATE_RULE and DELETE_RULE after fixing AUTO_INCREMENT

## [0.0.1] - 2026-01-26

### Added
- Initial release
- Automatic route performance tracking via event subscribers
- Database query counting and execution time tracking
- Request execution time measurement
- **HTTP Method tracking** - Track HTTP method (GET, POST, PUT, DELETE, etc.) for each route
  - New field in `RouteData` entity: `httpMethod` (STRING, nullable)
  - Automatically captured from request
  - Displayed in dashboard with color-coded badges
  - Included in CSV and JSON exports
- **Memory usage tracking** - Track peak memory consumption per route
  - New field in `RouteData` entity: `memoryUsage` (BIGINT, nullable)
  - Automatically tracked during request processing
  - Displayed in dashboard and exports
  - Can be set via `nowo:performance:set-route --memory` command
- **Route access frequency tracking** - Track how often routes are accessed
  - New fields: `accessCount` (INTEGER, default: 1) and `lastAccessedAt` (DATETIME_IMMUTABLE, nullable)
  - Automatically incremented on each route access
  - Displayed in dashboard with badge showing access count
  - Can be sorted by access count in dashboard
- **Record management system** - Delete individual records from the dashboard
  - New configuration option: `nowo_performance.dashboard.enable_record_management` (boolean, default: `false`)
  - Delete button for each record in the dashboard
  - CSRF protection for delete operations
  - Role-based access control (uses dashboard roles)
- **Record review system** - Comprehensive review system for performance records
  - New configuration option: `nowo_performance.dashboard.enable_review_system` (boolean, default: `false`)
  - New fields in `RouteData`: `reviewed` (boolean), `reviewedAt` (DATETIME_IMMUTABLE), `queriesImproved` (boolean, nullable), `timeImproved` (boolean, nullable), `reviewedBy` (string, nullable)
  - Review modal in dashboard to mark records as reviewed
  - Visual indicators for review status and improvements
  - Indexes added for `reviewed` and `reviewedAt` fields
- **Data export functionality** - CSV and JSON export capabilities
  - Export buttons in dashboard (CSV and JSON)
  - Exports respect current filters and sorting
  - Includes all fields: route name, HTTP method, environment, metrics, memory usage, access count, review status
  - Proper CSV encoding (UTF-8 with BOM)
  - JSON export with metadata (environment, export date)
- **Performance ranking in Web Profiler** - Ranking information in PerformanceDataCollector
  - Shows access frequency (how many times route was accessed)
  - Shows ranking position by request time (1 = slowest)
  - Shows ranking position by query count (1 = most queries)
  - Displays total routes count for context
  - Visual indicators for slow routes (top 3) and routes needing attention (top 10)
  - Shows database storage status and table existence
  - Indicates whether current route's performance data is being saved to database
  - Displays reason if data is not being saved (disabled, environment not tracked, etc.)
  - Always visible in toolbar, even when disabled, to provide status information
- **New console commands**:
  - `nowo:performance:create-table` - Create the performance metrics database table with all indexes
  - `nowo:performance:diagnose` - Comprehensive diagnostic report of bundle configuration and status
  - `nowo:performance:check-dependencies` - Check status of optional dependencies (e.g., Symfony UX TwigComponent)
- **Database table creation command** - `nowo:performance:create-table` command for easy table setup
  - Checks if table exists before creating
  - `--force` option to drop and recreate table
  - `--update` option to add missing columns
  - `--connection` option to specify Doctrine connection
  - Shows SQL statements being executed
  - Provides alternative migration commands if needed
- **Advanced filtering in dashboard** - Enhanced filtering capabilities
  - Filter by route name pattern (LIKE search)
  - Filter by multiple route names (OR condition)
  - Filter by request time range (min/max)
  - Filter by query count range (min/max)
  - Filter by query time range (min/max)
  - Filter by date range (createdAt)
  - Sort by access count
- **Caching layer** - `PerformanceCacheService` for improved dashboard performance
  - Caches statistics calculations
  - Caches environment lists
  - Automatic cache invalidation on metrics updates
  - Configurable TTL (default: 1 hour)
- **Symfony UX Twig Components support** - Optional integration with Symfony UX TwigComponent
  - Uses Twig Components when `symfony/ux-twig-component` is installed
  - Falls back to traditional Twig includes if not available
  - Better performance and maintainability with components
  - Dependency validation with informative messages
- **Chart.js integration** - Interactive charts in dashboard
  - Performance trends visualization
  - API endpoint `/api/chart-data` for chart data
  - Supports filtering by environment and route
  - Alert when no data is available
- **Role-based access control for performance dashboard** - Restrict access to the performance dashboard
  - New configuration option: `nowo_performance.dashboard.roles` (array, default: `[]`)
  - Access control is enforced in `PerformanceController::index()` method
  - Supports multiple roles with OR logic (user needs at least one role)
- **QueryTrackingMiddleware for DBAL 3.x compatibility** - Custom DBAL middleware to intercept and track database queries
  - Automatic query interception via DBAL middleware
  - Static methods for query count and execution time tracking
  - Multiple fallback strategies for query metrics collection
  - Support for Doctrine DataCollector as fallback
  - Version-aware middleware registration (DoctrineBundle 2.x vs 3.x)
  - `QueryTrackingConnectionSubscriber` for automatic middleware application
- Route data persistence in database
- Environment-specific metrics (dev, test, prod)
- Configurable route ignore list
- Command to manually set/update route metrics (`nowo:performance:set-route`)
- Support for multiple Doctrine connections
- Configurable table name for storing metrics
- Performance dashboard with filtering and sorting capabilities
- WebProfiler integration with PerformanceDataCollector
- Symfony 6.1+, 7.x, and 8.x compatibility
- Comprehensive test coverage (123 tests, 34% code coverage)
- Full documentation
- **Date format configuration** - Customizable date formats for dashboard display
  - New configuration: `nowo_performance.dashboard.date_formats.datetime` (default: 'Y-m-d H:i:s')
  - New configuration: `nowo_performance.dashboard.date_formats.date` (default: 'Y-m-d H:i')
- **Table status checking** - Service to verify database table existence and structure
  - `TableStatusChecker` service
  - Displays warnings in dashboard if table is missing or incomplete
  - Shows table status in Web Profiler collector
- **Symfony Flex recipe** - Automatic bundle configuration via Symfony Flex
  - Recipe for automatic setup
  - Default configuration files
  - Post-install instructions
- **Advanced Performance Statistics page** - Comprehensive statistical analysis page
  - Detailed statistics for all metrics (Request Time, Query Time, Query Count, Memory Usage, Access Count)
  - Statistical measures: Mean, Median, Mode, Standard Deviation
  - Percentiles: P25, P50, P75, P90, P95, P99
  - Distribution histograms with Chart.js for each metric
  - Outlier detection and identification
  - Routes needing attention section (top 95th percentile)
  - Key insights and interpretation guides for each metric
  - Contextual information explaining what each chart shows
  - Visual indicators for optimization targets
  - Metric-specific guidance (e.g., N+1 detection, memory optimization, hot path identification)
- **Symfony Translation component support** - Full internationalization support
  - `symfony/translation` as bundle dependency
  - Complete translation files for English, Spanish, Czech, Turkish, and Polish
  - All UI elements are translatable
  - Translation keys for dashboard, filters, statistics, and messages
  - Demo applications configured with translation support
- **Comprehensive test suite** - Extensive test coverage for all components
  - Unit tests for all commands (CreateTableCommand, CheckDependenciesCommand, DiagnoseCommand, SetRouteMetricsCommand)
  - Unit tests for all forms (PerformanceFiltersType, ReviewRouteDataType)
  - Unit tests for all events (BeforeMetricsRecordedEvent, AfterMetricsRecordedEvent, BeforeRecordDeletedEvent, etc.)
  - Unit tests for Twig components (RoutesTableComponent, FiltersComponent, StatisticsComponent, ChartsComponent)
  - Unit tests for Twig extensions (IconExtension)
  - Unit tests for DBAL components (QueryTrackingMiddlewareRegistry)
  - Unit tests for repository methods (findByRouteAndEnv, markAsReviewed, deleteById, findAllForStatistics, deleteAll)
  - Unit tests for entity methods (markAsReviewed, __toString)
  - Unit tests for sampling functionality in PerformanceMetricsSubscriber
  - Total: 37 test files, 150+ individual tests

### Changed
- **Improved query metrics collection** - Enhanced `getQueryMetrics()` method with multiple strategies:
  1. QueryTrackingMiddleware (primary method)
  2. Doctrine DataCollector from profiler (fallback)
  3. Request attributes (fallback)
  4. Stopwatch (time only, fallback)
- **Command option shortcut fix** - Removed conflicting shortcut `-q` from `queries` option in `nowo:performance:set-route` command
- **Enhanced command help** - All commands now include comprehensive help text
- **Database schema improvements** - Added composite indexes for better query performance
  - Index on `env + name` for route lookups
  - Index on `env + requestTime` for sorting
  - Index on `env + accessCount` for access frequency sorting
  - Index on `createdAt` for date filtering
  - Index on `reviewed` and `reviewedAt` for review system
- **Dashboard UI improvements** - Enhanced dashboard with new features
  - Export buttons (CSV/JSON) in header
  - Clear All Records button with referer redirect
  - Access count and last accessed date columns
  - Review status column with visual indicators
  - Action buttons (delete, review) conditionally displayed
  - Improved advanced filters layout with visual grouping
  - HTTP Method column with color-coded badges (GET, POST, PUT, DELETE, etc.)
  - Table status warnings when database table is missing or incomplete
  - Informative alerts when chart data is not available
  - Enhanced filter organization with grouped sections and icons
- **Repository methods** - Added new methods to `RouteDataRepository`
  - `getRankingByRequestTime()` - Get ranking position by request time (accepts RouteData entity)
  - `getRankingByQueryCount()` - Get ranking position by query count (accepts RouteData entity)
  - `getTotalRoutesCount()` - Get total number of routes in environment
  - `deleteById()` - Delete single record by ID
  - `markAsReviewed()` - Mark record as reviewed with improvement flags
- **Query optimization** - Optimized duplicate queries in repository methods
  - `getRankingByRequestTime()` and `getRankingByQueryCount()` now accept RouteData entity directly
  - Reduced duplicate `findByRouteAndEnv()` calls
  - Improved dashboard performance by reusing fetched data for statistics
- **Test coverage improvements** - Significantly expanded test suite
  - Added tests for all commands, forms, events, and Twig components
  - Improved coverage for repository methods and entity methods
  - Added tests for sampling and auto-refresh functionality
  - Total test count increased from 19 to 37 test files

### Fixed
- **Query tracking compatibility** - Fixed query tracking to work correctly with DBAL 3.x by implementing a custom middleware instead of relying on deprecated `SQLLogger`
- **Connection interface compatibility** - Fixed `QueryTrackingConnection` to properly implement all required methods from `Doctrine\DBAL\Driver\Connection` interface
- **Server version method signature** - Fixed `getServerVersion()` return type to match `ServerVersionProvider` interface requirements
- **Query tracking registration** - Fixed middleware registration to work with both DoctrineBundle 2.x and 3.x
  - Automatic version detection
  - YAML configuration for DoctrineBundle 2.x
  - Reflection-based registration for DoctrineBundle 3.x
  - Enhanced `QueryTrackingMiddlewareRegistry` for robust middleware application
- **Cache service autowiring** - Fixed `PerformanceCacheService` to handle cases where `cache.app` might not be available
- **Data collector query metrics** - Fixed `PerformanceDataCollector` to fetch query metrics directly from `QueryTrackingMiddleware` if not set
- **Access count and last accessed display** - Fixed Twig templates to use getter methods instead of direct property access
- **Table naming** - Fixed table name to use `routes_data` (plural) consistently
  - Added `#[ORM\Table(name: 'routes_data')]` to RouteData entity
  - Updated `TableNameSubscriber` to correctly override table name
  - Fixed index column names to use snake_case
- **Division by zero** - Fixed division by zero error in statistics calculation when all values are identical
- **Bundle alias** - Fixed bundle alias resolution by renaming bundle class to `NowoPerformanceBundle`
- **Route import** - Fixed route import path to use correct bundle alias `@NowoPerformanceBundle`
- **Twig syntax errors** - Fixed duplicate and missing Twig tags in RoutesTable component
- **Deprecation warnings** - Fixed `Doctrine\DBAL\Schema\AbstractAsset::getName` deprecation warning
- **Bundle class duplication** - Removed duplicate `PerformanceBundle.php` file that caused duplicate bundle registration
- **Symfony Flex recipe** - Fixed recipe to create configuration file automatically on installation
- **Entity method missing** - Added missing `markAsReviewed()` method to `RouteData` entity

### Security
- **Dashboard access control** - The performance dashboard supports role-based access control to restrict access to authorized users only
- **CSRF protection** - All form submissions (delete, review, clear) are protected with CSRF tokens
