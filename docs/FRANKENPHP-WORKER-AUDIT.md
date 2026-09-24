# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/performance-bundle` (`symfony-bundle`) |
| Audited revision | `v3.4.7` (remediation of findings from `v3.4.6` / `77f2ae2`) |
| Audit date | 2026-09-24 |
| Method | Manual review of the runtime code under `src/` (kernel/Doctrine subscribers, DBAL middleware, data collector, services, notification channels, Twig extensions/components, controller, message handler, DI extension, compiler passes, `Resources/config/*.yaml`). Commands were skimmed (CLI only). |
| **Verdict** | ✅ **Viable under scenario B** (after remediation in 3.4.7) — memory peak is reset per main request, no process-wide `error_reporting`/output-buffer mutation can leak, persisted entities are detached after each write, and the profiler collector is reset per main request. Residual: notifications are still sent synchronously in `kernel.terminate` (W-05, accepted) |
| Remediation (2026-09-24) | W-01…W-04 resolved in `PerformanceMetricsSubscriber` and `PerformanceMetricsService`; W-05 accepted; invalid YAML in `services_twig_component.yaml` fixed. Regression tests: `tests/Unit/EventSubscriber/PerformanceMetricsSubscriberWorkerModeTest.php`, `tests/Unit/Service/PerformanceMetricsServiceWorkerModeTest.php` |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `PerformanceMetricsSubscriber` overwrites its per-request fields in `onKernelRequest`; it now also resets `PerformanceDataCollector` (and re-applies `async`) at the start of every main request |
| Static properties / `static` locals | ✅ | No static properties; `LogHelper` and `QueryTrackingMiddlewareRegistry` only have stateless static methods |
| `ResetInterface` / `kernel.reset` coverage | ✅ | `QueryTrackingCounters` and `QueryTrackingMiddlewareResetter` are tagged and also reset per request by `QueryTrackingConnectionSubscriber`; the collector is reset through `DataCollectorInterface` (A) and by the subscriber per main request (B); the bundle's own entities are detached after each write |
| Request / user / locale captured in services | ✅ | Request, user and route are read per event; nothing is captured in a constructor |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | No `error_reporting()` mutation any more (warnings are silenced with `@`, restored by the engine even on exceptions); `$_SERVER['APP_ENV']` / `getenv()` are only a fallback when no kernel is injected |
| Doctrine / EntityManager | ✅ | Closed EntityManager is handled; `RouteData` / `RouteDataRecord` are detached after each `recordMetricsSync()`. Clearing the application's identity map between requests remains the application's responsibility under B |
| Output, headers, `exit`, shutdown functions | ✅ | The `ob_start()` buffer of `kernel.terminate` is closed in `finally`; CSV export writes to `php://output` inside `StreamedResponse` (correct); no `exit`/`header()` |
| Resources (files, sockets, cURL) held open | ✅ | CSV export handles are closed with `fclose()`; no persistent handles |
| Memory growth across requests | ✅ | Bundle entities are detached (W-03); the memory metric uses a peak reset per main request (W-01) |
| Blocking I/O and timeouts | ⚠️ Low (accepted) | Webhooks have an explicit `timeout`/`max_duration`; email depends on the mailer transport; both run in `kernel.terminate` |
| Third-party static state | ✅ | `VarDumper::setHandler()` is only called in CLI (`PHP_SAPI === 'cli'`), never in the HTTP worker |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist` |

Worker demo: `demo/symfony8/docker/frankenphp/Caddyfile` declares a `worker { file /app/public/index.php }` block, and `demo/symfony8/docker-compose.yml` defaults to `FRANKENPHP_MODE=worker`.

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `EventSubscriber\PerformanceMetricsSubscriber` | yes | `startTime`, `startMemory`, `routeName`, `routeParams`, `requestId` (overwritten per request) | ✅ | ✅ (W-01, W-02 resolved) |
| `DataCollector\PerformanceDataCollector` | yes | `enabled`, `async`, `routeName`, timings, record status, `data` | ✅ (W-04 resolved) | ✅ (reset by the subscriber per main request) |
| `EventSubscriber\QueryTrackingConnectionSubscriber` | yes | `trackedConnections` (one key per configured connection, bounded) | ✅ | ✅ (resets counters on every request) |
| `DBAL\QueryTrackingCounters` | yes (`kernel.reset`) | counters + `queryStartTimes` | ✅ | ✅ (reset by the subscriber above when tracking is on) |
| `DBAL\QueryTrackingMiddlewareResetter` | yes (`kernel.reset`) | none | ✅ | ✅ |
| `DBAL\QueryTrackingMiddleware` / `QueryTrackingConnection` | yes / per connection | none (readonly counters reference) | ✅ | ✅ |
| `Service\PerformanceMetricsService` | yes (public) | `entityManager`, `repository`, `recordRepository` (re-resolved when the EM is closed) | ✅ | ✅ (W-03 resolved) |
| `Service\PerformanceCacheService` | yes (public) | none (filesystem pool, generation keys) | ✅ | ✅ |
| `Service\TableStatusChecker` | yes (public) | `cacheService` (setter injection, set once) | ✅ | ✅ |
| `Service\DependencyChecker`, `Service\PerformanceAnalysisService` | yes | none | ✅ | ✅ |
| `Service\NotificationService` + `EmailNotificationChannel` + 3 × `WebhookNotificationChannel` | yes | none (readonly config) | ⚠️ W-05 | ⚠️ W-05 |
| `EventSubscriber\PerformanceAlertSubscriber` | yes | none (readonly thresholds) | ✅ (W-01 resolved) | ✅ |
| `EventSubscriber\TableNameSubscriber`, `RouteDataRecordTableNameSubscriber` | yes | none (only touch class metadata once) | ✅ | ✅ |
| `Repository\RouteDataRepository`, `RouteDataRecordRepository` | yes | none (plain `ServiceEntityRepository`, no caches) | ✅ | ✅ |
| `Controller\PerformanceController` | yes | none (all constructor args `readonly`) | ✅ | ✅ (dashboard reads load entities into the application's EM; clearing it between requests is the application's responsibility under B) |
| `MessageHandler\RecordMetricsMessageHandler`, `MessageBus\MessengerBusAdapter` | yes | none | ✅ | ✅ (same detaching write path) |
| `Security\ConfigurablePerformanceAccessChecker`, `AllowAllPerformanceAccessChecker` | yes | none (`final readonly`) | ✅ | ✅ |
| 3 Twig extensions (`IconExtension`, `ArrayExtension`, `PerformanceLayoutExtension`) | yes | none (globals come from readonly config) | ✅ | ✅ |
| 4 Twig components (`Twig\Component\*`) | no (TwigComponentPass sets `shared: false`) | public props per render | ✅ | ✅ |
| 8 form types (`Form\*`) | yes | stateless | ✅ | ✅ |
| 8 console commands | CLI only | not used by the HTTP worker | N/A | N/A |

Entities (`RouteData`, `RouteDataRecord`), models, messages, events and `PerformanceAlert` are created per call and never stored in a shared service.

## Findings

### W-01 — Memory metric uses the worker-lifetime peak (Medium)

- **Where:** `src/EventSubscriber/PerformanceMetricsSubscriber.php:255` (`$this->startMemory = memory_get_usage(true)`) and `:395-401` (`memory_get_peak_usage(true) - $this->startMemory`). There is no call to `memory_reset_peak_usage()` anywhere in `src/` (the bundle requires PHP >= 8.2, so the function is available).
- **Worker impact:** in a worker the peak is the highest value since the worker started, not since the request started. After one heavy request, every following request on that worker records roughly the same large `memoryUsage` in `RouteDataRecord` (and in the async message). `PerformanceAlertSubscriber::onAfterMetricsRecorded()` then compares this value with `memory_usage.warning` / `critical`, so memory alerts can fire on every request and flood email / Slack / Teams / webhook channels. It happens in scenario A and B, because the peak is process-global and `services_resetter` does not touch it. No data of one user is exposed to another; only the metric is wrong.
- **Recommendation:** call `memory_reset_peak_usage()` at the start of `onKernelRequest()` for main requests (before reading `startMemory`), or record `memory_get_usage()` deltas instead of peaks. Until fixed, disable memory alerts or ignore memory metrics collected in worker mode.
- **Status:** Resolved — `PerformanceMetricsSubscriber::onKernelRequest()` calls `memory_reset_peak_usage()` for tracked main requests right before reading `startMemory` (never on sub-requests). Test: `PerformanceMetricsSubscriberWorkerModeTest::testMemoryPeakOfPreviousRequestDoesNotLeakIntoNextRequest`.

### W-02 — `error_reporting(0)` and an output buffer can leak to later requests on `\Error` (Medium)

- **Where:** `src/EventSubscriber/PerformanceMetricsSubscriber.php:436` (`error_reporting(0)`), `:440-445` (`ob_start()`), restored at `:530-535` and in `catch (Exception $e)` at `:536-545`; the `finally` block at `:574-587` does not restore either. Same pattern in `src/Service/PerformanceMetricsService.php:403-405` with `catch (Exception $e)` at `:435`.
- **Worker impact:** only `\Exception` is caught. A `\TypeError` or other `\Error` raised inside `recordMetrics()` (for example by an application listener of `BeforeMetricsRecordedEvent` / `AfterMetricsRecordedEvent`, a notification channel, or Doctrine) leaves `error_reporting` at `0` and leaves the extra output buffer open. Both are process-global, so if the worker keeps running every later request on that thread runs with all PHP errors silenced. This affects scenario A and B. If the runtime lets the error crash the worker, FrankenPHP restarts it and the state is cleared, but at the cost of a worker restart.
- **Recommendation:** move the `error_reporting($errorReporting)` restore and the `ob_end_clean()` into the `finally` block (or catch `\Throwable`) in both places. Consider dropping the `error_reporting(0)` / `ob_start()` wrapping: at `kernel.terminate` the response has already been sent.
- **Status:** Resolved — `error_reporting(0)` was removed from both classes (it is also flagged by `frankenphp.worker.noErrorReportingMutation`); the `recordMetrics()` call and the `flush()` use the `@` operator, whose level the engine restores even when an exception unwinds. The bundle's output buffer is closed in `finally`, and `onKernelTerminate()` catches `\Throwable` (P9: nothing escapes `kernel.terminate`). A log line that relied on the old silencing (undefined `is_new`/`was_updated` keys) now uses `??`. Test: `testErrorWhileRecordingRestoresErrorReportingAndOutputBuffer`, `PerformanceMetricsServiceWorkerModeTest::testErrorDuringFlushRestoresErrorReporting`.

### W-03 — Doctrine identity map is never cleared by the bundle (Medium, scenario B only)

- **Where:** `src/Service/PerformanceMetricsService.php:307` (`findByRouteAndEnv()`), `:324` and `:393` (`persist()` of a `RouteData` and one `RouteDataRecord` per tracked request), `:404` (`flush()`); dashboard reads in `src/Controller/PerformanceController.php` go through the same shared EntityManager.
- **Worker impact:** the bundle writes through the application's EntityManager (connection `nowo_performance.connection`, default `default`) and never calls `clear()`/`detach()`. Under A, DoctrineBundle's `kernel.reset` on `doctrine` clears the EntityManager, so this is fine. Under B, every `RouteDataRecord` created stays managed (unbounded memory, and each `flush()` computes change sets over a growing unit of work), and `findByRouteAndEnv()` returns the already-managed `RouteData` without refreshing it, so a change made from the dashboard in another worker (for example `saveAccessRecords` or review fields) is not seen by this worker. Closed EntityManagers are handled correctly: `resetEntityManager()` (`:643-665`) calls `ManagerRegistry::resetManager()` and re-resolves the repositories.
- **Recommendation:** keep `kernel.reset` enabled (scenario A). If the bundle must survive scenario B, detach the persisted `RouteDataRecord` after flush, or use a dedicated EntityManager / DBAL insert for metrics and clear it after each write.
- **Status:** Resolved — `PerformanceMetricsService::recordMetricsSync()` detaches the new `RouteDataRecord` and the `RouteData` after a successful flush (after `AfterMetricsRecordedEvent`, so listeners still get a managed entity). The next request reloads `RouteData` from the database, so dashboard changes made in another worker (e.g. `saveAccessRecords`) are seen. The bundle never calls `clear()` on the application's EntityManager: clearing the identity map between requests (dashboard reads, application entities) remains the application's responsibility under scenario B. Test: `PerformanceMetricsServiceWorkerModeTest::testPersistedEntitiesAreDetachedAfterEachRequest`.

### W-04 — Profiler collector loses `async` after the first reset and can show a stale record status (Low)

- **Where:** `src/EventSubscriber/PerformanceMetricsSubscriber.php:119-120` sets `setEnabled()` / `setAsync()` only in the constructor; `src/DataCollector/PerformanceDataCollector.php:415-431` (`reset()`) sets `async = false`. `recordWasNew` / `recordWasUpdated` are read back in `collect()` (`:405-406`) and in the `finally` of `onKernelTerminate()`.
- **Worker impact:** the collector implements `ResetInterface` (through `DataCollectorInterface`) and is reset under A, but the subscriber is not rebuilt, so from the second request on the toolbar reports "sync" even when `async: true`. Under B, the record status set in the previous request's `kernel.terminate` is shown by the next request's profiler. Only the profiler display is affected; metric recording itself uses `PerformanceMetricsService::$async`, which is `readonly`.
- **Recommendation:** set `async` (and the configured environments) from the subscriber in every `onKernelRequest()`, or inject the flag into the collector directly. Clear `recordWasNew` / `recordWasUpdated` at the start of each tracked request.
- **Status:** Resolved — `PerformanceMetricsSubscriber::onKernelRequest()` calls `PerformanceDataCollector::reset()` and `setAsync()` at the start of every main request (sub-requests keep the main request's state). Tests: `testCollectorStateIsResetAtEachMainRequestWithoutKernelReset`, `testSubRequestDoesNotResetCollectorOfMainRequest`.

### W-05 — Notifications block the worker thread in `kernel.terminate` (Low)

- **Where:** `src/Notification/Channel/WebhookNotificationChannel.php:56-63` (explicit `timeout` and `max_duration`, configurable via `nowo_performance.notifications.http_timeout`, default 10 s); `src/Notification/Channel/EmailNotificationChannel.php:70` (`$this->mailer->send()` with no bundle-level timeout).
- **Worker impact:** alerts are sent synchronously after the response, while the worker thread is still busy. A slow SMTP server or webhook keeps that thread unavailable for other requests (up to the HTTP timeout for webhooks, up to the transport timeout for mail). No state leaks. Combined with W-01 this can happen on every request.
- **Recommendation:** route mail through Messenger (`async` transport), keep `http_timeout` low (1-3 s), and use FrankenPHP `max_wait_time` to cap queued requests.
- **Status:** Accepted — webhooks already have a configurable timeout and mail timeouts belong to the mailer transport / Messenger routing of the application. With W-01 fixed, alerts no longer fire on every request.

### Info

- `PerformanceMetricsSubscriber` has no `reset()`, but every field it reads in `onKernelTerminate()` is rewritten in `onKernelRequest()` for tracked requests, and terminate skips the request when `_route` is missing (`:323-340`). A request that fails before routing therefore cannot reuse the previous request's route or timings.
- `QueryTrackingConnectionSubscriber::onKernelRequest()` (`src/EventSubscriber/QueryTrackingConnectionSubscriber.php:77-88`) resets the shared counters at the start of every request, so query counts are correct even under B. The driver is wrapped once per worker via reflection and stays wrapped.
- `LogHelper` writes through `error_log()` and `nowo_performance.enable_logging` defaults to `true`, which produces several log lines per request. This is noise, not state; set `enable_logging: false` in production.
- Outside the worker scope: in `src/Resources/config/services_twig_component.yaml:13-16` the `Nowo\PerformanceBundle\Form\` block is indented with 2 spaces under a `services:` map that uses 4 spaces. This is invalid YAML (confirmed with a YAML parser: "expected <block end>, but found '<block mapping start>'" at line 13), so loading this file fails when `symfony/ux-twig-component` is installed. **Status:** Resolved — block re-indented to 4 spaces.

## Usage recommendations in worker mode

- Scenario B is supported by the bundle itself. Keeping `kernel.reset` / `services_resetter` enabled (scenario A) is still recommended for the application's own services and for clearing the application's EntityManager.
- Prefer `async: true` with Messenger so the database write and notifications leave the HTTP worker; the handler runs in `messenger:consume`, which should also use `--limit` / `--memory-limit`.
- Set `enable_logging: false` in production and a low `notifications.http_timeout`.
- Do not extend `PerformanceMetricsSubscriber`, `PerformanceMetricsService` or the notification channels with properties that store request data unless the class also implements `ResetInterface`.
- Errors thrown by application listeners of the bundle events are logged and swallowed in `kernel.terminate` (W-02); setting FrankenPHP `max_requests` (or `FRANKENPHP_LOOP_MAX` with the Symfony runtime) to recycle workers periodically is still good practice.

## Re-audit triggers

Re-run this audit when a change adds or modifies: properties on `PerformanceMetricsSubscriber`, `PerformanceDataCollector` or `PerformanceMetricsService`; the `error_reporting` / `ob_*` handling in `kernel.terminate`; memory measurement; any in-memory cache in repositories or services; a new notification channel; or any use of `$_SERVER` / `$_ENV` / `getenv()` at runtime.
