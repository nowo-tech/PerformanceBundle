<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Controller;

use Nowo\PerformanceBundle\Tests\Integration\TestKernelDashboardRoleAdmin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Covers controller branches when dashboard.roles requires ROLE_ADMIN and the user is anonymous.
 */
final class PerformanceControllerDashboardAccessDeniedIntegrationTest extends TestCase
{
    private KernelInterface $kernel;

    protected function setUp(): void
    {
        $this->kernel = new TestKernelDashboardRoleAdmin('test', true);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public static function dashboardGetPathsProvider(): array
    {
        return [
            'index'               => ['/performance'],
            'statistics'          => ['/performance/statistics'],
            'export_csv'          => ['/performance/export/csv'],
            'export_json'         => ['/performance/export/json'],
            'export_records_csv'  => ['/performance/export/records/csv'],
            'export_records_json' => ['/performance/export/records/json'],
            'chart_data'          => ['/performance/api/chart-data'],
            'diagnose'            => ['/performance/diagnose'],
            'access_statistics'   => ['/performance/access-statistics'],
            'access_records'      => ['/performance/access-records'],
        ];
    }

    #[DataProvider('dashboardGetPathsProvider')]
    public function testDashboardRequiresRoleAdminAndAnonymousIsDenied(string $path): void
    {
        $response = $this->handleGetFollowingRedirects($path);

        self::assertContains($response->getStatusCode(), [401, 403], 'Anonymous user without ROLE_ADMIN should receive 401 or 403');
    }

    private function handleGetFollowingRedirects(string $uri): \Symfony\Component\HttpFoundation\Response
    {
        $request  = Request::create($uri, Request::METHOD_GET);
        $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
        $guard    = 0;
        while ($response->isRedirection() && $guard++ < 5) {
            $location = $response->headers->get('Location');
            self::assertNotNull($location);
            $request  = Request::create($location, Request::METHOD_GET);
            $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
        }

        return $response;
    }
}
