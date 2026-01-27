# Upgrading Guide

This guide helps you upgrade between versions of the Performance Bundle.

## Upgrading to 0.0.5 (2025-01-27)

### Compatibility Fix

This version removes YAML middleware configuration completely to fix compatibility issues across all DoctrineBundle versions.

#### Changes

- **DoctrineBundle middleware configuration**: Removed YAML middleware configuration (`middlewares` and `yamlMiddleware`)
- YAML middleware options are not reliably available across all DoctrineBundle versions
- Some versions (like 2.17.1) do not support these options, causing "Unrecognized option" errors
- Changed to use only reflection-based middleware application via `QueryTrackingConnectionSubscriber`
- This approach works consistently across all DoctrineBundle versions (2.x and 3.x)
- **QueryTrackingConnectionSubscriber**: Added required `getSubscribedEvents()` method for `EventSubscriberInterface` compliance
- Fixes "Class contains 1 abstract method" error

#### What This Means

- **No configuration changes required**: The bundle automatically uses reflection-based middleware application
- **No database changes required**: This is a code-only fix
- **Fixes installation errors**: Resolves "Unrecognized option 'middlewares'" errors when installing the bundle
- **Works across all versions**: No more compatibility issues with different DoctrineBundle versions

#### Migration Steps

1. **Update the bundle**:
   ```bash
   composer update nowo-tech/performance-bundle
   ```

2. **Clear cache**:
   ```bash
   php bin/console cache:clear
   ```

3. **Verify installation**:
   ```bash
   php bin/console nowo:performance:diagnose
   ```

#### Troubleshooting

**Issue: Still seeing "Unrecognized option 'middlewares'" error**

- **Solution**: Clear Symfony cache and verify the bundle version:
  ```bash
  php bin/console cache:clear
  composer show nowo-tech/performance-bundle
  ```
  Make sure you're using version 0.0.5 or higher.

**Issue: Middleware not being registered**

- **Solution**: Run the diagnose command to check middleware registration:
  ```bash
  php bin/console nowo:performance:diagnose
  ```
  The command will show that middleware is applied via Event Subscriber (Reflection).

**Issue: "Class contains 1 abstract method" error**

- **Solution**: This was fixed in version 0.0.5. Update to the latest version:
  ```bash
  composer update nowo-tech/performance-bundle
  php bin/console cache:clear
  ```

## Upgrading to 0.0.4 (2025-01-27)

### Bug Fix

This version fixes a fatal error that occurred during container compilation.

#### Changes

- **PerformanceDataCollector Throwable import**: Added missing `use Throwable;` import statement
- Fixes "Class 'Nowo\PerformanceBundle\DataCollector\Throwable' not found" error
- Resolves ReflectionException during container compilation

#### What This Means

- **No configuration changes required**: This is a code-only fix
- **No database changes required**: No schema changes
- **Fixes fatal error**: Resolves the error that prevented the application from starting

#### Migration Steps

1. **Update the bundle**:
   ```bash
   composer update nowo-tech/performance-bundle
   ```

2. **Clear cache**:
   ```bash
   php bin/console cache:clear
   ```

3. **Verify installation**:
   ```bash
   php bin/console nowo:performance:diagnose
   ```

#### Troubleshooting

**Issue: Still seeing the Throwable error**

- **Solution**: Clear Symfony cache and verify the bundle version:
  ```bash
  php bin/console cache:clear
  composer show nowo-tech/performance-bundle
  ```
  Make sure you're using version 0.0.4 or higher.

## Upgrading to 0.0.3 (2025-01-27)

### Compatibility Fix

This version fixes a compatibility issue with DoctrineBundle middleware configuration.

#### Changes

- **DoctrineBundle middleware configuration**: Changed from using `yamlMiddleware` to always using `middlewares` for DoctrineBundle 2.x
- The `yamlMiddleware` option is not reliably available across all DoctrineBundle 2.x versions, even when the version suggests it should be supported
- The `middlewares` option is more widely supported and works consistently across all DoctrineBundle 2.x versions

#### What This Means

- **No configuration changes required**: The bundle automatically uses the correct configuration method
- **No database changes required**: This is a configuration-only fix
- **Fixes installation errors**: Resolves "Unrecognized option 'yamlMiddleware'" errors when installing the bundle

#### Migration Steps

1. **Update the bundle**:
   ```bash
   composer update nowo-tech/performance-bundle
   ```

2. **Clear cache**:
   ```bash
   php bin/console cache:clear
   ```

3. **Verify installation**:
   ```bash
   php bin/console nowo:performance:diagnose
   ```

#### Troubleshooting

**Issue: Still seeing "Unrecognized option 'yamlMiddleware'" error**

- **Solution**: Clear Symfony cache and verify the bundle version:
  ```bash
  php bin/console cache:clear
  composer show nowo-tech/performance-bundle
  ```
  Make sure you're using version 0.0.3 or higher.

**Issue: Middleware not being registered**

- **Solution**: Run the diagnose command to check middleware registration:
  ```bash
  php bin/console nowo:performance:diagnose
  ```
  The command will show which method is being used to register the middleware.

## Upgrading to 0.0.2 (2025-01-27)

### New Features Overview

This version adds HTTP status code tracking, performance alert notifications, sampling for high-traffic routes, configurable query tracking threshold, auto-refresh dashboard, and comprehensive test coverage improvements.

### Database Schema Changes

**IMPORTANT**: You need to update your database schema to include the new `status_codes` field.

#### New Field Added

- `status_codes` (JSON, nullable) - HTTP status codes counts (e.g., {'200': 100, '404': 5, '500': 2})

#### Migration Steps

**Option 1: Using the Bundle Command (Recommended)**

```bash
# Add missing columns without losing data
# Also fixes AUTO_INCREMENT if missing (handles foreign keys automatically)
php bin/console nowo:performance:create-table --update
```

**Note**: This command also automatically fixes the `id` column AUTO_INCREMENT if it's missing, even if there are foreign key constraints. The command temporarily drops and restores foreign keys during the fix.

**Option 2: Using Doctrine Migrations (Recommended for Production)**

```bash
# Generate migration
php bin/console doctrine:migrations:diff

# Review the migration file
# Then apply it
php bin/console doctrine:migrations:migrate
```

**Option 3: Manual SQL**

```sql
ALTER TABLE routes_data 
  ADD COLUMN status_codes JSON NULL;
```

### New Configuration Options

#### HTTP Status Code Tracking

Configure which HTTP status codes to track and calculate ratios for:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    # Status codes to track (default: 200, 404, 500, 503)
    track_status_codes: [200, 404, 500, 503]
```

**Use cases:**
- Monitor success rates (200 vs errors)
- Track error rates (404, 500, 503)
- Identify problematic routes

**Example configuration:**
```yaml
nowo_performance:
    track_status_codes: [200, 201, 400, 404, 500, 503]
```

#### Performance Notifications

Enable automatic notifications when performance thresholds are exceeded:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    notifications:
        enabled: true
        email:
            enabled: true
            from: 'noreply@example.com'
            to: ['admin@example.com']
        slack:
            enabled: true
            webhook_url: 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL'
```

**See [NOTIFICATIONS.md](NOTIFICATIONS.md) for complete documentation.**

#### Sampling Rate

Reduce database load for frequently accessed routes by tracking only a percentage of requests:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    # Sampling rate: 0.0 to 1.0 (where 1.0 = 100% tracking)
    # Example: 0.1 = only track 10% of requests
    sampling_rate: 1.0  # Default: 1.0 (track all requests)
```

**Use cases:**
- High-traffic production environments
- Routes with thousands of requests per minute
- When database write performance is a concern

**Example configuration for production:**
```yaml
nowo_performance:
    sampling_rate: 0.1  # Track only 10% of requests
```

#### Query Tracking Threshold

Configure minimum query count to track query execution time:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    # Minimum query count to track query execution time
    # Queries below this threshold are counted but not timed individually
    query_tracking_threshold: 0  # Default: 0 (track all queries)
```

**Use cases:**
- Reduce overhead for routes with very few queries
- Focus timing on routes with significant query activity

**Example configuration:**
```yaml
nowo_performance:
    query_tracking_threshold: 5  # Only time queries if route has 5+ queries
```

#### Auto-Refresh Dashboard

Enable automatic dashboard refresh with visual countdown:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    dashboard:
        # Auto-refresh interval in seconds (0 = disabled)
        auto_refresh_interval: 30  # Default: 0 (disabled)
```

**Features:**
- Visual countdown indicator
- Automatically pauses when window loses focus
- Improves real-time monitoring experience

**Example configuration:**
```yaml
nowo_performance:
    dashboard:
        auto_refresh_interval: 30  # Refresh every 30 seconds
```

### Migration Steps

1. **Update database schema** (if upgrading from 0.0.1):
   ```bash
   php bin/console nowo:performance:create-table --update
   ```

2. **Update configuration** (optional):

2. **Update configuration** (optional):
   ```yaml
   # config/packages/nowo_performance.yaml
   nowo_performance:
       track_status_codes: [200, 404, 500, 503]
       sampling_rate: 1.0
       query_tracking_threshold: 0
       dashboard:
           auto_refresh_interval: 0
       notifications:
           enabled: false  # Enable if you want notifications
   ```

3. **Clear cache**:
   ```bash
   php bin/console cache:clear
   ```

### Breaking Changes

- **None** - All new features are backward compatible

### Testing Your Upgrade

1. **Verify database schema**:
   ```bash
   php bin/console nowo:performance:diagnose
   # Should show status_codes column exists
   ```

2. **Verify configuration**:
   ```bash
   php bin/console debug:container --parameter=nowo_performance.track_status_codes
   php bin/console debug:container --parameter=nowo_performance.sampling_rate
   php bin/console debug:container --parameter=nowo_performance.query_tracking_threshold
   php bin/console debug:container --parameter=nowo_performance.dashboard.auto_refresh_interval
   ```

3. **Test status code tracking**:
   - Make requests to routes with different status codes (200, 404, 500)
   - Check dashboard to see status code ratios displayed
   - Verify ratios are calculated correctly

4. **Test notifications** (if enabled):
   - Set low thresholds temporarily
   - Make a request that exceeds thresholds
   - Verify notifications are sent

5. **Test sampling**:
   - Set `sampling_rate: 0.5` (track 50% of requests)
   - Make multiple requests to the same route
   - Verify that approximately 50% are recorded

6. **Test auto-refresh**:
   - Set `auto_refresh_interval: 30`
   - Open dashboard and verify countdown appears
   - Verify dashboard refreshes after 30 seconds

### Troubleshooting

**Issue: Error "Field 'id' doesn't have a default value"**

- **Cause**: The `id` column in the `routes_data` table is missing AUTO_INCREMENT
- **Solution**: Run the update command to fix it automatically:
  ```bash
  php bin/console nowo:performance:create-table --update
  ```
  This command will:
  - Detect missing AUTO_INCREMENT on the `id` column
  - Temporarily drop foreign keys that reference the `id` column
  - Add AUTO_INCREMENT to the `id` column
  - Restore all foreign keys with their original rules
- **Note**: If you have foreign keys referencing the `id` column (e.g., from `routes_data_records` table), the command handles them automatically

**Issue: Status codes not being tracked**

- **Solution**: Verify the column exists and configuration is correct:
  ```bash
  php bin/console nowo:performance:create-table --update
  php bin/console debug:container --parameter=nowo_performance.track_status_codes
  ```

**Issue: Notifications not working**

- **Solution**: Verify configuration and dependencies:
  ```bash
  php bin/console debug:container --parameter=nowo_performance.notifications.enabled
  # For email: composer require symfony/mailer
  # For webhooks: composer require symfony/http-client
  ```

**Issue: Sampling not working**

- **Solution**: Clear cache and verify configuration is loaded:
  ```bash
  php bin/console cache:clear
  php bin/console debug:container --parameter=nowo_performance.sampling_rate
  ```

**Issue: Auto-refresh not working**

- **Solution**: Verify JavaScript is enabled and check browser console for errors

## Upgrading to 0.0.1 (2025-01-26)

### New Features Overview

This version adds significant new features including memory tracking, access frequency, record management, review system, data export, and enhanced Web Profiler integration.

### Database Schema Changes

**IMPORTANT**: You need to update your database schema to include the new fields.

#### New Fields Added

- `memory_usage` (BIGINT, nullable) - Peak memory usage in bytes
- `access_count` (INTEGER, default: 1) - Number of times route was accessed
- `last_accessed_at` (DATETIME_IMMUTABLE, nullable) - Last access timestamp
- `reviewed` (BOOLEAN, default: false) - Whether record has been reviewed
- `reviewed_at` (DATETIME_IMMUTABLE, nullable) - Review timestamp
- `queries_improved` (BOOLEAN, nullable) - Whether queries improved after review
- `time_improved` (BOOLEAN, nullable) - Whether time improved after review
- `reviewed_by` (STRING, nullable) - Username of reviewer

#### Migration Steps

**Option 1: Using the Bundle Command (Recommended)**

```bash
# Drop and recreate the table (WARNING: This will delete all data)
php bin/console nowo:performance:create-table --force
```

**Option 2: Using Doctrine Migrations (Recommended for Production)**

```bash
# Generate migration
php bin/console doctrine:migrations:diff

# Review the migration file
# Then apply it
php bin/console doctrine:migrations:migrate
```

**Option 3: Manual SQL**

If you prefer to manually update the schema:

```sql
ALTER TABLE routes_data 
  ADD COLUMN memory_usage BIGINT NULL,
  ADD COLUMN access_count INTEGER NOT NULL DEFAULT 1,
  ADD COLUMN last_accessed_at DATETIME_IMMUTABLE NULL,
  ADD COLUMN reviewed BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN reviewed_at DATETIME_IMMUTABLE NULL,
  ADD COLUMN queries_improved BOOLEAN NULL,
  ADD COLUMN time_improved BOOLEAN NULL,
  ADD COLUMN reviewed_by VARCHAR(255) NULL;

-- Add indexes
CREATE INDEX idx_route_env_access_count ON routes_data(env, access_count);
CREATE INDEX idx_route_reviewed ON routes_data(reviewed);
CREATE INDEX idx_route_reviewed_at ON routes_data(reviewed_at);
```

### New Configuration Options

#### Record Management

Enable individual record deletion from the dashboard:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    dashboard:
        enable_record_management: true  # Default: false
```

#### Review System

Enable the record review system:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    dashboard:
        enable_review_system: true  # Default: false
```

**Note**: Both features are disabled by default for backward compatibility. Enable them explicitly if needed.

### New Commands

Three new commands are available:

1. **`nowo:performance:create-table`** - Create the database table
   ```bash
   php bin/console nowo:performance:create-table
   php bin/console nowo:performance:create-table --force  # Drop and recreate
   ```

2. **`nowo:performance:diagnose`** - Diagnostic information
   ```bash
   php bin/console nowo:performance:diagnose
   ```

3. **`nowo:performance:check-dependencies`** - Check optional dependencies
   ```bash
   php bin/console nowo:performance:check-dependencies
   ```

### Enhanced Command: `nowo:performance:set-route`

The `set-route` command now supports memory usage:

```bash
php bin/console nowo:performance:set-route app_home \
    --request-time=0.5 \
    --queries=10 \
    --memory=1048576  # New option: memory in bytes
```

### Dashboard Changes

#### New Features

- **Export buttons** - CSV and JSON export in dashboard header
- **Clear All Records** - Button to clear all records (with referer redirect)
- **Access Count column** - Shows how many times each route was accessed
- **Last Accessed At column** - Shows last access timestamp
- **Review Status column** - Shows review status and improvement indicators
- **Action buttons** - Delete and review buttons (when enabled)

#### Breaking Changes

- **None** - All new features are opt-in via configuration

### Web Profiler Integration

The PerformanceDataCollector now shows:
- Access frequency (how many times route was accessed)
- Ranking by request time (position among all routes)
- Ranking by query count (position among all routes)
- Total routes count

This information is automatically collected if the route exists in the database.

### Optional Dependencies

The bundle now optionally uses Symfony UX TwigComponent for better performance. If not installed, it falls back to traditional Twig includes.

To install (optional):
```bash
composer require symfony/ux-twig-component
```

### Testing Your Upgrade

1. **Verify database schema**:
   ```bash
   php bin/console nowo:performance:diagnose
   ```

2. **Check table structure**:
   ```bash
   php bin/console doctrine:schema:validate
   ```

3. **Test dashboard**:
   - Access `/performance` (or your configured path)
   - Verify new columns are visible
   - Test export functionality
   - Test record management (if enabled)

4. **Test commands**:
   ```bash
   php bin/console nowo:performance:create-table --help
   php bin/console nowo:performance:diagnose
   php bin/console nowo:performance:check-dependencies
   ```

### Troubleshooting

**Issue: Missing columns in database**

- **Solution**: Run the migration or use `nowo:performance:create-table --force`

**Issue: Export buttons not visible**

- **Solution**: Clear Symfony cache: `php bin/console cache:clear`

**Issue: Review system not working**

- **Solution**: Enable it in configuration: `enable_review_system: true`

**Issue: Query tracking not working**

- **Solution**: Run `php bin/console nowo:performance:diagnose` to check configuration

## Upgrading to 0.0.1 (2025-01-26)

### Initial Release

This is the first stable release of the Performance Bundle. No upgrade steps needed if you're installing for the first time.

#### Installation

If you're installing the bundle for the first time, follow the [Installation Guide](INSTALLATION.md).

#### Key Features

This initial release includes:

- Automatic route performance tracking
- Database query counting and timing
- Request execution time measurement
- HTTP method tracking (GET, POST, PUT, DELETE, etc.)
- Memory usage tracking
- Access frequency tracking
- Performance dashboard with filtering and sorting
- WebProfiler integration
- CSV and JSON export
- Record management and review system (optional)
- Role-based access control
- Advanced Performance Statistics page
- Sampling for high-traffic routes
- Configurable query tracking threshold
- Auto-refresh dashboard
- Symfony 6.1+, 7.x, and 8.x compatibility

#### Database Setup

After installation, create the database table:

```bash
php bin/console nowo:performance:create-table
```

Or use Doctrine migrations:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```
