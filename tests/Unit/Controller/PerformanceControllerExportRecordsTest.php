<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Tests for PerformanceController export records (CSV / JSON) methods.
 */
final class PerformanceControllerExportRecordsTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;
    private RouteDataRecordRepository|MockObject $recordRepository;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->recordRepository = $this->createMock(RouteDataRecordRepository::class);
    }

    private function createController(
        bool $enabled = true,
        bool $enableAccessRecords = true,
        ?RouteDataRecordRepository $recordRepository = null,
        array $requiredRoles = [],
        bool $withRecordRepository = true,
    ): PerformanceController {
        $repo = $withRecordRepository ? ($recordRepository ?? $this->recordRepository) : null;

        return new PerformanceController(
            $this->metricsService,
            null,
            $enabled,
            $requiredRoles,
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
            $repo,
            $enableAccessRecords,
            true,
            ['dev', 'test', 'prod'],
            'default',
            true,
            true,
            false,
            [],
            false,
            1.0,
            true,
        );
    }

    public function testExportRecordsCsvThrowsWhenDashboardDisabled(): void
    {
        $controller = $this->createController(enabled: false);
        $request = new Request();
        $request->query->set('env', 'dev');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Temporal access records are disabled.');

        $controller->exportRecordsCsv($request);
    }

    public function testExportRecordsCsvThrowsWhenAccessRecordsDisabled(): void
    {
        $controller = $this->createController(enableAccessRecords: false);
        $request = new Request();
        $request->query->set('env', 'dev');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Temporal access records are disabled.');

        $controller->exportRecordsCsv($request);
    }

    public function testExportRecordsCsvThrowsWhenRecordRepositoryNull(): void
    {
        $controller = $this->createController(enableAccessRecords: true, withRecordRepository: false);
        $request = new Request();
        $request->query->set('env', 'dev');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('RouteDataRecordRepository is not available.');

        $controller->exportRecordsCsv($request);
    }

    public function testExportRecordsCsvThrowsAccessDeniedWhenUserLacksRoles(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                ['ROLE_ADMIN'],
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
                $this->recordRepository,
                true,
                true,
                ['dev', 'test', 'prod'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['isGranted', 'createAccessDeniedException'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $controller->expects(self::once())->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);
        $controller->expects(self::once())
            ->method('createAccessDeniedException')
            ->with('You do not have permission to export access records.')
            ->willReturn(new AccessDeniedException('You do not have permission to export access records.'));

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You do not have permission to export access records.');

        $controller->exportRecordsCsv($request);
    }

    public function testExportRecordsCsvReturnsStreamedResponseWithRecords(): void
    {
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
                $this->recordRepository,
                true,
                true,
                ['dev', 'test', 'prod'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['getParameter'])
            ->getMock();

        $controller->method('getParameter')->willReturnCallback(static fn (string $k) => $k === 'kernel.environment' ? 'dev' : null);

        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $record = new RouteDataRecord();
        $record->setRouteData($routeData);
        $record->setAccessedAt(new \DateTimeImmutable('2026-01-15 12:00:00'));
        $record->setStatusCode(200);
        $record->setResponseTime(0.1);
        $record->setTotalQueries(5);
        $record->setQueryTime(0.05);
        $record->setMemoryUsage(1024);

        $this->recordRepository
            ->expects(self::once())
            ->method('getRecordsForExport')
            ->with('dev', null, null, null, null)
            ->willReturn(['records' => [$record], 'total' => 1]);

        $request = new Request();
        $request->query->set('env', 'dev');

        $response = $controller->exportRecordsCsv($request);

        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('performance_access_records', $response->headers->get('Content-Disposition'));
    }

    public function testExportRecordsCsvHandlesRepositoryException(): void
    {
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
                $this->recordRepository,
                true,
                true,
                ['dev', 'test', 'prod'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['getParameter'])
            ->getMock();

        $controller->method('getParameter')->willReturnCallback(static fn (string $k) => $k === 'kernel.environment' ? 'dev' : null);

        $this->recordRepository
            ->expects(self::once())
            ->method('getRecordsForExport')
            ->willThrowException(new \RuntimeException('DB error'));

        $request = new Request();
        $request->query->set('env', 'dev');

        $response = $controller->exportRecordsCsv($request);

        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function testExportRecordsCsvHandlesEmptyRecords(): void
    {
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
                $this->recordRepository,
                true,
                true,
                ['dev', 'test', 'prod'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['getParameter'])
            ->getMock();

        $controller->method('getParameter')->willReturnCallback(static fn (string $k) => $k === 'kernel.environment' ? 'dev' : null);

        $this->recordRepository->method('getRecordsForExport')->willReturn(['records' => [], 'total' => 0]);

        $request = new Request();
        $request->query->set('env', 'dev');

        $response = $controller->exportRecordsCsv($request);

        self::assertInstanceOf(StreamedResponse::class, $response);
    }

    public function testExportRecordsJsonThrowsWhenAccessRecordsDisabled(): void
    {
        $controller = $this->createController(enableAccessRecords: false);
        $request = new Request();
        $request->query->set('env', 'dev');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Temporal access records are disabled.');

        $controller->exportRecordsJson($request);
    }

    public function testExportRecordsJsonReturnsJsonWithRecords(): void
    {
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
                $this->recordRepository,
                true,
                true,
                ['dev', 'test', 'prod'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['getParameter'])
            ->getMock();

        $controller->method('getParameter')->willReturnCallback(static fn (string $k) => $k === 'kernel.environment' ? 'dev' : null);

        $routeData = new RouteData();
        $routeData->setName('api_foo');
        $routeData->setEnv('prod');

        $record = new RouteDataRecord();
        $record->setRouteData($routeData);
        $record->setAccessedAt(new \DateTimeImmutable('2026-01-20 14:30:00'));
        $record->setStatusCode(201);
        $record->setResponseTime(0.2);
        $record->setTotalQueries(10);
        $record->setQueryTime(0.08);
        $record->setMemoryUsage(2048);

        $this->recordRepository
            ->expects(self::once())
            ->method('getRecordsForExport')
            ->with('prod', null, null, null, null)
            ->willReturn(['records' => [$record], 'total' => 1]);

        $request = new Request();
        $request->query->set('env', 'prod');

        $response = $controller->exportRecordsJson($request);

        self::assertInstanceOf(Response::class, $response);
        self::assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        self::assertStringContainsString('performance_access_records', $response->headers->get('Content-Disposition'));

        $data = json_decode($response->getContent(), true);
        self::assertIsArray($data);
        self::assertArrayHasKey('environment', $data);
        self::assertArrayHasKey('exported_at', $data);
        self::assertArrayHasKey('total_records', $data);
        self::assertArrayHasKey('total_matching', $data);
        self::assertArrayHasKey('data', $data);
        self::assertSame('prod', $data['environment']);
        self::assertSame(1, $data['total_records']);
        self::assertSame(1, $data['total_matching']);
        self::assertCount(1, $data['data']);
        self::assertSame('api_foo', $data['data'][0]['route_name']);
        self::assertSame('prod', $data['data'][0]['environment']);
        self::assertSame(201, $data['data'][0]['status_code']);
        self::assertSame(0.2, $data['data'][0]['response_time']);
    }

    public function testExportRecordsJsonHandlesRepositoryException(): void
    {
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
                $this->recordRepository,
                true,
                true,
                ['dev', 'test', 'prod'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['getParameter'])
            ->getMock();

        $controller->method('getParameter')->willReturnCallback(static fn (string $k) => $k === 'kernel.environment' ? 'dev' : null);

        $this->recordRepository->method('getRecordsForExport')->willThrowException(new \RuntimeException('DB error'));

        $request = new Request();
        $request->query->set('env', 'dev');

        $response = $controller->exportRecordsJson($request);

        self::assertInstanceOf(Response::class, $response);
        $data = json_decode($response->getContent(), true);
        self::assertSame(0, $data['total_records']);
        self::assertSame(0, $data['total_matching']);
        self::assertSame([], $data['data']);
    }

    public function testExportRecordsJsonPassesFiltersFromRequest(): void
    {
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
                $this->recordRepository,
                true,
                true,
                ['dev', 'test', 'prod'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['getParameter'])
            ->getMock();

        $controller->method('getParameter')->willReturnCallback(static fn (string $k) => $k === 'kernel.environment' ? 'dev' : null);

        $this->recordRepository
            ->expects(self::once())
            ->method('getRecordsForExport')
            ->with(
                'test',
                self::callback(static fn ($v) => $v instanceof \DateTimeImmutable && $v->format('Y-m-d') === '2026-01-01'),
                self::callback(static fn ($v) => $v instanceof \DateTimeImmutable && $v->format('Y-m-d') === '2026-01-31'),
                'app_user',
                404
            )
            ->willReturn(['records' => [], 'total' => 0]);

        $request = new Request();
        $request->query->set('env', 'test');
        $request->query->set('start_date', '2026-01-01');
        $request->query->set('end_date', '2026-01-31');
        $request->query->set('route', 'app_user');
        $request->query->set('status_code', '404');

        $response = $controller->exportRecordsJson($request);

        self::assertInstanceOf(Response::class, $response);
        $data = json_decode($response->getContent(), true);
        self::assertSame(0, $data['total_records']);
    }
}
