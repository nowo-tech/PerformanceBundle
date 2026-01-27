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
}
