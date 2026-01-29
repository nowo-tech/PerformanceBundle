<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Twig\Component;

use Nowo\PerformanceBundle\Twig\Component\StatisticsComponent;
use PHPUnit\Framework\TestCase;

final class StatisticsComponentTest extends TestCase
{
    public function testComponentHasDefaultValues(): void
    {
        $component = new StatisticsComponent();
        
        $this->assertSame([], $component->stats);
        $this->assertSame('bootstrap', $component->template);
    }

    public function testComponentCanSetProperties(): void
    {
        $component = new StatisticsComponent();
        $stats = ['request_time' => ['mean' => 0.5]];
        
        $component->stats = $stats;
        $component->template = 'tailwind';
        
        $this->assertSame($stats, $component->stats);
        $this->assertSame('tailwind', $component->template);
    }

    public function testTemplateDefaultIsBootstrap(): void
    {
        $component = new StatisticsComponent();

        $this->assertSame('bootstrap', $component->template);
    }

    public function testStatsWithMultipleKeys(): void
    {
        $component = new StatisticsComponent();
        $stats = [
            'request_time' => ['mean' => 0.5, 'p95' => 1.2],
            'query_count' => ['mean' => 10, 'max' => 50],
        ];
        $component->stats = $stats;

        $this->assertSame($stats, $component->stats);
        $this->assertSame(0.5, $component->stats['request_time']['mean']);
        $this->assertSame(50, $component->stats['query_count']['max']);
    }
}
