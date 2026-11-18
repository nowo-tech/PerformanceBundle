# Concurrency

How the bundle stays correct when two workers, two requests or a replayed message write the same data at the same
time (REQ-RUNTIME-003). It applies to FrankenPHP worker mode with the kernel not reset between requests too
(see [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md)).

## Table of contents

- [Summary](#summary)
- [Write paths](#write-paths)
- [The metrics write unit](#the-metrics-write-unit)
- [Conflict answer of the dashboard](#conflict-answer-of-the-dashboard)
- [Host options](#host-options)
- [Entity managers](#entity-managers)
- [Tests and CI](#tests-and-ci)
- [Accepted residual risk](#accepted-residual-risk)

## Summary

- **One route, one row.** `routes_data` has the unique constraint `uniq_route_env_name` on (`env`, `name`) since
  3.18.0. Two first requests of a new route in two workers insert it once: the loser's insert fails on the constraint,
  and its write unit runs again and re-reads the row the winner committed.
- **One request, one access record.** `routes_data_records.request_id` is unique. When two writes race on the same
  request id, the loser saves the route's metrics without a second access record and logs a warning.
- **One transaction per write unit.** The route row and the access record are committed together, through
  `nowo-tech/transaction-doctrine`'s `DoctrineTransactionRunner` on the bundle's private entity manager.
- **Lock conflicts are not swallowed.** A deadlock or lock wait timeout in the metrics path is replayed (3 attempts in
  all); after the last one the failure is rethrown, so Messenger retries the message, and the request subscriber logs a
  warning. A dashboard write that meets a lock conflict answers **409 + `Retry-After`** with `CONFLICT_001`, or **409**
  with `CONFLICT_002` when part of the work was already committed.
- **The host's entity manager is never cleared, reset or flushed by the metrics path.**

## Write paths

| Write path | Where | Transaction | Concurrency control | Conflict answer |
|------------|-------|-------------|---------------------|-----------------|
| Request metrics (route row + access record) | `PerformanceMetricsService::recordMetricsSync()`, from `PerformanceMetricsSubscriber` (kernel.terminate) or `RecordMetricsMessageHandler` (async) | One runner transaction on the private entity manager | Unique `uniq_route_env_name` + re-read; unique `uniq_record_request_id` + re-read; replay on lock conflict | Not HTTP: replayed, then rethrown (Messenger retries; the subscriber logs a warning and never breaks the request) |
| Review a route (review fields + "save access records") | `PerformanceController::review()` | One runner transaction on the configured connection | Last write wins on one row (an admin action) | 409 + `Retry-After` + `CONFLICT_001` |
| Delete a route (cascades its records) | `PerformanceController::delete()` → `RouteDataRepository::deleteById()` | One flush (one statement + FK cascade) | Row delete by id | 409 + `CONFLICT_001` |
| Clear routes of an environment | `PerformanceController::clear()` → `RouteDataRepository::deleteAll()` | One DQL statement | Set delete | 409 + `CONFLICT_001` |
| Purge / delete access records by filter | `PerformanceController::purgeRecords()`, `deleteRecordsByFilter()` → `RouteDataRecordRepository` | Chunked: 1,000 ids per statement, each committed on its own (short locks) | Set delete by id | 409 + `CONFLICT_001` before the first batch; `CONFLICT_002` (no `Retry-After`) after one |
| Purge command | `nowo:performance:purge-records` | Chunked as above | As above | CLI: the `ConcurrencyConflict` (with `committed`) propagates (exit code 1); running the command again finishes the purge |
| Rebuild aggregates | `nowo:performance:rebuild-aggregates` | One transaction per batch (`--batch-size`) | DQL `UPDATE … SET last_accessed_at = MAX(records)` by id; no entity loaded | CLI |
| Schema commands | `create-table`, `create-records-table`, `sync-schema` | DDL (implicit commits on MySQL); route merge one transaction per duplicated route | Run by an operator, not under load | CLI |

`grep -rn "flush()" src/` finds: the metrics write unit (inside the runner), `RouteDataRepository::deleteById()` and
`markAsReviewed()` (one row each, inside the controller's runner or a single statement), and the review action's
"save access records" flush (inside the same runner closure).

## The metrics write unit

```
attempt 1..3:
  private entity manager (fresh after any failure)
  runner->run:  BEGIN
                SELECT route by (name, env)           -- no lock
                INSERT route if missing  /  UPDATE last_accessed_at
                SELECT access record by request id   -- skip if it exists
                INSERT access record
                COMMIT
  unique violation  -> run again (re-read what the other worker committed)
  lock conflict     -> short jittered pause (20–50 ms × attempt), run again
  anything else     -> rethrow
after the commit: cache invalidation, AfterMetricsRecordedEvent, clear the private manager
```

- **Third attempt after two unique violations:** the route is saved without an access record, and a warning says so.
  This only happens when the same request id keeps colliding.
- **Inside a host transaction** (the connection is already in a transaction when recording starts) the unit runs once
  and a failure propagates: the host transaction may already have been rolled back by the database, so only the host
  may replay it.
- **Logs.** A replay is logged at notice level, a skipped access record and lost metrics at warning level, with the
  route, the environment and the exception class, never the exception message (it can hold SQL). These warnings are
  logged whatever `enable_logging` says; `enable_logging` gates the debug traces only.

## Conflict answer of the dashboard

The dashboard's POST actions map a lock conflict (MySQL / MariaDB 1213, 1205, MariaDB 1020, PostgreSQL 40001, 40P01,
55P03) to `Nowo\PerformanceBundle\Concurrency\WriteConflictException`, an `HttpException` the host's error page renders:

| Code | When | Status | Headers |
|------|------|--------|---------|
| `CONFLICT_001` | Nothing was committed; retry is safe | 409 (or the host's configured status) | `Retry-After` (1 s by default), `Cache-Control: no-store, private` |
| `CONFLICT_002` | A chunked delete already committed part of its batches; read again before repeating | 409 (or the host's configured status) | `Cache-Control: no-store, private`, **no** `Retry-After` |

The message starts with the code and holds no SQL. Unique violations, lost connections and every other database error
are not mapped to a conflict: they keep the actions' error flash.

## Host options

- **Status and Retry-After.** When the host registers `NowoTransactionDoctrineBundle`, the bundle reads the shared
  `nowo_transaction_doctrine.conflict_response` policy (status 409 or 503, `retry_after`), so the whole application
  answers the same. Without it, 409 and `Retry-After: 1`.
- **Own entity manager.** The dashboard writes through the entity manager of the configured `connection`. A host that
  shares that manager with application code and wants the bundle never to flush it can give the bundle a dedicated
  connection and entity manager (`nowo_performance.connection`).
- **Async recording.** With `async: true` the Messenger handler runs the same write unit; a final lock conflict
  propagates as `TransientStorageFailure`, so the transport's retry strategy applies.

## Entity managers

- The metrics path writes through a **private entity manager** (`MetricsEntityManagerFactory`) on the configured
  connection: its flush only writes the bundle's entities, it is cleared after every recording and dropped after a
  failure. The host's manager is never flushed, cleared or reset by it.
- `nowo:performance:rebuild-aggregates` loads no entity and updates through DQL, so it never clears the injected
  manager (3.17 cleared it).
- The bundle's own runner is used instead of the host's `TransactionRunnerInterface` on purpose: a host runner
  flushes the host's manager, which would commit the host's pending changes with the metrics.

## Tests and CI

- `tests/Unit/Service/PerformanceMetricsServiceConcurrencyTest.php` (SQLite): route race, request-id race, repeated
  unique violations, deadlock replay, lock timeout on every attempt, no replay inside a host transaction.
- `tests/Integration/Concurrency/MetricsWriteRaceTest.php` (two connections on MySQL 8.4, MariaDB 11.4 and
  PostgreSQL 16): two first requests on the same new route give exactly one `RouteData`; a duplicate request id still
  saves the other metrics; a real row lock (`SELECT … FOR UPDATE` on the second connection) makes the write give up
  with a 409 `CONFLICT_001` answer and leaves the connection usable; a chunked purge that conflicts after a committed
  batch answers `CONFLICT_002`; `create-table --update` merges duplicates and adds the unique constraint.
- CI job **Databases** (required check): runs those race tests and the table-command database tests on the three
  databases. DBAL 4 only for now: `src/DBAL/QueryTrackingConnection.php` does not load on DBAL 3, although
  `composer.json` allows `^3.8.2`.

## Accepted residual risk

- **Case-insensitive collations.** On MySQL / MariaDB with a case-insensitive collation, route names that differ only
  in case (`app_Home`, `app_home`) share one row, as the lookup already did before 3.18; PostgreSQL keeps them apart.
  Symfony route names are conventionally lowercase.
- **Review is last-write-wins.** Two admins reviewing the same route at the same moment: the later review is kept.
  The review is an admin annotation with no business rule attached.
- **Chunked purges are not atomic by design** (short locks on a hot table, rule 9): a conflict half-way answers
  `CONFLICT_002`, and running the purge again finishes it.
