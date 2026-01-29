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

    public function testConstructorWithEmptyString(): void
    {
        $f = new StatisticsEnvFilter('');
        $this->assertSame('', $f->env);
    }

    public function testEnvPropertyCanBeAssigned(): void
    {
        $f = new StatisticsEnvFilter();
        $f->env = 'stage';
        $this->assertSame('stage', $f->env);
    }
}
