<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Twig;

use Nowo\PerformanceBundle\Service\DependencyChecker;
use Nowo\PerformanceBundle\Twig\IconExtension;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Advanced tests for IconExtension.
 */
final class IconExtensionAdvancedTest extends TestCase
{
    private DependencyChecker|MockObject $dependencyChecker;
    private IconExtension $extension;

    protected function setUp(): void
    {
        $this->dependencyChecker = $this->createMock(DependencyChecker::class);
        $this->extension = new IconExtension($this->dependencyChecker);
    }

    public function testRenderIconWithIconsAvailableAndUxIconFunctionExists(): void
    {
        $this->dependencyChecker->method('isIconsAvailable')->willReturn(true);

        // When ux_icon function doesn't exist (test environment), it falls back to SVG
        $result = $this->extension->renderIcon('test', [], '<svg>test</svg>');

        $this->assertStringContainsString('svg', $result);
    }

    public function testRenderIconWithIconsNotAvailableAndFallbackSvg(): void
    {
        $this->dependencyChecker->method('isIconsAvailable')->willReturn(false);

        $result = $this->extension->renderIcon('test', ['class' => 'icon-class'], '<svg>test</svg>');

        $this->assertStringContainsString('svg', $result);
        $this->assertStringContainsString('icon-class', $result);
    }

    public function testRenderIconWithFallbackSvgContainingSvgTag(): void
    {
        $this->dependencyChecker->method('isIconsAvailable')->willReturn(false);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><path d="M10 10"/></svg>';
        $result = $this->extension->renderIcon('test', [], $svg);

        // Should return SVG directly when it contains <svg tag
        $this->assertStringContainsString('<svg', $result);
        $this->assertStringContainsString('path', $result);
    }

    public function testRenderIconWithFallbackSvgWithoutSvgTag(): void
    {
        $this->dependencyChecker->method('isIconsAvailable')->willReturn(false);

        $svg = '<path d="M10 10"/>';
        $result = $this->extension->renderIcon('test', ['class' => 'icon'], $svg);

        // Should wrap in span when SVG doesn't contain <svg tag
        $this->assertStringContainsString('<span', $result);
        $this->assertStringContainsString('class="icon"', $result);
        $this->assertStringContainsString('path', $result);
    }

    public function testRenderIconWithNoFallbackSvg(): void
    {
        $this->dependencyChecker->method('isIconsAvailable')->willReturn(false);

        $result = $this->extension->renderIcon('test', [], null);

        // Should return empty string when no fallback SVG
        $this->assertSame('', $result);
    }

    public function testRenderIconWithEmptyFallbackSvg(): void
    {
        $this->dependencyChecker->method('isIconsAvailable')->willReturn(false);

        $result = $this->extension->renderIcon('test', [], '');

        // Should return empty string when fallback SVG is empty
        $this->assertSame('', $result);
    }

    public function testRenderIconWithStyleOption(): void
    {
        $this->dependencyChecker->method('isIconsAvailable')->willReturn(false);

        $result = $this->extension->renderIcon('test', ['style' => 'color: red;'], '<path/>');

        $this->assertStringContainsString('style="color: red;"', $result);
    }

    public function testRenderIconWithClassAndStyleOptions(): void
    {
        $this->dependencyChecker->method('isIconsAvailable')->willReturn(false);

        $result = $this->extension->renderIcon('test', [
            'class' => 'icon-class',
            'style' => 'width: 20px;',
        ], '<path/>');

        $this->assertStringContainsString('class="icon-class"', $result);
        $this->assertStringContainsString('style="width: 20px;"', $result);
    }

    public function testRenderIconWithEmptyOptions(): void
    {
        $this->dependencyChecker->method('isIconsAvailable')->willReturn(false);

        $result = $this->extension->renderIcon('test', [], '<svg>test</svg>');

        $this->assertStringContainsString('svg', $result);
        $this->assertStringContainsString('class=""', $result);
        $this->assertStringContainsString('style=""', $result);
    }

    public function testRenderIconWithEmptyStringName(): void
    {
        $this->dependencyChecker->method('isIconsAvailable')->willReturn(false);

        $result = $this->extension->renderIcon('', [], '<svg>test</svg>');

        $this->assertStringContainsString('svg', $result);
    }

    public function testGetFunctionsReturnsPerformanceIconFunction(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('performance_icon', $functions[0]->getName());
    }

    public function testGetFunctionsFunctionIsSafeForHtml(): void
    {
        $functions = $this->extension->getFunctions();

        $options = $functions[0]->getOptions();
        $this->assertArrayHasKey('is_safe', $options);
        $this->assertContains('html', $options['is_safe']);
    }
}
