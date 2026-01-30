<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Model;

use Nowo\PerformanceBundle\Model\RecordFilters;
use PHPUnit\Framework\TestCase;

final class RecordFiltersTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $f = new RecordFilters();
        $this->assertNull($f->startDate);
        $this->assertNull($f->endDate);
        $this->assertNull($f->env);
        $this->assertNull($f->route);
        $this->assertNull($f->statusCode);
        $this->assertNull($f->minQueryTime);
        $this->assertNull($f->maxQueryTime);
        $this->assertNull($f->minMemoryUsage);
        $this->assertNull($f->maxMemoryUsage);
    }

    public function testConstructorWithValues(): void
    {
        $start = new \DateTimeImmutable('2026-01-01');
        $end = new \DateTimeImmutable('2026-01-31');
        $f = new RecordFilters($start, $end, 'prod', 'app_user', 404);
        $this->assertSame($start, $f->startDate);
        $this->assertSame($end, $f->endDate);
        $this->assertSame('prod', $f->env);
        $this->assertSame('app_user', $f->route);
        $this->assertSame(404, $f->statusCode);
        $this->assertNull($f->minQueryTime);
        $this->assertNull($f->maxQueryTime);
        $this->assertNull($f->minMemoryUsage);
        $this->assertNull($f->maxMemoryUsage);
    }

    public function testConstructorWithQueryTimeAndMemoryFilters(): void
    {
        $start = new \DateTimeImmutable('2026-01-01');
        $end = new \DateTimeImmutable('2026-01-31');
        $f = new RecordFilters(
            $start,
            $end,
            'dev',
            'api_foo',
            200,
            0.1,
            5.0,
            10 * 1024 * 1024,
            100 * 1024 * 1024
        );
        $this->assertSame($start, $f->startDate);
        $this->assertSame($end, $f->endDate);
        $this->assertSame('dev', $f->env);
        $this->assertSame('api_foo', $f->route);
        $this->assertSame(200, $f->statusCode);
        $this->assertSame(0.1, $f->minQueryTime);
        $this->assertSame(5.0, $f->maxQueryTime);
        $this->assertSame(10 * 1024 * 1024, $f->minMemoryUsage);
        $this->assertSame(100 * 1024 * 1024, $f->maxMemoryUsage);
    }

    public function testAllNullFilters(): void
    {
        $f = new RecordFilters(null, null, null, null, null, null, null, null, null);

        $this->assertNull($f->startDate);
        $this->assertNull($f->endDate);
        $this->assertNull($f->env);
        $this->assertNull($f->route);
        $this->assertNull($f->statusCode);
        $this->assertNull($f->minQueryTime);
        $this->assertNull($f->maxQueryTime);
        $this->assertNull($f->minMemoryUsage);
        $this->assertNull($f->maxMemoryUsage);
    }

    public function testPropertyAssignment(): void
    {
        $f = new RecordFilters();
        $start = new \DateTimeImmutable('2026-02-01');
        $end = new \DateTimeImmutable('2026-02-28');

        $f->startDate = $start;
        $f->endDate = $end;
        $f->env = 'stage';
        $f->route = 'api_bar';

        $this->assertSame($start, $f->startDate);
        $this->assertSame($end, $f->endDate);
        $this->assertSame('stage', $f->env);
        $this->assertSame('api_bar', $f->route);
    }

    public function testRouteEmptyString(): void
    {
        $f = new RecordFilters(null, null, 'dev', '', null);
        $this->assertSame('', $f->route);
        $this->assertSame('dev', $f->env);
    }

    public function testOnlyDateFilters(): void
    {
        $start = new \DateTimeImmutable('2026-03-01');
        $end = new \DateTimeImmutable('2026-03-31');
        $f = new RecordFilters($start, $end, null, null, null, null, null, null, null);
        $this->assertSame($start, $f->startDate);
        $this->assertSame($end, $f->endDate);
        $this->assertNull($f->env);
        $this->assertNull($f->route);
        $this->assertNull($f->statusCode);
    }

    public function testOnlyStatusCodeFilter(): void
    {
        $f = new RecordFilters(null, null, null, null, 500);
        $this->assertSame(500, $f->statusCode);
        $this->assertNull($f->minQueryTime);
        $this->assertNull($f->maxMemoryUsage);
    }

    public function testMinQueryTimeAndMaxQueryTimeZero(): void
    {
        $f = new RecordFilters(null, null, null, null, null, 0.0, 0.0, null, null);
        $this->assertSame(0.0, $f->minQueryTime);
        $this->assertSame(0.0, $f->maxQueryTime);
    }

    public function testMinMemoryUsageAndMaxMemoryUsageZero(): void
    {
        $f = new RecordFilters(null, null, null, null, null, null, null, 0, 0);
        $this->assertSame(0, $f->minMemoryUsage);
        $this->assertSame(0, $f->maxMemoryUsage);
    }

    public function testStatusCodeZeroIsAccepted(): void
    {
        $f = new RecordFilters(null, null, null, null, 0);
        $this->assertSame(0, $f->statusCode);
    }

    public function testAssignMinQueryTimeAndMaxQueryTimeAfterConstruction(): void
    {
        $f = new RecordFilters();
        $f->minQueryTime = 0.5;
        $f->maxQueryTime = 2.5;
        $this->assertSame(0.5, $f->minQueryTime);
        $this->assertSame(2.5, $f->maxQueryTime);
    }

    public function testAssignMinMemoryUsageAndMaxMemoryUsageAfterConstruction(): void
    {
        $f = new RecordFilters();
        $f->minMemoryUsage = 1024;
        $f->maxMemoryUsage = 1048576;
        $this->assertSame(1024, $f->minMemoryUsage);
        $this->assertSame(1048576, $f->maxMemoryUsage);
    }

    public function testAssignStatusCodeAfterConstruction(): void
    {
        $f = new RecordFilters();
        $f->statusCode = 503;
        $this->assertSame(503, $f->statusCode);
    }

    public function testEnvWithEmptyString(): void
    {
        $f = new RecordFilters(null, null, '', null, null);
        $this->assertSame('', $f->env);
    }

    public function testAssignRouteAndStatusCodeAfterConstruction(): void
    {
        $f = new RecordFilters();
        $f->route = 'api_users';
        $f->statusCode = 200;

        $this->assertSame('api_users', $f->route);
        $this->assertSame(200, $f->statusCode);
    }

    public function testAssignRouteAfterConstruction(): void
    {
        $f = new RecordFilters();
        $f->route = 'api_dashboard';
        $this->assertSame('api_dashboard', $f->route);
    }

    public function testAssignStartDateAndEndDateAfterConstruction(): void
    {
        $f = new RecordFilters();
        $start = new \DateTimeImmutable('2026-04-01');
        $end = new \DateTimeImmutable('2026-04-30');

        $f->startDate = $start;
        $f->endDate = $end;

        $this->assertSame($start, $f->startDate);
        $this->assertSame($end, $f->endDate);
    }

    public function testAssignEnvAfterConstruction(): void
    {
        $f = new RecordFilters();
        $f->env = 'stage';

        $this->assertSame('stage', $f->env);
    }

    public function testAssignAllFiltersAfterConstruction(): void
    {
        $f = new RecordFilters();
        $start = new \DateTimeImmutable('2026-05-01');
        $end = new \DateTimeImmutable('2026-05-31');

        $f->startDate = $start;
        $f->endDate = $end;
        $f->env = 'prod';
        $f->route = 'api_report';
        $f->statusCode = 200;
        $f->minQueryTime = 0.01;
        $f->maxQueryTime = 2.0;
        $f->minMemoryUsage = 1048576;
        $f->maxMemoryUsage = 52428800;

        $this->assertSame($start, $f->startDate);
        $this->assertSame($end, $f->endDate);
        $this->assertSame('prod', $f->env);
        $this->assertSame('api_report', $f->route);
        $this->assertSame(200, $f->statusCode);
        $this->assertSame(0.01, $f->minQueryTime);
        $this->assertSame(2.0, $f->maxQueryTime);
        $this->assertSame(1048576, $f->minMemoryUsage);
        $this->assertSame(52428800, $f->maxMemoryUsage);
    }
}
