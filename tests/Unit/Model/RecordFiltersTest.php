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
}
