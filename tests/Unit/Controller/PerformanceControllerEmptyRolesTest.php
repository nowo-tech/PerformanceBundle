<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Wiring without an access checker (BC fallback): an empty role list must deny, never open the dashboard.
 */
final class PerformanceControllerEmptyRolesTest extends TestCase
{
    public function testIndexDeniesWhenRoleListIsEmptyAndNoCheckerIsWired(): void
    {
        $metricsService = $this->createMock(PerformanceMetricsService::class);
        $metricsService->expects(self::never())->method(self::anything());

        $controller = new PerformanceController($metricsService, null, true, []);

        $this->expectException(AccessDeniedException::class);

        $controller->index(new Request());
    }
}
