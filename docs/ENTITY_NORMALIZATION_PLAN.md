# Entity Normalization Plan: RouteData and RouteDataRecord

> **v2.0.0 — Breaking changes.** This plan removes fields from the `RouteData` entity and changes the source of metrics (aggregates from `RouteDataRecord`). The 1.x branch remains stable; normalization is delivered in **2.0.0**. See [V2_MIGRATION.md](V2_MIGRATION.md) for breaking changes and migration guide.

## Objective

- **routes_data**: only information that **defines the route** (identity) + usage and review metadata.
- **routes_data_records**: one record per **access** to the route, with all metrics for that request.
- Be able to analyze by route: seasonality, 500s by parameters, etc., always querying over `records`.

---

## 1. Current state

### RouteData (routes_data)

| Field           | Current use                               |
|----------------|--------------------------------------------|
| env, name      | Identity (lookup by route+env)             |
| httpMethod     | Identity                                   |
| params         | Identity (JSON)                            |
| totalQueries   | Aggregated metric / "worst case"           |
| requestTime    | Aggregated metric                          |
| queryTime      | Aggregated metric                          |
| memoryUsage    | Aggregated metric                          |
| accessCount    | Access counter                             |
| statusCodes    | Aggregated metric (counts per HTTP code)   |
| createdAt      | Metadata                                   |
| updatedAt      | Metadata                                   |
| lastAccessedAt | Metadata                                   |
| reviewed, reviewedAt, reviewedBy, queriesImproved, timeImproved | Review |

Currently the lookup key is `(name, env)`; **params is not part of the key**, so the same route with different params is merged into a single row.

### RouteDataRecord (routes_data_records)

| Field         | Current use              |
|---------------|--------------------------|
| route_data_id | FK to RouteData          |
| accessed_at   | When                     |
| status_code   | HTTP status              |
| response_time | Time for that request    |

Missing per record: `total_queries`, `query_time`, `memory_usage`. Without these, "worst case" or metric-based seasonality cannot be analyzed without relying on aggregates stored in RouteData.

---

## 2. Target model

### 2.1 RouteData — route identity + usage + review only

**Keep in routes_data:**

| Field            | Type / Notes |
|------------------|--------------|
| id               | PK           |
| env              | string       |
| name             | string       |
| http_method      | string       |
| params           | JSON (nullable) — key to allow splitting by params later |
| created_at       | datetime_immutable |
| last_accessed_at | datetime_immutable (last access; updated when inserting a record or via command) |
| reviewed         | bool         |
| reviewed_at      | datetime_immutable nullable |
| reviewed_by      | string nullable |
| queries_improved | bool nullable (optional) |
| time_improved    | bool nullable (optional) |

**Remove from RouteData (become derived from records only):**

- totalQueries, requestTime, queryTime, memoryUsage  
- accessCount  
- statusCodes  
- updatedAt (or keep only if the row is updated for review/lastAccessedAt)

The idea: **one RouteData row = one "logical route"** identified by `(env, name, httpMethod, params)`. If you later split by params, the lookup key would be `(env, name, httpMethod, params)` and you could have multiple rows for the same `name` with different `params`.

### 2.2 RouteDataRecord — one record per access (the "rest")

**Content of each record:**

| Field          | Type / Notes |
|----------------|--------------|
| id             | PK           |
| route_data_id  | FK → routes_data |
| accessed_at    | datetime_immutable |
| status_code    | int nullable |
| response_time  | float nullable (time for that request) |
| total_queries  | int nullable *(add)* |
| query_time     | float nullable *(add)* |
| memory_usage   | int nullable *(add, optional)* |

With this, **everything that is "metric of a request" lives in records**. "Worst" values and counts are obtained via queries or an aggregation command (e.g. the current RebuildAggregates, extended).

---

## 3. Concrete changes

**Sync schema with entities:** after modifying entities (adding/removing columns), run:

```bash
php bin/console nowo:performance:sync-schema
```

To drop columns that no longer exist on the entity, use `--drop-obsolete`. See [COMMANDS.md](COMMANDS.md#nowoperformancesync-schema).

### Phase 1 — Extend RouteDataRecord

1. Add to entity and migration:
   - `totalQueries` (int, nullable)
   - `queryTime` (float, nullable)
   - `memoryUsage` (int, nullable), if you want memory per request
2. In `PerformanceMetricsService`, when creating each `RouteDataRecord`, also set:
   - `totalQueries`, `queryTime`, `memoryUsage` with the values for that request.

This way no information is lost and you can analyze per record.

### Phase 2 — Normalize RouteData

1. **Decide lookup key:**
   - Option A: keep `(name, env)` and do not use params in the key (params informational only).
   - Option B: key `(env, name, httpMethod, params)` to allow splitting by params later.
2. **Remove from RouteData** (and from the DB via migration):
   - totalQueries, requestTime, queryTime, memoryUsage, accessCount, statusCodes, updatedAt (if removed).
3. **Keep in RouteData:** env, name, httpMethod, params, createdAt, lastAccessedAt, reviewed, reviewedAt, reviewedBy (and optional queriesImproved, timeImproved).
4. **Repository:**  
   - If using params in the key: new method `findByRouteEnvAndParams(string $name, string $env, ?array $params)` (comparing normalized params, e.g. sorted JSON).  
   - The service that currently uses `findByRouteAndEnv` would switch to this lookup (with optional or normalized params).

### Phase 3 — Aggregates and listings

1. **Source of truth:** always `RouteDataRecord`.
2. **For listings and rankings (worst time, worst query count, etc.):**
   - Option 1: SQL/DQL queries that aggregate over `routes_data_records` (MAX(response_time), MAX(total_queries), COUNT(*), GROUP BY route_data_id).
   - Option 2: aggregate cache table (one row per route_data_id with max_request_time, max_total_queries, access_count, status_counts) updated when inserting a record or via a command like `nowo:performance:rebuild-aggregates`.
3. **RebuildAggregatesCommand:** no longer writes statusCodes/accessCount to RouteData if those fields are removed; it could instead fill the aggregate cache table or rely entirely on real-time queries.

### Phase 4 — "Route-specific data" views

1. **Route detail (by RouteData id):**
   - List `RouteDataRecord` for that `route_data_id` with filters:
     - Date range (`accessed_at`) → **seasonality**.
     - `status_code = 500` → view only 500 errors.
     - Optional: group by params (if in the future params is also stored on the record or derived from RouteData).
2. **Seasonality:**
   - Query records by `route_data_id` and date range; aggregate by day/week/month (COUNT, AVG(response_time), MAX(response_time), COUNT when status_code=500).
3. **"Returns 500 for certain params":**
   - If you later split by params (multiple RouteData for the same name with different params), each has its own records; filter by `status_code = 500` per RouteData.
   - If params is not split yet, 500s for that route are seen by filtering records by `route_data_id` and `status_code = 500` (and optionally by params if stored on record).

---

## 4. Recommended order

| Step | Action |
|-----|--------|
| 1   | Add to `RouteDataRecord`: totalQueries, queryTime, memoryUsage. Migration + fill in PerformanceMetricsService. |
| 2   | Decide whether RouteData key includes params (findByRouteEnvAndParams) and update repository + service. |
| 3   | Create migration that removes from RouteData: totalQueries, requestTime, queryTime, memoryUsage, accessCount, statusCodes (and updatedAt if desired). |
| 4   | Replace uses of those fields in controllers, commands and tests: read from records or an aggregate layer (query or cache). |
| 5   | Implement "route detail" view/API: list of records with filters (dates, status_code) and aggregations for seasonality. |
| 6   | (Optional) Aggregate table or materialized views if per-environment listings are slow. |

---

## 5. Summary

- **routes_data:** identity (env, name, httpMethod, params) + createdAt, lastAccessedAt, reviewed, reviewedAt, reviewedBy. No metrics.
- **routes_data_records:** per access: accessed_at, status_code, response_time, total_queries, query_time, memory_usage. All analyzable data goes here.
- **Analysis by route:** always over records (filter by route_data_id, dates, status_code; aggregate by time for seasonality).
- **Splitting by params later:** key (env, name, httpMethod, params) in RouteData; each combination has its own records.
