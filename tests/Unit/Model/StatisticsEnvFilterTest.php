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

    public function testEnvPropertyCanBeSetToNull(): void
    {
        $f = new StatisticsEnvFilter('dev');
        $this->assertSame('dev', $f->env);
        $f->env = null;
        $this->assertNull($f->env);
    }

    public function testEnvPropertyCanBeSetToEmptyString(): void
    {
        $f = new StatisticsEnvFilter('prod');
        $this->assertSame('prod', $f->env);
        $f->env = '';
        $this->assertSame('', $f->env);
    }

    public function testEnvPropertyCanBeSetToStage(): void
    {
        $f = new StatisticsEnvFilter();
        $f->env = 'stage';
        $this->assertSame('stage', $f->env);
    }

    public function testEnvPropertyCanBeSetToTest(): void
    {
        $f = new StatisticsEnvFilter();
        $f->env = 'test';
        $this->assertSame('test', $f->env);
    }
}
