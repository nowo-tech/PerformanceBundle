# PHP-FIG PSR evaluation (REQ-CS-007)

Package: `nowo-tech/performance-bundle` (`symfony-bundle`)

This document records which [PHP-FIG PSRs](https://www.php-fig.org/psr/) apply to this package.
Only contracts that add clear interoperability or maintainability value are **Adopted**.
Others are **N/A** (or already covered by Symfony) so the decision stays auditable.

## Table of contents

- [Baseline (always)](#baseline-always)
- [Interface / contract PSRs](#interface--contract-psrs)
- [Summary](#summary)

## Baseline (always)

| PSR | Decision | How |
| --- | -------- | --- |
| PSR-12 (coding style) | **Adopted** | `@PSR12` in `.php-cs-fixer.dist.php` (Nowo REQ-CS-001). |
| PSR-4 (autoloading) | **Adopted** | `composer.json` `autoload` / `autoload-dev` PSR-4 map for package sources and tests. |

## Interface / contract PSRs

| PSR | Decision | Notes |
| --- | -------- | ----- |
| PSR-3 Logger | **Adopted** | `PerformanceMetricsSubscriber`, `PerformanceMetricsService`, `NotificationService` and the email / webhook notification channels take an optional `Psr\Log\LoggerInterface` (autowired `logger`); diagnostics go to `debug` (gated by `enable_logging`), delivery failures to `warning`. The static `LogHelper` (`error_log`) is deprecated. `psr/log` in `require`. |
| PSR-6 Caching | **Adopted** | Uses `Psr\Cache\` contracts where item metadata/tags matter. |
| PSR-7 / PSR-17 HTTP messages | **N/A** | Symfony HttpFoundation (or no HTTP messages API) is the correct surface; a PSR-7 bridge would not help consumers. |
| PSR-18 HTTP client | **N/A** | Outbound HTTP uses Symfony `HttpClientInterface` (or a package transport) as the supported SPI instead of PSR-18. |
| PSR-11 Container | **Adopted (narrow)** | Uses `Psr\Container\ContainerInterface` only as a **closed plugin/strategy registry** (documented service ids), not as a general service locator. |
| PSR-14 Event dispatcher | **Already satisfied via Symfony** | Uses Symfony `EventDispatcherInterface` / subscribers (PSR-14 compatible). No separate FIG dependency required. |
| PSR-15 HTTP middleware | **N/A** | Uses Symfony kernel listeners/subscribers (or has no HTTP middleware pipeline). |
| PSR-20 Clock | **Deferred** | Re-evaluated 2026-10-21: ~30 call sites (entities, retention purge, statistics windows, repositories) build `DateTimeImmutable` from system time. Adopting a clock means threading `ClockInterface` through repositories and entities — planned for a minor with UPGRADING notes; until then tests pass explicit dates. |

## Summary

- **Adopted beyond baseline:** PSR-6 Caching, PSR-11 Container
- **Rule:** do not add `psr/*` Composer dependencies without matching type-hints and DI wiring.
- **Re-evaluate** when the package gains logging, HTTP, cache, clock, or event SPIs.

---

_REQ-CS-007 evaluation date: 2026-10-21 (PSR-3 adopted, PSR-20 re-evaluated)._
