# Installation Guide

> 📑 **Documentation index**: [docs/README.md](README.md)

## Requirements

- PHP >= 8.1, < 8.6
- Symfony >= 6.1 || >= 7.0 || >= 8.0
- Doctrine ORM >= 2.13 || >= 3.0
- Doctrine Bundle >= 2.8 || >= 3.0 (3.0 required for Symfony 8)

> 📖 **For detailed information about compatibility with different Doctrine and DBAL versions, see [COMPATIBILITY.md](COMPATIBILITY.md)**

## Step 1: Install the Bundle

```bash
composer require nowo-tech/performance-bundle
```

## Step 2: Register the Bundle

The bundle should be automatically registered via Symfony Flex. If not, manually register it in `config/bundles.php`:

```php
<?php

return [
    // ...
    Nowo\PerformanceBundle\NowoPerformanceBundle::class => ['all' => true],
];
```

## Step 3: Configure the Bundle (Optional)

Create `config/packages/nowo_performance.yaml`:

```yaml
nowo_performance:
    enabled: true
    environments: ['prod', 'dev', 'test']  # Or restrict to ['dev', 'test']
    connection: 'default'
    track_queries: true
    track_request_time: true
    track_sub_requests: false  # Set to true to track sub-requests (ESI, fragments, etc.)
    ignore_routes:
        - '_wdt'
        - '_profiler'
        - 'web_profiler*'
        - '_error'
```

## Step 4: Create the Database Table

### Option A: Using the Bundle Command (Recommended)

```bash
php bin/console nowo:performance:create-table
```

This command will:
- Check if the table already exists
- Create the table with all necessary columns and indexes
- Show you exactly what SQL is being executed
- Automatically configure AUTO_INCREMENT for the `id` column

If the table already exists, you have two options:

**Option 1: Update existing table (Recommended - preserves data)**
```bash
php bin/console nowo:performance:create-table --update
```
This will:
- Add missing columns without losing data
- Fix AUTO_INCREMENT if missing (handles foreign keys automatically)
- Add missing indexes

**Option 2: Force recreation (WARNING: This will delete all data)**
```bash
php bin/console nowo:performance:create-table --force
```

**When using access records (`enable_access_records: true`)**

Create or update the access records table as well:

```bash
php bin/console nowo:performance:create-records-table
# or use sync-schema to update both tables in one go
php bin/console nowo:performance:sync-schema
```

**Syncing both tables after entity changes**

If you change entity mappings (add/remove/rename fields), sync the database with:

```bash
php bin/console nowo:performance:sync-schema
```

Use `--drop-obsolete` to also remove columns that no longer exist in the entity. See [Commands](COMMANDS.md#nowoperformancesync-schema).

> **Note**: If you encounter the error "Field 'id' doesn't have a default value", run the command with `--update` to fix it automatically.

### Option B: Using Doctrine Schema Update

```bash
php bin/console doctrine:schema:update --force
```

### Option C: Using Migrations (Recommended for Production)

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## Step 5: Verify Installation

Check that the commands are available:

```bash
php bin/console list nowo:performance
```

You should see at least:
- `nowo:performance:create-table` - Create or update the main metrics table
- `nowo:performance:create-records-table` - Create or update the access records table (when `enable_access_records: true`)
- `nowo:performance:sync-schema` - Sync both tables with entity metadata
- `nowo:performance:set-route` - Set or update route metrics manually
- `nowo:performance:diagnose` - Diagnose configuration and tracking status
- `nowo:performance:check-dependencies` - Check optional dependencies

See [Commands](COMMANDS.md) for full command documentation.

## That's It!

The bundle is now installed and will automatically track route performance metrics in the configured environments.

## Next Steps

- See [Configuration Guide](CONFIGURATION.md) for detailed configuration options
- See [Usage Guide](USAGE.md) for usage examples
- See [Commands](COMMANDS.md) for command documentation

> **Upgrading to 2.0?** Version 2.0.0 (released 2026-01-28) introduces breaking changes (entity normalization). See [V2_MIGRATION.md](V2_MIGRATION.md) and [ENTITY_NORMALIZATION_PLAN.md](ENTITY_NORMALIZATION_PLAN.md).
