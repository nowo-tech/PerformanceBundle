# Commands

## nowo:performance:set-route

Set or update route performance metrics.

### Usage

```bash
php bin/console nowo:performance:set-route <route> [options]
```

### Arguments

- `route` (required) - The route name

### Options

- `--env, -e` - Environment (dev, test, prod) (default: `dev`)
- `--request-time, -r` - Request time in seconds (float)
- `--queries` - Total number of queries (integer)
- `--query-time, -t` - Total query execution time in seconds (float)
- `--memory, -m` - Peak memory usage in bytes (integer)
- `--params, -p` - Route parameters as JSON string

### Description

This command sets or updates route performance metrics. If a route doesn't exist, it will be created. If it exists, it will be updated only if the new metrics are worse (higher request time or more queries).

### Examples

#### Basic Usage

```bash
# Set request time and query count
php bin/console nowo:performance:set-route app_home \
    --request-time=0.5 \
    --queries=10
```

#### Full Metrics

```bash
# Set all metrics
php bin/console nowo:performance:set-route app_user_show \
    --env=prod \
    --request-time=1.2 \
    --queries=25 \
    --query-time=0.3 \
    --params='{"id":123}'
```

#### Update with Worse Metrics

```bash
# Update if metrics are worse
php bin/console nowo:performance:set-route app_home \
    --request-time=0.8 \
    --queries=15
```

The command will only update if:
- Request time is higher than existing
- Query count is higher than existing
- No existing data exists

### Output

The command displays a table with the saved metrics:

```
Route metrics saved successfully!
+----------------+-------+
| Metric         | Value |
+----------------+-------+
| Route Name     | app_home |
| Environment    | dev   |
| Request Time   | 0.5000 s |
| Total Queries  | 10    |
| Query Time     | 0.2000 s |
| Updated At     | 2026-01-23 10:30:00 |
+----------------+-------+
```

### Error Handling

- If JSON in `--params` is invalid, the command will fail with an error message
- If no metrics are provided, the command will fail
- If database errors occur, the command will display an error message

## nowo:performance:create-table

Create the performance metrics database table with all necessary columns and indexes.

### Usage

```bash
php bin/console nowo:performance:create-table [options]
```

### Options

- `--force, -f` - Force table creation even if it already exists (WARNING: This will delete all data)
- `--update, -u` - Add missing columns to existing table without losing data (safe, preserves data)
- `--drop-obsolete` - Drop columns that exist in DB but not in entity (use with `--update`). Never drops the `id` column.
- `--connection` - The Doctrine connection name to use (default: `default`)

### Description

This command creates the `routes_data` table with all necessary columns and indexes for optimal performance. It's useful for:
- Initial setup
- Recreating the table after schema changes
- Setting up the table in new environments
- **Updating existing tables** - Adding missing columns without losing data
- **Fixing AUTO_INCREMENT** - Automatically fixes missing AUTO_INCREMENT on the `id` column, even with foreign key constraints

### Examples

```bash
# Create table if it doesn't exist
php bin/console nowo:performance:create-table

# Force recreation (WARNING: Deletes all data)
php bin/console nowo:performance:create-table --force

# Update existing table (add missing columns, fix AUTO_INCREMENT)
php bin/console nowo:performance:create-table --update

# Sync schema and drop columns no longer in entity (after removing fields from RouteData)
php bin/console nowo:performance:create-table --update --drop-obsolete

# Use a different connection
php bin/console nowo:performance:create-table --connection=performance
```

### Features

#### Automatic AUTO_INCREMENT Fix

If the `id` column is missing AUTO_INCREMENT (common issue causing "Field 'id' doesn't have a default value" errors), the command will:

1. **Detect the issue** - Automatically checks if AUTO_INCREMENT is missing
2. **Handle foreign keys** - Temporarily drops foreign keys that reference the `id` column
3. **Fix the column** - Adds AUTO_INCREMENT to the `id` column
4. **Restore foreign keys** - Restores all foreign keys with their original rules (UPDATE/DELETE CASCADE, etc.)

This works seamlessly even if you have foreign keys from other tables (e.g., `routes_data_records`) referencing the `id` column.

#### Column Updates

The `--update` option:
- Adds missing columns without losing existing data
- Updates column definitions if they differ (nullable, type, defaults)
- Adds missing indexes
- Preserves all existing data

With `--drop-obsolete` (use together with `--update`):
- Drops columns that exist in the database but are no longer in the entity mapping
- The primary key column `id` is never dropped
- Use when you have removed or renamed fields in the entity and want the table to match

### Output

The command shows:
- Table name and connection being used
- SQL statements being executed
- Foreign key operations (if fixing AUTO_INCREMENT)
- Success message when complete

If the table already exists and neither `--force` nor `--update` is used, it will show a warning and suggest using `--update` or `--force` or Doctrine migrations.

### Common Issues

**Error: "Field 'id' doesn't have a default value"**

This happens when the `id` column is missing AUTO_INCREMENT. Fix it with:
```bash
php bin/console nowo:performance:create-table --update
```

**Error: "Cannot change column 'id': used in a foreign key constraint"**

The command now handles this automatically. If you see this error, make sure you're using the latest version of the bundle, then run:
```bash
php bin/console nowo:performance:create-table --update
```

The command will automatically drop and restore foreign keys during the fix.

## nowo:performance:create-records-table

Create or update the temporal access records table used for seasonality analysis.

### Usage

```bash
php bin/console nowo:performance:create-records-table [options]
```

### Options

- `--force, -f` - Force table creation even if it already exists (WARNING: This will delete all data)
- `--update, -u` - Add missing columns to existing table without losing data (safe, preserves data)
- `--drop-obsolete` - Drop columns that exist in DB but not in entity (use with `--update`). Never drops the `id` column.
- `--connection` - The Doctrine connection name to use (default: `default`)

### Description

This command creates the `routes_data_records` table based on the `RouteDataRecord` entity. It is used
for temporal analysis features (hour-of-day, day-of-week, month and heatmaps in the access statistics UI).

The `--update` option:
- Adds missing columns without losing existing data
- Updates column definitions if they differ (type, nullable, defaults)
- Adds missing indexes
- Leaves extra/legacy columns untouched unless you use `--drop-obsolete`

With `--drop-obsolete`: drops columns that exist in DB but not in the entity (never drops `id`).

Use this command when:
- Enabling `enable_access_records` for the first time
- Deploying to a new environment where temporal records are needed
- Adding new fields to `RouteDataRecord` in future versions

## nowo:performance:purge-records

Purge access records (RouteDataRecord) by age or delete all.

### Usage

```bash
php bin/console nowo:performance:purge-records [options]
```

### Options

- `--older-than=N, -o N` - Delete records older than N days (e.g. `--older-than=30`)
- `--all, -a` - Delete all access records
- `--env=ENV, -e ENV` - Limit to a specific environment (dev, prod, etc.)
- `--dry-run` - Show what would be deleted without actually deleting

### Description

Deletes access records from the `routes_data_records` table. Without options, uses the configured `access_records_retention_days` (must be set). With `--older-than=N`, deletes records with `accessed_at` older than N days. With `--all`, deletes all records.

Use via cron to automatically purge old records when `access_records_retention_days` is configured:

```bash
# Purge records older than retention_days (from config)
php bin/console nowo:performance:purge-records

# Purge records older than 30 days
php bin/console nowo:performance:purge-records --older-than=30

# Purge all records for prod
php bin/console nowo:performance:purge-records --all --env=prod

# Preview what would be deleted
php bin/console nowo:performance:purge-records --older-than=30 --dry-run
```

Requires `enable_access_records: true`.

## nowo:performance:sync-schema

Sync database schema with entity metadata for both tables in one go.

### Usage

```bash
php bin/console nowo:performance:sync-schema [options]
```

### Options

- `--drop-obsolete` - Drop columns that exist in DB but not in entity (for both tables). Never drops the `id` column.

### Description

Runs the same logic as `nowo:performance:create-table --update` and `nowo:performance:create-records-table --update` together:

1. **Add** columns that exist in the entity but not in the database
2. **Alter** columns whose type, nullable or default differ from the entity
3. **Drop** (only with `--drop-obsolete`) columns that exist in the database but not in the entity

Useful after changing entity mappings: run sync-schema to bring the database in line with the code without generating migrations manually.

### Examples

```bash
# Sync without dropping any columns (add + alter only)
php bin/console nowo:performance:sync-schema

# Sync and drop obsolete columns
php bin/console nowo:performance:sync-schema --drop-obsolete
```

## nowo:performance:rebuild-aggregates

Rebuild `RouteData` aggregate fields from `RouteDataRecord` access logs.

### Usage

```bash
php bin/console nowo:performance:rebuild-aggregates [options]
```

### Options

- `--env=ENV` - Restrict rebuild to a single environment (e.g. `dev`, `test`, `prod`)
- `--batch-size=NUMBER` - Batch size for flushing changes (default: `200`)

### Description

`RouteData` stores aggregate metrics (request time, query count, status codes, access count, last access)
while `RouteDataRecord` stores individual access records. In some scenarios (manual imports, schema changes)
aggregates can become out of sync with the underlying records.

This command:
- Iterates over all `RouteData` rows (optionally filtered by environment)
- Recomputes:
  - `accessCount` from the number of related `RouteDataRecord` rows
  - `lastAccessedAt` from the latest `accessedAt`
  - `statusCodes` JSON from the distribution of `statusCode` values in records
- Flushes changes in batches for efficiency

Use it when:
- You imported `routes_data_records` manually
- You suspect `accessCount`, `lastAccessedAt` or `statusCodes` are out of sync
- After upgrading and backfilling temporal records

## nowo:performance:diagnose

Diagnose Performance Bundle configuration and query tracking status.

### Usage

```bash
php bin/console nowo:performance:diagnose
```

### Description

Provides a comprehensive diagnostic report including:
- Bundle configuration (enabled, environments, connection, table name)
- Dashboard configuration (enabled, path, roles, template)
- Doctrine Bundle version detection
- Query tracking method (middleware registration strategy)
- Database connection status
- **Database tables** – Main table and access records table (when `enable_access_records` is true): exists, complete, missing columns (e.g. `request_id`, `referer`, `user_identifier`, `user_id`). Suggests `sync-schema` or `create-records-table --update` when columns are missing.
- Query tracking status and troubleshooting tips

### Use Cases

- Verify bundle configuration
- Troubleshoot query tracking issues
- Understand how the bundle is configured
- Check database connectivity

### Example Output

```
Performance Bundle Configuration:
  Enabled: Yes
  Environments: dev, test
  Connection: default
  Table Name: routes_data

Dashboard Configuration:
  Enabled: Yes
  Path: /performance
  Template: bootstrap
  Roles: []

Query Tracking:
  DoctrineBundle Version: 3.0.0
  Method: Reflection-based (DoctrineBundle 3.x)
  Status: Active

Database:
  Connection: OK
  Table Exists: Yes
```

## nowo:performance:check-dependencies

Check if optional dependencies are installed for the Performance Bundle.

### Usage

```bash
php bin/console nowo:performance:check-dependencies
```

### Description

Verifies the status of optional dependencies:
- Symfony UX TwigComponent availability
- Other optional dependencies (if any)

### Use Cases

- Verify which optional features are available
- Get installation instructions for missing dependencies
- Understand what features are using fallback mode

### Example Output

```
Optional Dependencies Status:

✓ Symfony UX TwigComponent: Installed
  The dashboard will use Twig Components for better performance.

Missing Dependencies:
  ✗ None

All optional dependencies are installed!
```

If dependencies are missing, the command will show installation instructions.
