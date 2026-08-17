<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration;

/**
 * Dashboard enabled but requires ROLE_ADMIN (anonymous user → AccessDenied).
 */
final class TestKernelDashboardRoleAdmin extends TestKernel
{
    protected function performanceConfigFile(): string
    {
        return 'nowo_performance_dashboard_role_admin.yaml';
    }
}
