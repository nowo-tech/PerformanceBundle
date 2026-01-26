# Upgrading Guide

This guide helps you upgrade between versions of the Performance Bundle.

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

This is the first release. No upgrade steps needed.
