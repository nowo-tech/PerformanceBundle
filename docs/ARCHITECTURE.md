# Architecture

## Table of contents

- [Overview](#overview)
- [Ports, events and VOs](#ports-events-and-vos)
- [Standard Symfony and DDD hosts](#standard-symfony-and-ddd-hosts)
- [REQ-ARCH-001 summary](#req-arch-001-summary)

## Overview

PerformanceBundle is operations infrastructure: it measures requests and stores route metrics in its own tables
(`routes_data`, `routes_data_records`) through its own entities, and ships an admin dashboard and maintenance commands.
Host entities are never referenced (the user is stored as strings).

## Ports, events and VOs

| Element | Kind | Notes |
|---------|------|-------|
| `Notification\RouteMetricsNotificationChannelInterface` (3.17.0) | SPI | Receives a `RouteMetricsSnapshot`; preferred |
| `Notification\NotificationChannelInterface` | SPI | Entity form of `send()` (`RouteData` or `AfterMetricsRecordedEvent`), replaced in 4.0 |
| `Security\PerformanceAccessCheckerInterface` | SPI | Dashboard access |
| `MessageBus\MessageBusInterface` | SPI | Async recording |
| `Model\RouteMetricsSnapshot` (3.17.0) | VO | Framework-free copy of a route row plus the metrics just recorded |
| `Before/AfterMetricsRecordedEvent`, `Before/AfterRecordReviewedEvent`, `Before/AfterRecordDeletedEvent`, `Before/AfterRecordsClearedEvent` | Events | Every event that carries a route also has `getRouteDataId()`, `getRouteName()`, `getEnv()` and `getRouteMetrics()` since 3.17.0; `getRouteData()` is deprecated and removed in 4.0 |

The events extend `Symfony\Contracts\EventDispatcher\Event` (an allowed contracts package). See [EVENTS](EVENTS.md) and
[NOTIFICATIONS](NOTIFICATIONS.md).

## Standard Symfony and DDD hosts

- **Standard Symfony host:** install, keep `connection: default`, read the dashboard. Listeners and channels may keep
  using the entity accessors until 4.0.
- **DDD / hexagonal host:** see [USAGE — Using it from a DDD / hexagonal application](USAGE.md#using-it-from-a-ddd--hexagonal-application).
  In short: give the bundle its own entity manager (`connection: performance`), read ids and `RouteMetricsSnapshot`
  in listeners, implement `RouteMetricsNotificationChannelInterface` to forward alerts to your own bus, and never
  reference `RouteData` / `RouteDataRecord` from your domain.

## REQ-ARCH-001 summary

| Rule | Status |
|------|--------|
| 1 Interfaces + VOs | SPIs above; `RouteMetricsSnapshot`; `PerformanceMetricsService` has no interface yet |
| 2 No forced base class / trait | Bundle-owned entities, no host relation |
| 3 No framework types in ports / events / VOs | Snapshot and id accessors on every route event; snapshot channel SPI |
| 4 Events carry IDs | Yes (3.17.0); entity accessors deprecated |
| 5 SPI | Notification channels, access checker, message bus, `Before*` events can veto |
| 6 No host EM flush | Recording: private manager (3.16.0). Admin / `rebuild-aggregates`: ⚠️ flush the `connection` manager; opt-in: a dedicated manager; 4.0 moves them to the private manager |
| 7 Events after commit | `After*` events after the write |
| 8 Config through provider ports | Bundle config only |
