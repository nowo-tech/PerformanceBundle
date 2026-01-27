<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Twig\Component;

use Nowo\PerformanceBundle\Twig\Component\ChartsComponent;
use PHPUnit\Framework\TestCase;

final class ChartsComponentTest extends TestCase
{
    public function testComponentHasDefaultValues(): void
    {
        $component = new ChartsComponent();
        
        $this->assertSame('dev', $component->environment);
        $this->assertNull($component->currentRoute);
        $this->assertSame('bootstrap', $component->template);
    }

    public function testComponentCanSetProperties(): void
    {
        $component = new ChartsComponent();
        
        $component->environment = 'prod';
        $component->currentRoute = 'app_home';
        $component->chartDataUrl = '/api/chart-data';
        $component->template = 'tailwind';
        
        $this->assertSame('prod', $component->environment);
        $this->assertSame('app_home', $component->currentRoute);
        $this->assertSame('/api/chart-data', $component->chartDataUrl);
        $this->assertSame('tailwind', $component->template);
    }
}
