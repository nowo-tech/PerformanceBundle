<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Model;

use Nowo\PerformanceBundle\Model\PurgeAccessRecordsRequest;
use PHPUnit\Framework\TestCase;

final class PurgeAccessRecordsRequestTest extends TestCase
{
    public function testConstants(): void
    {
        $this->assertSame('all', PurgeAccessRecordsRequest::PURGE_ALL);
        $this->assertSame('older_than', PurgeAccessRecordsRequest::PURGE_OLDER_THAN);
    }

    public function testDefaultConstructor(): void
    {
        $request = new PurgeAccessRecordsRequest();

        $this->assertSame('', $request->env);
        $this->assertSame(PurgeAccessRecordsRequest::PURGE_OLDER_THAN, $request->purgeType);
        $this->assertSame(30, $request->days);
    }

    public function testConstructorWithCustomValues(): void
    {
        $request = new PurgeAccessRecordsRequest(
            env: 'prod',
            purgeType: PurgeAccessRecordsRequest::PURGE_ALL,
            days: 90,
        );

        $this->assertSame('prod', $request->env);
        $this->assertSame(PurgeAccessRecordsRequest::PURGE_ALL, $request->purgeType);
        $this->assertSame(90, $request->days);
    }

    public function testConstructorWithOlderThanAndDays(): void
    {
        $request = new PurgeAccessRecordsRequest(
            env: 'dev',
            purgeType: PurgeAccessRecordsRequest::PURGE_OLDER_THAN,
            days: 7,
        );

        $this->assertSame('dev', $request->env);
        $this->assertSame('older_than', $request->purgeType);
        $this->assertSame(7, $request->days);
    }

    public function testConstructorWithNullDays(): void
    {
        $request = new PurgeAccessRecordsRequest(env: '', purgeType: 'older_than', days: null);

        $this->assertNull($request->days);
    }
}
