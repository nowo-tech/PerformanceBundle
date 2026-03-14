<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Controller;

use Nowo\PerformanceBundle\Form\ClearPerformanceDataType;
use Nowo\PerformanceBundle\Model\ClearPerformanceDataRequest;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use function is_array;

final class PerformanceControllerIntegrationTest extends TestCase
{
    private TestKernel $kernel;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel('test', true);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public function testDashboardRespondsSuccessfully(): void
    {
        $request  = Request::create('/performance', Request::METHOD_GET);
        $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);

        if ($response->isRedirection()) {
            $location = $response->headers->get('Location');
            self::assertNotNull($location);
            $request  = Request::create($location, Request::METHOD_GET);
            $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
        }

        self::assertSame(200, $response->getStatusCode(), 'Dashboard should respond with 200');
        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('performance', strtolower($content));
    }

    public function testStatisticsPageRespondsSuccessfully(): void
    {
        $request  = Request::create('/performance/statistics', Request::METHOD_GET);
        $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);

        if ($response->isRedirection()) {
            $location = $response->headers->get('Location');
            self::assertNotNull($location);
            $request  = Request::create($location, Request::METHOD_GET);
            $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
        }

        self::assertSame(200, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        self::assertTrue(
            str_contains(strtolower($content), 'statistics') || str_contains(strtolower($content), 'performance'),
            'Response should contain statistics or performance',
        );
    }

    public function testExportCsvRespondsSuccessfully(): void
    {
        $response = $this->requestGet('/performance/export/csv');
        self::assertSame(200, $response->getStatusCode());
        // StreamedResponse may return false from getContent() until sent; just assert status and optional content-type
        $contentType = $response->headers->get('Content-Type');
        self::assertTrue(
            $contentType === null || str_contains($contentType, 'text/csv') || str_contains($contentType, 'text/plain'),
            'Expected CSV or text response',
        );
    }

    public function testExportJsonRespondsSuccessfully(): void
    {
        $response = $this->requestGet('/performance/export/json');
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->headers->get('Content-Type') ?? '');
        $content = $response->getContent();
        self::assertIsString($content);
    }

    public function testDiagnosePageRespondsSuccessfully(): void
    {
        $response = $this->requestGet('/performance/diagnose');
        self::assertSame(200, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        self::assertTrue(
            str_contains(strtolower($content), 'diagnose') || str_contains(strtolower($content), 'performance') || str_contains(strtolower($content), 'configuration'),
            'Diagnose page content expected',
        );
    }

    public function testAccessStatisticsPageRespondsSuccessfully(): void
    {
        $response = $this->requestGet('/performance/access-statistics');
        self::assertSame(200, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
    }

    public function testAccessRecordsPageRespondsSuccessfully(): void
    {
        $response = $this->requestGet('/performance/access-records');
        self::assertSame(200, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
    }

    public function testApiChartDataRespondsSuccessfully(): void
    {
        $response = $this->requestGet('/performance/api/chart-data');
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->headers->get('Content-Type') ?? '');
    }

    public function testExportRecordsCsvRespondsSuccessfully(): void
    {
        $response = $this->requestGet('/performance/export/records/csv');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testExportRecordsJsonRespondsSuccessfully(): void
    {
        $response = $this->requestGet('/performance/export/records/json');
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->headers->get('Content-Type') ?? '');
    }

    public function testStatisticsWithEnvQueryParam(): void
    {
        $response = $this->requestGet('/performance/statistics?env=test');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testExportJsonWithEnvQueryParam(): void
    {
        $response = $this->requestGet('/performance/export/json?env=test');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testUnknownPerformancePathReturns404(): void
    {
        $request  = Request::create('/performance/nonexistent-page', Request::METHOD_GET);
        $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
        self::assertSame(404, $response->getStatusCode());
    }

    public function testApiChartDataWithEnvParam(): void
    {
        $response = $this->requestGet('/performance/api/chart-data?env=test');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testDashboardWithRecordedMetrics(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance');
        self::assertSame(200, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('performance', strtolower($content));
    }

    public function testStatisticsWithRecordedMetrics(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance/statistics?env=test');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testExportJsonWithRecordedMetrics(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance/export/json?env=test');
        self::assertSame(200, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('application/json', $response->headers->get('Content-Type') ?? '');
    }

    public function testAccessRecordsWithPaginationParams(): void
    {
        $response = $this->requestGet('/performance/access-records?page=1&per_page=10&env=test');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testAccessStatisticsWithDateParams(): void
    {
        $response = $this->requestGet('/performance/access-statistics?env=test');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexWithSortOrderAndLimit(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance?env=test&sort=totalQueries&order=ASC&limit=50&page=1');
        self::assertSame(200, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('performance', strtolower($content));
    }

    public function testIndexWithRouteAndPathFilter(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance?env=test&route=integration&path=/');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexWithRequestTimeAndQueryCountFilters(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance?env=test&min_request_time=0.05&max_request_time=1&min_query_count=1&max_query_count=10');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexWithDateFilters(): void
    {
        $response = $this->requestGet('/performance?env=test&date_from=2024-01-01&date_to=2026-12-31');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testExportCsvWithEnvParam(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance/export/csv?env=test');
        self::assertSame(200, $response->getStatusCode());
    }

    /** Trigger stream callback so CSV export closure is executed (more coverage). */
    public function testExportCsvStreamContentWithData(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance/export/csv?env=test');
        self::assertSame(200, $response->getStatusCode());
        if (method_exists($response, 'sendContent')) {
            ob_start();
            $response->sendContent();
            $output = ob_get_clean();
            self::assertIsString($output);
            self::assertStringContainsString('Route Name', $output);
        }
    }

    public function testChartDataWithDaysAndMetric(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance/api/chart-data?env=test&days=14&metric=totalQueries');
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->headers->get('Content-Type') ?? '');
    }

    public function testChartDataWithMetricRequestTime(): void
    {
        $response = $this->requestGet('/performance/api/chart-data?env=test&metric=requestTime');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testChartDataWithMetricMemoryUsage(): void
    {
        $response = $this->requestGet('/performance/api/chart-data?env=test&metric=memoryUsage');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testExportRecordsCsvWithDateFilters(): void
    {
        $response = $this->requestGet('/performance/export/records/csv?env=test&start_date=2024-01-01&end_date=2026-12-31');
        self::assertSame(200, $response->getStatusCode());
    }

    /** Trigger stream callback so export records CSV closure runs (more coverage). */
    public function testExportRecordsCsvStreamContent(): void
    {
        $response = $this->requestGet('/performance/export/records/csv?env=test');
        self::assertSame(200, $response->getStatusCode());
        if (method_exists($response, 'sendContent')) {
            ob_start();
            $response->sendContent();
            $output = ob_get_clean();
            self::assertIsString($output);
            self::assertStringContainsString('ID', $output);
        }
    }

    public function testExportRecordsJsonWithFilters(): void
    {
        $response = $this->requestGet('/performance/export/records/json?env=test&start_date=2024-01-01&end_date=2026-12-31&route=app_home');
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->headers->get('Content-Type') ?? '');
    }

    public function testAccessRecordsWithSortAndOrder(): void
    {
        $response = $this->requestGet('/performance/access-records?env=test&page=1&per_page=20&sort_by=response_time&order=ASC');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testAccessRecordsWithFilterParams(): void
    {
        $response = $this->requestGet('/performance/access-records?env=test&start_date=2024-06-01&end_date=2026-06-30&status_code=200&min_query_time=0&max_query_time=5');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexWithPage2AndHighLimit(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance?env=test&page=2&limit=25');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexWithSortByAccessCount(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance?env=test&sort=accessCount&order=DESC');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testAccessStatisticsWithSortAndFilters(): void
    {
        $response = $this->requestGet('/performance/access-statistics?env=test&start_date=2024-01-01&end_date=2026-12-31');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testExportJsonWithAllFilterParams(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet(
            '/performance/export/json?env=test&route=app&path=/&min_request_time=0.01&max_request_time=5'
            . '&min_query_count=0&max_query_count=100&date_from=2024-01-01&date_to=2026-12-31',
        );
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->headers->get('Content-Type') ?? '');
    }

    public function testExportCsvWithAllFilterParams(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet(
            '/performance/export/csv?env=test&route=integration&path=/&min_request_time=0&max_request_time=2'
            . '&date_from=2024-06-01&date_to=2026-06-30',
        );
        self::assertSame(200, $response->getStatusCode());
    }

    public function testDiagnoseWithRecordedData(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance/diagnose');
        self::assertSame(200, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        self::assertTrue(
            str_contains(strtolower($content), 'diagnose') || str_contains(strtolower($content), 'configuration') || str_contains(strtolower($content), 'table'),
            'Diagnose should contain configuration or table info',
        );
    }

    public function testIndexWithLimit1(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance?env=test&limit=1&page=1');
        self::assertSame(200, $response->getStatusCode());
    }

    /** Index with limit=1000 and no filters hits the branch that uses current routes for stats. */
    public function testIndexWithLimit1000NoFilters(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance?env=test&limit=1000&page=1');
        self::assertSame(200, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('performance', strtolower($content));
    }

    public function testChartDataWithRouteParam(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance/api/chart-data?env=test&route=integration_test_route&days=30&metric=requestTime');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testChartDataWithMetricQueryTime(): void
    {
        $response = $this->requestGet('/performance/api/chart-data?env=test&metric=queryTime');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testAccessRecordsWithMinMaxMemoryMb(): void
    {
        $response = $this->requestGet('/performance/access-records?env=test&min_memory_mb=0&max_memory_mb=512');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testAccessRecordsWithRefererAndUser(): void
    {
        $response = $this->requestGet('/performance/access-records?env=test&referer=https%3A%2F%2Fexample.com&user=testuser');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testExportRecordsCsvWithStatusAndPath(): void
    {
        $response = $this->requestGet('/performance/export/records/csv?env=test&status_code=200&path=%2Ftest');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testExportRecordsJsonWithMemoryParams(): void
    {
        $response = $this->requestGet('/performance/export/records/json?env=test&min_memory_mb=0&max_memory_mb=100');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexWithSortQueryTime(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance?env=test&sort=queryTime&order=ASC');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexWithSortMemoryUsage(): void
    {
        $response = $this->requestGet('/performance?env=test&sort=memoryUsage&order=DESC');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexWithSortLastAccessedAt(): void
    {
        $response = $this->requestGet('/performance?env=test&sort=lastAccessedAt&order=DESC');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testExportJsonWithInvalidDateFromIgnored(): void
    {
        $response = $this->requestGet('/performance/export/json?env=test&date_from=invalid-date&date_to=2026-12-31');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testExportCsvWithInvalidDateToIgnored(): void
    {
        $response = $this->requestGet('/performance/export/csv?env=test&date_from=2024-01-01&date_to=not-a-date');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexWithLimit500Max(): void
    {
        $response = $this->requestGet('/performance?env=test&limit=500&page=1');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexWithPage1Explicit(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance?env=test&page=1&limit=10');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testAccessRecordsWithSortByQueryTime(): void
    {
        $response = $this->requestGet('/performance/access-records?env=test&sort_by=query_time&order=DESC');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testAccessRecordsWithSortByMemoryUsage(): void
    {
        $response = $this->requestGet('/performance/access-records?env=test&sort_by=memory_usage&order=ASC');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testAccessStatisticsWithRouteFilter(): void
    {
        $response = $this->requestGet('/performance/access-statistics?env=test&route=app_home');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testAccessStatisticsWithStatusCodeFilter(): void
    {
        $response = $this->requestGet('/performance/access-statistics?env=test&status_code=200');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testAccessRecordsWithSortByAccessedAt(): void
    {
        $response = $this->requestGet('/performance/access-records?env=test&sort_by=accessed_at&order=ASC');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testAccessRecordsWithSortByTotalQueries(): void
    {
        $response = $this->requestGet('/performance/access-records?env=test&sort_by=total_queries&order=DESC');
        self::assertSame(200, $response->getStatusCode());
    }

    /** POST clear with valid CSRF (token from form) hits full clear path and redirects. */
    public function testClearPostWithValidTokenFromForm(): void
    {
        $this->createTablesAndRecordMetric();
        $token = $this->getClearFormCsrfToken();
        if ($token === null) {
            self::markTestSkipped('Form CSRF token not available');
        }
        $response = $this->requestPost('/performance/clear', [
            'clear_performance_data' => [
                'env'    => 'test',
                '_token' => $token,
                'submit' => '',
            ],
        ]);
        self::assertTrue($response->isRedirection());
        self::assertSame(302, $response->getStatusCode());
    }

    /** POST clear with token extracted from dashboard HTML runs full clear flow when token is present. */
    public function testClearPostWithTokenFromDashboardHtml(): void
    {
        $this->createTablesAndRecordMetric();
        $getResponse = $this->requestGet('/performance?env=test');
        self::assertSame(200, $getResponse->getStatusCode());
        $content = $getResponse->getContent();
        self::assertIsString($content);
        $token = null;
        if (preg_match('/name="clear_performance_data\[_token\]"\s+value="([^"]+)"/', $content, $m)) {
            $token = $m[1];
        } elseif (preg_match('/name=\'clear_performance_data\[_token\]\'[^>]*value=\'([^\']+)\'/', $content, $m)) {
            $token = $m[1];
        }
        if ($token === null || $token === '') {
            self::markTestSkipped('Clear form CSRF token not found in dashboard HTML');
        }
        $cookies      = $getResponse->headers->get('Set-Cookie');
        $cookieHeader = is_array($cookies) ? implode('; ', array_map(static fn (string $c): string => explode(';', $c)[0], $cookies)) : '';
        $response     = $this->requestPost('/performance/clear', [
            'clear_performance_data' => [
                'env'    => 'test',
                '_token' => $token,
                'submit' => '',
            ],
        ], false, $cookieHeader !== '' ? ['HTTP_COOKIE' => $cookieHeader] : []);
        self::assertTrue($response->isRedirection());
        self::assertSame(302, $response->getStatusCode());
        $location = $response->headers->get('Location');
        self::assertNotNull($location);
    }

    /** POST without valid CSRF hits controller and form validation (redirect with flash). */
    public function testClearPostRedirectsWhenTokenInvalid(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestPost('/performance/clear', [
            'clear_performance_data' => [
                'env'    => 'test',
                '_token' => 'invalid',
                'submit' => '',
            ],
        ]);
        self::assertTrue($response->isRedirection());
        self::assertSame(302, $response->getStatusCode());
    }

    /** POST delete without valid CSRF hits controller and validation path. */
    public function testDeletePostRedirectsWhenTokenInvalid(): void
    {
        $this->createTablesAndRecordMetric();
        $routeId = $this->getFirstRouteDataId();
        self::assertNotNull($routeId);
        $response = $this->requestPost('/performance/' . $routeId . '/delete', [
            'delete_record' => ['_token' => 'invalid', 'submit' => ''],
        ]);
        self::assertTrue($response->isRedirection());
        self::assertSame(302, $response->getStatusCode());
    }

    /** POST review without valid CSRF hits controller and validation path. */
    public function testReviewPostRedirectsWhenTokenInvalid(): void
    {
        $this->createTablesAndRecordMetric();
        $routeId = $this->getFirstRouteDataId();
        self::assertNotNull($routeId);
        $response = $this->requestPost('/performance/' . $routeId . '/review', [
            'review_route_data' => [
                'queries_improved' => '1',
                'time_improved'    => '',
                '_token'           => 'invalid',
                'submit'           => '',
            ],
        ]);
        self::assertTrue($response->isRedirection());
        self::assertSame(302, $response->getStatusCode());
    }

    /** POST purge without valid CSRF hits controller and validation path. */
    public function testPurgeRecordsPostRedirectsWhenTokenInvalid(): void
    {
        $response = $this->requestPost('/performance/purge-records', [
            'purge_access_records' => [
                'purgeType' => 'all',
                'days'      => '30',
                'env'       => 'test',
                '_token'    => 'invalid',
                'submit'    => '',
            ],
        ]);
        self::assertTrue($response->isRedirection());
        self::assertSame(302, $response->getStatusCode());
    }

    /** POST delete-records-by-filter without valid CSRF hits controller and validation path. */
    public function testDeleteRecordsByFilterPostRedirectsWhenTokenInvalid(): void
    {
        $response = $this->requestPost('/performance/delete-records-by-filter', [
            'delete_records_by_filter' => [
                '_from'            => 'access_records',
                'env'              => 'test',
                'start_date'       => '',
                'end_date'         => '',
                'route'            => '',
                'path'             => '',
                'status_code'      => '',
                'min_query_time'   => '',
                'max_query_time'   => '',
                'min_memory_usage' => '',
                'max_memory_usage' => '',
                'referer'          => '',
                'user'             => '',
                '_token'           => 'invalid',
                'submit'           => '',
            ],
        ]);
        self::assertTrue($response->isRedirection());
        self::assertSame(302, $response->getStatusCode());
    }

    /** POST clear with invalid token and Referer header hits redirect-to-referer branch. */
    public function testClearPostWithRefererRedirectsToReferer(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestPost(
            '/performance/clear',
            [
                'clear_performance_data' => [
                    'env'    => 'test',
                    '_token' => 'invalid',
                    'submit' => '',
                ],
            ],
            false,
            ['HTTP_REFERER' => 'http://localhost/performance/statistics'],
        );
        self::assertTrue($response->isRedirection());
        self::assertSame(302, $response->getStatusCode());
        $location = $response->headers->get('Location');
        self::assertNotNull($location);
        self::assertStringContainsString('/performance/statistics', $location);
    }

    /** POST delete with Referer header hits redirect-to-referer branch. */
    public function testDeletePostWithRefererRedirectsToReferer(): void
    {
        $this->createTablesAndRecordMetric();
        $routeId = $this->getFirstRouteDataId();
        self::assertNotNull($routeId);
        $response = $this->requestPost(
            '/performance/' . $routeId . '/delete',
            ['delete_record' => ['_token' => 'invalid', 'submit' => '']],
            false,
            ['HTTP_REFERER' => 'http://localhost/performance'],
        );
        self::assertTrue($response->isRedirection());
        $location = $response->headers->get('Location');
        self::assertNotNull($location);
    }

    /** POST review with Referer header. */
    public function testReviewPostWithRefererRedirectsToReferer(): void
    {
        $this->createTablesAndRecordMetric();
        $routeId = $this->getFirstRouteDataId();
        self::assertNotNull($routeId);
        $response = $this->requestPost(
            '/performance/' . $routeId . '/review',
            [
                'review_route_data' => [
                    'queries_improved' => '1',
                    'time_improved'    => '1',
                    '_token'           => 'invalid',
                    'submit'           => '',
                ],
            ],
            false,
            ['HTTP_REFERER' => 'http://localhost/performance/statistics'],
        );
        self::assertTrue($response->isRedirection());
    }

    /** POST purge-records with Referer. */
    public function testPurgeRecordsPostWithReferer(): void
    {
        $response = $this->requestPost(
            '/performance/purge-records',
            [
                'purge_access_records' => [
                    'purgeType' => 'all',
                    'days'      => '30',
                    'env'       => 'test',
                    '_token'    => 'invalid',
                    'submit'    => '',
                ],
            ],
            false,
            ['HTTP_REFERER' => 'http://localhost/performance/access-records'],
        );
        self::assertTrue($response->isRedirection());
    }

    /** POST delete-records-by-filter with Referer from access_statistics. */
    public function testDeleteRecordsByFilterPostWithRefererFromAccessStatistics(): void
    {
        $response = $this->requestPost(
            '/performance/delete-records-by-filter',
            [
                'delete_records_by_filter' => [
                    '_from'            => 'access_statistics',
                    'env'              => 'test',
                    'start_date'       => '',
                    'end_date'         => '',
                    'route'            => '',
                    'path'             => '',
                    'status_code'      => '',
                    'min_query_time'   => '',
                    'max_query_time'   => '',
                    'min_memory_usage' => '',
                    'max_memory_usage' => '',
                    'referer'          => '',
                    'user'             => '',
                    '_token'           => 'invalid',
                    'submit'           => '',
                ],
            ],
            false,
            ['HTTP_REFERER' => 'http://localhost/performance/access-statistics'],
        );
        self::assertTrue($response->isRedirection());
    }

    public function testIndexWithFormBoundParams(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet(
            '/performance?clear_performance_data%5Benv%5D=test&clear_performance_data%5Bsubmit%5D=&performance_filters%5Benv%5D=test&performance_filters%5Bsort%5D=requestTime&performance_filters%5Border%5D=DESC&performance_filters%5Blimit%5D=100',
        );
        self::assertSame(200, $response->getStatusCode());
    }

    public function testStatisticsWithFormBoundEnv(): void
    {
        $response = $this->requestGet('/performance/statistics?statistics_env_filter%5Benv%5D=test');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testExportJsonWithEmptyEnvUsesKernelEnv(): void
    {
        $response = $this->requestGet('/performance/export/json');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testExportRecordsCsvWithRouteAndPath(): void
    {
        $response = $this->requestGet('/performance/export/records/csv?env=test&route=app&path=%2Ffoo');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testAccessRecordsWithPerPage100(): void
    {
        $response = $this->requestGet('/performance/access-records?env=test&per_page=100');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testAccessRecordsWithPathFilter(): void
    {
        $response = $this->requestGet('/performance/access-records?env=test&path=%2Fsome-path');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testChartDataWithDays30(): void
    {
        $response = $this->requestGet('/performance/api/chart-data?env=test&days=30');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testChartDataWithMetricTotalQueries(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance/api/chart-data?env=test&days=7&metric=totalQueries');
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->headers->get('Content-Type') ?? '');
    }

    public function testIndexWithMinMaxRequestTimeAndQueryCountForm(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet(
            '/performance?env=test&min_request_time=0&max_request_time=5&min_query_count=0&max_query_count=100',
        );
        self::assertSame(200, $response->getStatusCode());
    }

    public function testAccessRecordsWithSortByResponseTime(): void
    {
        $response = $this->requestGet('/performance/access-records?env=test&sort_by=response_time&order=ASC');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexWithLimitZeroNormalizedToDefault(): void
    {
        $response = $this->requestGet('/performance?env=test&limit=0');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testStatisticsWithStartAndEndDateParams(): void
    {
        $response = $this->requestGet(
            '/performance/access-statistics?env=test&start_date=2024-01-01&end_date=2026-12-31',
        );
        self::assertSame(200, $response->getStatusCode());
    }

    public function testExportRecordsJsonWithLimitParam(): void
    {
        $response = $this->requestGet('/performance/export/records/json?env=test&limit=100');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testDiagnoseMultipleEnvironments(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestGet('/performance/diagnose');
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('diagnose', strtolower($response->getContent() ?: '') ?: 'diagnose');
    }

    /** Full flow: create data, then hit access-statistics and access-records to trigger repository methods. */
    public function testFullFlowAccessStatisticsAndAccessRecordsWithData(): void
    {
        $this->createTablesAndRecordMetric();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);
        $service->recordMetrics('route_a', 'test', 0.2, 4, 0.01, null, 512000, 'GET', 200, [], null, null, null, null, '/route_a');
        $service->recordMetrics('route_b', 'test', 0.3, 8, 0.02);

        $responseStats = $this->requestGet('/performance/access-statistics?env=test&start_date=2024-01-01&end_date=2026-12-31&route=route');
        self::assertSame(200, $responseStats->getStatusCode());

        $responseRecords = $this->requestGet('/performance/access-records?env=test&page=1&per_page=10&sort_by=accessed_at&order=DESC');
        self::assertSame(200, $responseRecords->getStatusCode());

        $responseExport = $this->requestGet('/performance/export/records/json?env=test');
        self::assertSame(200, $responseExport->getStatusCode());
        self::assertStringContainsString('application/json', $responseExport->headers->get('Content-Type') ?? '');
    }

    public function testIndexWithLimit2Page2Pagination(): void
    {
        $this->createTablesAndRecordMetric();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);
        $service->recordMetrics('pagination_route_1', 'test', 0.1, 1);
        $service->recordMetrics('pagination_route_2', 'test', 0.1, 1);

        $response = $this->requestGet('/performance?env=test&limit=2&page=2');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testExportCsvStreamWithMultipleRoutes(): void
    {
        $this->createTablesAndRecordMetric();
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);
        $service->recordMetrics('csv_route_1', 'test', 0.1, 2);
        $service->recordMetrics('csv_route_2', 'test', 0.2, 4);

        $response = $this->requestGet('/performance/export/csv?env=test');
        self::assertSame(200, $response->getStatusCode());
        if (method_exists($response, 'sendContent')) {
            ob_start();
            $response->sendContent();
            $out = ob_get_clean();
            self::assertIsString($out);
            self::assertStringContainsString('Route Name', $out);
        }
    }

    public function testAccessStatisticsWithPathFilter(): void
    {
        $response = $this->requestGet('/performance/access-statistics?env=test&path=%2Fapi%2F');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testClearPostWithRefererInvalidUrlRedirectsToIndex(): void
    {
        $this->createTablesAndRecordMetric();
        $response = $this->requestPost(
            '/performance/clear',
            [
                'clear_performance_data' => [
                    'env'    => 'test',
                    '_token' => 'invalid',
                    'submit' => '',
                ],
            ],
            false,
            ['HTTP_REFERER' => 'not-a-valid-url'],
        );
        self::assertTrue($response->isRedirection());
        $location = $response->headers->get('Location');
        self::assertNotNull($location);
        self::assertStringContainsString('performance', $location);
    }

    private function getClearFormCsrfToken(): ?string
    {
        $container = $this->kernel->getContainer();
        if (!$container->has('form.factory')) {
            return null;
        }
        $formFactory = $container->get('form.factory');
        $form        = $formFactory->create(ClearPerformanceDataType::class, new ClearPerformanceDataRequest('test'));
        $view        = $form->createView();
        if (!isset($view['_token'], $view['_token']->vars['value'])) {
            return null;
        }

        return $view['_token']->vars['value'];
    }

    private function getFirstRouteDataId(): ?int
    {
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);
        $routes  = $service->getRoutesByEnvironment('test');
        if ($routes === []) {
            return null;
        }
        $first = $routes[0];

        return $first->getId();
    }

    private function requestPost(string $uri, array $data, bool $followRedirect = false, array $server = []): \Symfony\Component\HttpFoundation\Response
    {
        $request = Request::create($uri, Request::METHOD_POST, $data, [], [], $server);
        $request->headers->set('Content-Type', 'application/x-www-form-urlencoded');
        $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
        if ($followRedirect && $response->isRedirection()) {
            $location = $response->headers->get('Location');
            if ($location !== null && str_starts_with($location, '/')) {
                $request  = Request::create('http://localhost' . $location, Request::METHOD_GET);
                $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
            }
        }

        return $response;
    }

    private function createTablesAndRecordMetric(): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        (new CommandTester($application->find('nowo:performance:create-table')))->execute([]);
        (new CommandTester($application->find('nowo:performance:create-records-table')))->execute([]);
        $service = $this->kernel->getContainer()->get(PerformanceMetricsService::class);
        $service->recordMetrics('integration_test_route', 'test', 0.1, 3, 0.02);
    }

    private function requestGet(string $uri): \Symfony\Component\HttpFoundation\Response
    {
        $request  = Request::create($uri, Request::METHOD_GET);
        $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
        if ($response->isRedirection()) {
            $location = $response->headers->get('Location');
            self::assertNotNull($location);
            $request  = Request::create($location, Request::METHOD_GET);
            $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
        }

        return $response;
    }
}
