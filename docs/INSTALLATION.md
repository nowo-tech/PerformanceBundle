# Installation Guide

## Requirements

- PHP >= 8.1, < 8.6
- Symfony >= 6.1 || >= 7.0 || >= 8.0
- Doctrine ORM >= 2.13 || >= 3.0
- Doctrine Bundle >= 2.8 || >= 3.0 (3.0 required for Symfony 8)

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
    environments: ['dev', 'test']
    connection: 'default'
    track_queries: true
    track_request_time: true
    ignore_routes:
        - '_wdt'
        - '_profiler'
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

If the table already exists, you can force recreation (WARNING: This will delete all data):
```bash
php bin/console nowo:performance:create-table --force
```

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

Check that the command is available:

```bash
php bin/console list nowo:performance
```

You should see:
- `nowo:performance:set-route` - Set or update route performance metrics

## That's It!

The bundle is now installed and will automatically track route performance metrics in the configured environments.

## Next Steps

- See [Configuration Guide](CONFIGURATION.md) for detailed configuration options
- See [Usage Guide](USAGE.md) for usage examples
- See [Commands](COMMANDS.md) for command documentation
