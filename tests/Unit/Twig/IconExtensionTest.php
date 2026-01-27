<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Twig;

use Nowo\PerformanceBundle\Service\DependencyChecker;
use Nowo\PerformanceBundle\Twig\IconExtension;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class IconExtensionTest extends TestCase
{
    private DependencyChecker|MockObject $dependencyChecker;
    private IconExtension $extension;

    protected function setUp(): void
    {
        $this->dependencyChecker = $this->createMock(DependencyChecker::class);
        $this->extension = new IconExtension($this->dependencyChecker);
    }

    public function testGetFunctionsReturnsPerformanceIconFunction(): void
    {
        $functions = $this->extension->getFunctions();
        
        $this->assertCount(1, $functions);
        $this->assertSame('performance_icon', $functions[0]->getName());
    }

    public function testRenderIconWithIconsAvailable(): void
    {
        $this->dependencyChecker->method('isIconsAvailable')->willReturn(true);
        
        // Mock ux_icon function doesn't exist in test environment, so it will fall back
        $result = $this->extension->renderIcon('test', [], '<svg>test</svg>');
        
        $this->assertStringContainsString('svg', $result);
    }

    public function testRenderIconWithIconsNotAvailable(): void
    {
        $this->dependencyChecker->method('isIconsAvailable')->willReturn(false);
        
        $result = $this->extension->renderIcon('test', ['class' => 'icon-class'], '<svg>test</svg>');
        
        $this->assertStringContainsString('svg', $result);
        $this->assertStringContainsString('icon-class', $result);
    }

    public function testRenderIconWithFallbackSvgContainingSvgTag(): void
    {
        $this->dependencyChecker->method('isIconsAvailable')->willReturn(false);
        
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><path/></svg>';
        $result = $this->extension->renderIcon('test', [], $svg);
        
        $this->assertSame($svg, $result);
    }

    public function testRenderIconWithNoFallback(): void
    {
        $this->dependencyChecker->method('isIconsAvailable')->willReturn(false);
        
        $result = $this->extension->renderIcon('test', [], null);
        
        $this->assertSame('', $result);
    }

    public function testRenderIconWithOptions(): void
    {
        $this->dependencyChecker->method('isIconsAvailable')->willReturn(false);
        
        $result = $this->extension->renderIcon('test', [
            'class' => 'icon-class',
            'style' => 'color: red;'
        ], 'icon-content');
        
        $this->assertStringContainsString('icon-class', $result);
        $this->assertStringContainsString('color: red;', $result);
        $this->assertStringContainsString('icon-content', $result);
    }
}
