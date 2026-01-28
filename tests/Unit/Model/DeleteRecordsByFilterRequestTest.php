<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Model;

use Nowo\PerformanceBundle\Model\DeleteRecordsByFilterRequest;
use PHPUnit\Framework\TestCase;

final class DeleteRecordsByFilterRequestTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $r = new DeleteRecordsByFilterRequest();
        $this->assertSame('', $r->env);
        $this->assertSame('access_records', $r->from);
        $this->assertNull($r->startDate);
        $this->assertNull($r->endDate);
        $this->assertNull($r->route);
        $this->assertNull($r->statusCode);
        $this->assertNull($r->minQueryTime);
        $this->assertNull($r->maxQueryTime);
        $this->assertNull($r->minMemoryUsage);
        $this->assertNull($r->maxMemoryUsage);
    }

    public function testConstructorWithValues(): void
    {
        $r = new DeleteRecordsByFilterRequest(
            env: 'prod',
            from: 'access_records',
            startDate: '2026-01-01',
            endDate: '2026-01-31',
            route: 'api_foo',
            statusCode: '500',
        );
        $this->assertSame('prod', $r->env);
        $this->assertSame('access_records', $r->from);
        $this->assertSame('2026-01-01', $r->startDate);
        $this->assertSame('2026-01-31', $r->endDate);
        $this->assertSame('api_foo', $r->route);
        $this->assertSame('500', $r->statusCode);
        $this->assertNull($r->minQueryTime);
        $this->assertNull($r->maxQueryTime);
        $this->assertNull($r->minMemoryUsage);
        $this->assertNull($r->maxMemoryUsage);
    }

    public function testConstructorWithQueryTimeAndMemoryValues(): void
    {
        $r = new DeleteRecordsByFilterRequest(
            env: 'dev',
            from: 'access_records',
            startDate: '2026-01-01',
            endDate: '2026-01-31',
            route: 'app_home',
            statusCode: '200',
            minQueryTime: '0.05',
            maxQueryTime: '2.5',
            minMemoryUsage: '1048576',
            maxMemoryUsage: '52428800',
        );
        $this->assertSame('dev', $r->env);
        $this->assertSame('0.05', $r->minQueryTime);
        $this->assertSame('2.5', $r->maxQueryTime);
        $this->assertSame('1048576', $r->minMemoryUsage);
        $this->assertSame('52428800', $r->maxMemoryUsage);
    }
}
