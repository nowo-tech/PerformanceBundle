# Migration to 2.0.0 — Entity Normalization

Version **2.0.0** of the Performance Bundle introduces **breaking** changes from 1.x: normalization of the data model (RouteData / RouteDataRecord). This document summarizes the breaking changes and the steps to migrate from 1.x to 2.0.0.

---

## Summary of breaking changes

| Area | Change in 2.0.0 |
|------|------------------|
| **RouteData (entity)** | Removed properties: `totalQueries`, `requestTime`, `queryTime`, `memoryUsage`, `accessCount`, `statusCodes`, `updatedAt`. Only identity (env, name, httpMethod, params), timestamps (createdAt, lastAccessedAt) and review (reviewed, reviewedAt, reviewedBy, queriesImproved, timeImproved) remain. |
| **RouteDataRecord (entity)** | Added per record: `totalQueries`, `queryTime`, `memoryUsage`. |
| **Database** | Columns removed from `routes_data`. New columns in `routes_data_records`. |
| **Public API** | RouteData getters that returned metrics (`getRequestTime()`, `getTotalQueries()`, etc.) **are removed**. Metrics are obtained by aggregating over `RouteDataRecord` or from an aggregate layer. |
| **Controller / views** | The dashboard and views that read `route.requestTime`, `route.totalQueries`, etc. must switch to reading from aggregates or records. |
| **RebuildAggregatesCommand** | In 2.0 it no longer fills RouteData fields that do not exist; it may fill an aggregate table or remain only for partial compatibility during migration. |
| **Notifications / thresholds** | Events and notifications that use `routeData->getRequestTime()` etc. must use the new data source (aggregates or records). |

---

## Requirements for 2.0.0

- PHP and Symfony: same as 1.x (see [COMPATIBILITY.md](COMPATIBILITY.md)).
- **Doctrine**: no minimum version change.
- If you use **custom queries** or **repositories** that read removed columns from `routes_data`, they must be adapted to queries over `routes_data_records` or the new aggregate layer.

---

## Migration guide (1.x → 2.0.0)

### 1. Before upgrading

1. **Identify use of metrics on RouteData**
   - Search your project for: `getRequestTime()`, `getTotalQueries()`, `getQueryTime()`, `getMemoryUsage()`, `getAccessCount()`, `getStatusCodes()`, `getUpdatedAt()` on RouteData entities (or equivalent tables/views).
   - Note: controllers, services, commands, Twig templates, notifications, exports (CSV/JSON).

2. **Data backup**
   - Back up `routes_data` and `routes_data_records` if you need to preserve history or populate aggregates from 1.x.

3. **Review the normalization plan**
   - Read [ENTITY_NORMALIZATION_PLAN.md](ENTITY_NORMALIZATION_PLAN.md) for the target model and phases.

### 2. Upgrade the bundle

```bash
composer require nowo-tech/performance-bundle:^2.0
```

(2.0.0 is available as of 2026-01-28.)

### 3. Database schema

After upgrading the bundle entities (which in 2.0 no longer have metric columns on RouteData and do have the new ones on RouteDataRecord):

```bash
# Option A: sync schema with the bundle command (add/alter/optionally drop)
php bin/console nowo:performance:sync-schema --drop-obsolete
```

- **Important:** `--drop-obsolete` removes columns that no longer exist on the entities (e.g. on `routes_data`). Ensure you have migrated logic and/or data first.

Alternative with Doctrine:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

(if you use your own migrations that reflect the new mapping).

### 4. Adapt application code

- **Where metrics were read from RouteData:** in 2.0 use the new API (e.g. repository/service that aggregates over `RouteDataRecord` by `route_data_id`, or reads from an aggregate table/cache if the bundle provides one).
- **Templates:** replace `route.requestTime`, `route.totalQueries`, etc. with variables the controller fills from aggregates or the new service.
- **Notifications / events:** switch to thresholds and data based on aggregates or records, as exposed by the bundle in 2.0.
- **Exports (CSV/JSON):** obtain metrics from the same aggregate/records layer used by the dashboard.

### 5. Verify after migration

- Dashboard listings (rankings, filters).
- Route detail (if present in 2.0).
- Notifications and thresholds.
- Commands that use RouteData or RouteDataRecord.
- Your own tests that mock or assert on RouteData/RouteDataRecord.

---

## Expected timeline

- **1.x:** Maintained; compatibility fixes and improvements only. Normalization is not applied in 1.x.
- **2.0.0** (2026-01-28): Includes the normalization described in [ENTITY_NORMALIZATION_PLAN.md](ENTITY_NORMALIZATION_PLAN.md). Requires code and schema migration as per this document.

---

## Related documentation

- [ENTITY_NORMALIZATION_PLAN.md](ENTITY_NORMALIZATION_PLAN.md) — Target model and technical phases.
- [COMMANDS.md](COMMANDS.md) — `nowo:performance:sync-schema`, `create-table`, `create-records-table` (including `--drop-obsolete`).
- [UPGRADING.md](UPGRADING.md) — "Upgrading to 2.0.0" section with summary and links.
