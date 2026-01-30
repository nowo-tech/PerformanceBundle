<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Twig;

use Nowo\PerformanceBundle\Twig\IconExtension;
use PHPUnit\Framework\TestCase;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class IconExtensionTest extends TestCase
{
    private IconExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new IconExtension();
    }

    public function testGetFunctionsReturnsPerformanceIcon(): void
    {
        $fns = $this->extension->getFunctions();

        $this->assertIsArray($fns);
        $this->assertCount(1, $fns);
        $this->assertInstanceOf(TwigFunction::class, $fns[0]);
        $this->assertSame('performance_icon', $fns[0]->getName());
    }

    public function testRenderIconReturnsEmptyWhenUxIconNotDefined(): void
    {
        $result = $this->extension->renderIcon('bi:gear', []);

        $this->assertSame('', $result);
    }

    public function testRenderIconWithOptionsReturnsEmptyWhenUxIconNotDefined(): void
    {
        $result = $this->extension->renderIcon('heroicons:check', ['class' => 'icon-sm']);

        $this->assertSame('', $result);
    }

    public function testExtendsAbstractExtension(): void
    {
        $this->assertInstanceOf(AbstractExtension::class, $this->extension);
    }

    public function testRenderIconWithEmptyOptionsArray(): void
    {
        $result = $this->extension->renderIcon('bi:clock', []);

        $this->assertSame('', $result);
    }

    public function testRenderIconWithEmptyIconName(): void
    {
        $result = $this->extension->renderIcon('', []);

        $this->assertSame('', $result);
    }

    public function testRenderIconWithStyleOptionReturnsEmptyWhenUxIconNotDefined(): void
    {
        $result = $this->extension->renderIcon('bi:trash', ['style' => 'width: 24px']);

        $this->assertSame('', $result);
    }
}
