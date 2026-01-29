<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Twig\Component;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Twig\Component\RoutesTableComponent;
use PHPUnit\Framework\TestCase;

final class RoutesTableComponentTest extends TestCase
{
    public function testComponentHasDefaultValues(): void
    {
        $component = new RoutesTableComponent();
        
        $this->assertSame([], $component->routes);
        $this->assertSame('bootstrap', $component->template);
        $this->assertSame('requestTime', $component->sortBy);
        $this->assertSame('DESC', $component->order);
        $this->assertSame([], $component->thresholds);
        $this->assertFalse($component->enableRecordManagement);
        $this->assertFalse($component->enableReviewSystem);
        $this->assertSame([], $component->reviewForms);
        $this->assertSame([], $component->deleteForms);
    }

    public function testComponentCanSetProperties(): void
    {
        $component = new RoutesTableComponent();
        $route = new RouteData();
        
        $component->routes = [$route];
        $component->template = 'tailwind';
        $component->thresholds = ['request_time' => ['warning' => 0.5]];
        $component->enableRecordManagement = true;
        $component->enableReviewSystem = true;
        
        $this->assertCount(1, $component->routes);
        $this->assertSame('tailwind', $component->template);
        $this->assertArrayHasKey('request_time', $component->thresholds);
        $this->assertTrue($component->enableRecordManagement);
        $this->assertTrue($component->enableReviewSystem);
    }

    public function testSortByAndOrderCanBeSet(): void
    {
        $component = new RoutesTableComponent();
        $this->assertSame('requestTime', $component->sortBy);
        $this->assertSame('DESC', $component->order);

        $component->sortBy = 'memoryUsage';
        $component->order = 'ASC';
        $this->assertSame('memoryUsage', $component->sortBy);
        $this->assertSame('ASC', $component->order);
    }

    public function testReviewFormsAndDeleteFormsDefaultEmpty(): void
    {
        $component = new RoutesTableComponent();

        $this->assertSame([], $component->reviewForms);
        $this->assertSame([], $component->deleteForms);
        $this->assertIsArray($component->reviewForms);
        $this->assertIsArray($component->deleteForms);
    }

    public function testEnableRecordManagementAndReviewSystemDefaults(): void
    {
        $component = new RoutesTableComponent();

        $this->assertFalse($component->enableRecordManagement);
        $this->assertFalse($component->enableReviewSystem);
    }
}
