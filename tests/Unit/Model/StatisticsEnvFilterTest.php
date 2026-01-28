<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Model;

use Nowo\PerformanceBundle\Model\StatisticsEnvFilter;
use PHPUnit\Framework\TestCase;

final class StatisticsEnvFilterTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $f = new StatisticsEnvFilter();
        $this->assertNull($f->env);
    }

    public function testConstructorWithValue(): void
    {
        $f = new StatisticsEnvFilter('prod');
        $this->assertSame('prod', $f->env);
    }
}
