# Performance Bundle Roadmap

Living plan for `nowo-tech/performance-bundle`. Shipped history lives in [CHANGELOG.md](CHANGELOG.md); this file lists **current product** and **open ideas** only.

## Table of contents

- [Current state (v3.4.x)](#current-state-v34x)
- [Open ideas](#open-ideas)
- [Non-goals](#non-goals)

## Current state (v3.4.x)

Tag **v3.4.2** (2026-08). The dashboard uses **FormKit** and **UiKit** (required Composer deps since 3.4.0).

Already in the bundle:

- Automatic route metrics (time, queries, memory, access count, HTTP status, optional sub-requests)
- Doctrine persistence, sampling, cache, ignore lists, multi-connection
- Dashboard `/performance` (filters, Chart.js, CSV/JSON export, review)
- Notifications (email / Slack / Teams / webhook)
- CLI: rebuild aggregates, sync schema, purge access records, diagnose, set metrics, check dependencies, create tables
- Web Profiler collector

Phase 1 (foundation, Q1 2026) is **done**. Do not treat the old Q2–Q4 2026 enterprise checklist as a schedule.

## Open ideas

Not committed; pick up only if they serve real traffic:

- Optional **Redis/Memcached** cache backend (today: Symfony cache pool)
- **Database partitioning** for very high-volume `access_record` tables
- **Batch flush** of in-request metrics to cut I/O
- Richer distributed tracing / APM export (beyond Sentry/Beacon pairing in the host app)

## Non-goals

- Replacing APM products (New Relic, Datadog, Blackfire)
- Storing full SQL text or request bodies in this bundle (see HttpLogBundle)

---

**Last updated:** 2026-08-17
