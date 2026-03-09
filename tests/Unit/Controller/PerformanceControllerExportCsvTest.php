<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Exception;
use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unit tests for PerformanceController::exportCsv() when getRoutesWithAggregatesFiltered throws.
 */
final class PerformanceControllerExportCsvTest extends TestCase
{
    private MockObject&PerformanceMetricsService $metricsService;
    private MockObject&RouteDataRepository $repository;

    protected function setUp(): void
    {
        $this->repository     = $this->createMock(RouteDataRepository::class);
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->metricsService->method('getRepository')->willReturn($this->repository);
    }

    /** When getRoutesWithAggregatesFiltered throws inside the stream callback, exportCsv uses empty routes. */
    public function testExportCsvWhenGetRoutesWithAggregatesFilteredThrowsReturnsEmptyCsv(): void
    {
        $this->metricsService
            ->method('getRoutesWithAggregatesFiltered')
            ->willThrowException(new Exception('DB error'));

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
                null,
                null,
                false,
                false,
                null,
                0.5,
                1.0,
                20,
                50,
                20.0,
                50.0,
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                0,
                [200, 404, 500, 503],
                null,
                false,
                true,
                ['dev', 'test'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
                true,
                null,
            ])
            ->onlyMethods(['getParameter'])
            ->getMock();

        $controller->method('getParameter')->with('kernel.environment')->willReturn('test');

        $request  = Request::create('/performance/export/csv', 'GET', ['env' => 'test']);
        $response = $controller->exportCsv($request);

        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());

        ob_start();
        $response->sendContent();
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString('Route Name', $output);
    }
}
