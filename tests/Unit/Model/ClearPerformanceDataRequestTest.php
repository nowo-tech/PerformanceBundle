<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Model;

use Nowo\PerformanceBundle\Model\ClearPerformanceDataRequest;
use PHPUnit\Framework\TestCase;

final class ClearPerformanceDataRequestTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $r = new ClearPerformanceDataRequest();
        $this->assertNull($r->env);
    }

    public function testConstructorWithValue(): void
    {
        $r = new ClearPerformanceDataRequest('dev');
        $this->assertSame('dev', $r->env);
    }
}
