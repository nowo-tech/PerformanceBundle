<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit;

use Nowo\PerformanceBundle\DependencyInjection\Compiler\QueryTrackingMiddlewarePass;
use Nowo\PerformanceBundle\DependencyInjection\PerformanceExtension;
use Nowo\PerformanceBundle\NowoPerformanceBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

/**
 * Tests for NowoPerformanceBundle.
 */
final class NowoPerformanceBundleTest extends TestCase
{
    public function testGetContainerExtensionReturnsPerformanceExtension(): void
    {
        $bundle = new NowoPerformanceBundle();

        $extension = $bundle->getContainerExtension();

        $this->assertInstanceOf(PerformanceExtension::class, $extension);
    }

    public function testGetContainerExtensionReturnsSameInstanceOnMultipleCalls(): void
    {
        $bundle = new NowoPerformanceBundle();

        $extension1 = $bundle->getContainerExtension();
        $extension2 = $bundle->getContainerExtension();

        $this->assertSame($extension1, $extension2);
    }

    public function testGetContainerExtensionReturnsExtensionInterface(): void
    {
        $bundle = new NowoPerformanceBundle();

        $extension = $bundle->getContainerExtension();

        $this->assertInstanceOf(ExtensionInterface::class, $extension);
    }

    public function testBuildRegistersCompilerPass(): void
    {
        $bundle = new NowoPerformanceBundle();
        $container = $this->createMock(ContainerBuilder::class);

        $container->expects($this->once())
            ->method('addCompilerPass')
            ->with($this->isInstanceOf(QueryTrackingMiddlewarePass::class));

        $bundle->build($container);
    }

    public function testBuildCallsParentBuild(): void
    {
        $bundle = new NowoPerformanceBundle();
        $container = $this->createMock(ContainerBuilder::class);

        // Verify that addCompilerPass is called (which means build is executed)
        $container->expects($this->once())
            ->method('addCompilerPass')
            ->with($this->isInstanceOf(QueryTrackingMiddlewarePass::class));

        $bundle->build($container);
    }

    public function testGetPathReturnsNonEmptyPath(): void
    {
        $bundle = new NowoPerformanceBundle();

        $path = $bundle->getPath();

        $this->assertIsString($path);
        $this->assertNotEmpty($path);
        $this->assertStringContainsString('PerformanceBundle', $path);
    }
}
