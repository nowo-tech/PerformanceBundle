# Upgrading Guide

This guide helps you upgrade between versions of the Performance Bundle.

## Upgrading to 0.0.3 (Unreleased)

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

## Upgrading to 0.0.2 (Unreleased)

### New Feature: Improved Query Tracking with QueryTrackingMiddleware

The bundle now includes a custom DBAL middleware (`QueryTrackingMiddleware`) for more reliable query tracking, especially with DBAL 3.x compatibility.

#### What Changed?

- Added `QueryTrackingMiddleware` for DBAL 3.x compatibility
- Enhanced query metrics collection with multiple fallback strategies
- Improved reliability of query tracking across different Symfony/Doctrine versions

#### Migration Steps

**No action required** - This is a backward-compatible improvement. The bundle will automatically use the new middleware if available, or fall back to previous methods.

**Optional: Manual Middleware Registration**

If you want to explicitly register the middleware in your Doctrine configuration:

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        connections:
            default:
                middlewares:
                    - Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware
```

However, this is **not required** - the bundle will work without it using fallback methods.

### New Feature: Role-Based Access Control for Dashboard

The performance dashboard now supports role-based access control. This is a **non-breaking change** - existing installations will continue to work without modification.

#### What Changed?

- Added `roles` configuration option to the dashboard configuration
- The dashboard now checks user roles before allowing access
- If no roles are configured (default), access remains unrestricted

#### Migration Steps

**Option 1: Keep unrestricted access (no changes needed)**

If you want to keep the dashboard accessible to everyone, no changes are required. The default configuration (`roles: []`) allows unrestricted access.

**Option 2: Add role restrictions**

To restrict dashboard access to specific roles, update your configuration:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    # ... existing configuration ...
    dashboard:
        enabled: true
        path: '/performance'
        prefix: ''
        roles: ['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER']  # Add this line
```

#### Configuration Examples

**Example 1: Restrict to administrators only**

```yaml
nowo_performance:
    dashboard:
        roles: ['ROLE_ADMIN']
```

**Example 2: Allow multiple roles**

```yaml
nowo_performance:
    dashboard:
        roles: ['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER', 'ROLE_MONITORING']
```

Users with **any** of the configured roles will have access.

**Example 3: Keep unrestricted access**

```yaml
nowo_performance:
    dashboard:
        roles: []  # Empty array = no restrictions
```

Or simply omit the `roles` key (defaults to empty array).

#### Breaking Changes

- **None** - This is a backward-compatible addition

#### Testing Your Upgrade

1. **Verify unrestricted access still works** (if you didn't add roles):
   ```bash
   # Access the dashboard without authentication
   curl http://localhost:8000/performance
   ```

2. **Test role-based access** (if you added roles):
   ```bash
   # As a user with ROLE_ADMIN
   curl -u admin:password http://localhost:8000/performance
   
   # As a user without required roles (should return 403)
   curl -u user:password http://localhost:8000/performance
   ```

3. **Check configuration**:
   ```bash
   php bin/console debug:container --parameter=nowo_performance.dashboard.roles
   ```

#### Troubleshooting

**Issue: Dashboard returns 403 Forbidden**

- **Cause**: You have configured roles but the current user doesn't have any of them
- **Solution**: 
  - Add the required role to your user
  - Or remove/empty the `roles` configuration to allow unrestricted access

**Issue: Dashboard still accessible without authentication**

- **Cause**: You have `roles: []` or didn't configure roles
- **Solution**: This is expected behavior. To restrict access, configure specific roles

**Issue: Configuration not recognized**

- **Cause**: Cache not cleared
- **Solution**: Clear Symfony cache:
  ```bash
  php bin/console cache:clear
  ```

## Upgrading to 0.0.1

### Initial Release

This is the first release of the Performance Bundle. No upgrade steps needed.

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
