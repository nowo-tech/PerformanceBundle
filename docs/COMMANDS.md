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
| Updated At     | 2025-01-23 10:30:00 |
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
- Table existence check
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
