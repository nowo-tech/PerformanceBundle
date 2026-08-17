<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration;

/**
 * Same as TestKernel but with the performance dashboard disabled (covers controller 403/404 branches).
 */
final class TestKernelDashboardDisabled extends TestKernel
{
    protected function performanceConfigFile(): string
    {
        return 'nowo_performance_dashboard_disabled.yaml';
    }
}
