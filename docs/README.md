# Documentation Index

Documentation for the **Performance Bundle**. Links are relative to this folder (`docs/`).

## Main guides

| Document | Description |
|----------|-------------|
| [INSTALLATION.md](INSTALLATION.md) | Step-by-step installation: requirements, bundle registration, initial configuration, table creation, and verification. |
| [CONFIGURATION.md](CONFIGURATION.md) | Full configuration reference. Default values and examples. **Source of truth** for defaults. |
| [USAGE.md](USAGE.md) | Bundle usage: automatic tracking, manual commands, dashboard customization, events, and best practices. |
| [COMMANDS.md](COMMANDS.md) | Documentation for all `nowo:performance:*` commands (create-table, create-records-table, sync-schema, set-route, diagnose, purge-records, check-dependencies, rebuild-aggregates). |

## Specific topics

| Document | Description |
|----------|-------------|
| [BEHAVIOUR_AND_CHANGES.md](BEHAVIOUR_AND_CHANGES.md) | **Behaviour and notable changes** – Why certain behaviours exist; detailed notes on non-obvious changes (e.g. VarDumper in web vs CLI). |
| [EVENTS.md](EVENTS.md) | Bundle events (BeforeMetricsRecorded, AfterRecordDeleted, etc.), internal listener flow, and **priorities** (relevant for `ignore_routes`). |
| [COMPATIBILITY.md](COMPATIBILITY.md) | Doctrine/DBAL and Symfony version compatibility. |
| [NOTIFICATIONS.md](NOTIFICATIONS.md) | Performance alert notifications (email, Slack, Teams, webhooks). |
| [ASYNC_METRICS.md](ASYNC_METRICS.md) | Asynchronous metrics recording with Symfony Messenger. |
| [UPGRADING.md](UPGRADING.md) | Upgrade guide between bundle versions. |
| [V2_MIGRATION.md](V2_MIGRATION.md) | Migration to version 2.0 (entity changes). |
| [ENTITY_NORMALIZATION_PLAN.md](ENTITY_NORMALIZATION_PLAN.md) | Entity normalization plan (technical reference). |

## Other

| Document | Description |
|----------|-------------|
| [CHANGELOG.md](CHANGELOG.md) | Version history and changes. |
| [CONTRIBUTING.md](CONTRIBUTING.md) | How to contribute. |
| [ROADMAP.md](ROADMAP.md) | Roadmap and future improvements. |
| [BRANCHING.md](BRANCHING.md) | Branching strategy. |

## Cross-references

- **Configuration defaults**: Defined in code in `Configuration.php`; the reference doc is [CONFIGURATION.md](CONFIGURATION.md). In case of discrepancy, code wins.
- **Listener priority and `ignore_routes`**: [EVENTS.md – Internal event flow and priorities](EVENTS.md#internal-event-flow-and-priorities). [CONFIGURATION.md – ignore_routes](CONFIGURATION.md#ignore_routes) links to EVENTS for the full flow.
- **Table commands**: [COMMANDS.md](COMMANDS.md) documents `create-table`, `sync-schema`, `create-records-table`. [INSTALLATION.md](INSTALLATION.md) links to COMMANDS for details.
