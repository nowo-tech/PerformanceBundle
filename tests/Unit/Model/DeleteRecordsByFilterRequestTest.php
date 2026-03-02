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

    public function testFromAccessStatistics(): void
    {
        $r = new DeleteRecordsByFilterRequest(
            env: 'dev',
            from: 'access_statistics',
            startDate: null,
            endDate: null,
        );
        $this->assertSame('access_statistics', $r->from);
        $this->assertSame('dev', $r->env);
    }

    public function testEmptyEnvAndRoute(): void
    {
        $r = new DeleteRecordsByFilterRequest();
        $this->assertSame('', $r->env);
        $this->assertSame('access_records', $r->from);
        $this->assertNull($r->route);
        $this->assertNull($r->statusCode);
    }

    public function testConstructorWithOnlyEnvAndFrom(): void
    {
        $r = new DeleteRecordsByFilterRequest(env: 'stage', from: 'access_records');
        $this->assertSame('stage', $r->env);
        $this->assertSame('access_records', $r->from);
        $this->assertNull($r->startDate);
        $this->assertNull($r->endDate);
        $this->assertNull($r->route);
        $this->assertNull($r->statusCode);
    }

    public function testFromPropertyCanBeAssigned(): void
    {
        $r = new DeleteRecordsByFilterRequest(env: 'dev', from: 'access_records');
        $this->assertSame('access_records', $r->from);

        $r->from = 'access_statistics';
        $this->assertSame('access_statistics', $r->from);
    }

    public function testFromPropertyCanBeAssignedBackToAccessRecords(): void
    {
        $r       = new DeleteRecordsByFilterRequest(env: 'prod', from: 'access_statistics');
        $r->from = 'access_records';

        $this->assertSame('access_records', $r->from);
    }

    public function testPropertiesCanBeAssignedAfterConstruction(): void
    {
        $r = new DeleteRecordsByFilterRequest();

        $r->startDate      = '2026-03-01';
        $r->endDate        = '2026-03-31';
        $r->route          = 'api_users';
        $r->statusCode     = '404';
        $r->minQueryTime   = '0.01';
        $r->maxQueryTime   = '1.5';
        $r->minMemoryUsage = '524288';
        $r->maxMemoryUsage = '10485760';

        $this->assertSame('2026-03-01', $r->startDate);
        $this->assertSame('2026-03-31', $r->endDate);
        $this->assertSame('api_users', $r->route);
        $this->assertSame('404', $r->statusCode);
        $this->assertSame('0.01', $r->minQueryTime);
        $this->assertSame('1.5', $r->maxQueryTime);
        $this->assertSame('524288', $r->minMemoryUsage);
        $this->assertSame('10485760', $r->maxMemoryUsage);
    }

    public function testEnvCanBeAssignedEmptyString(): void
    {
        $r = new DeleteRecordsByFilterRequest(env: 'prod', from: 'access_records');
        $this->assertSame('prod', $r->env);

        $r->env = '';
        $this->assertSame('', $r->env);
    }

    public function testEnvWithTestEnvironment(): void
    {
        $r = new DeleteRecordsByFilterRequest(env: 'test', from: 'access_records');

        $this->assertSame('test', $r->env);
        $this->assertSame('access_records', $r->from);
    }

    public function testStatusCodeWith503String(): void
    {
        $r = new DeleteRecordsByFilterRequest(
            env: 'prod',
            from: 'access_records',
            statusCode: '503',
        );

        $this->assertSame('503', $r->statusCode);
    }

    public function testConstructorWithAllOptionalParams(): void
    {
        $r = new DeleteRecordsByFilterRequest(
            env: 'stage',
            from: 'access_statistics',
            startDate: '2026-05-01T00:00',
            endDate: '2026-05-31T23:59',
            route: 'api_report',
            statusCode: '200',
            minQueryTime: '0.01',
            maxQueryTime: '1.5',
            minMemoryUsage: '1048576',
            maxMemoryUsage: '104857600',
        );

        $this->assertSame('stage', $r->env);
        $this->assertSame('access_statistics', $r->from);
        $this->assertSame('2026-05-01T00:00', $r->startDate);
        $this->assertSame('2026-05-31T23:59', $r->endDate);
        $this->assertSame('api_report', $r->route);
        $this->assertSame('200', $r->statusCode);
        $this->assertSame('0.01', $r->minQueryTime);
        $this->assertSame('1.5', $r->maxQueryTime);
        $this->assertSame('1048576', $r->minMemoryUsage);
        $this->assertSame('104857600', $r->maxMemoryUsage);
    }
}
