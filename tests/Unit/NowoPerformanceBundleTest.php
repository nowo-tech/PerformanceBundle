<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit;

use Nowo\PerformanceBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use Nowo\PerformanceBundle\DependencyInjection\Compiler\NotificationChannelsPass;
use Nowo\PerformanceBundle\DependencyInjection\Compiler\QueryTrackingMiddlewarePass;
use Nowo\PerformanceBundle\DependencyInjection\Compiler\RemovedNotificationChannelPass;
use Nowo\PerformanceBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\PerformanceBundle\DependencyInjection\PerformanceExtension;
use Nowo\PerformanceBundle\NowoPerformanceBundle;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\VarDumper\VarDumper;

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

    /**
     * Covers getContainerExtension() return null when extension is not ExtensionInterface (defensive branch).
     * Parent Bundle allows extension to be false; in that case we return null.
     */
    public function testGetContainerExtensionReturnsNullWhenExtensionIsNotExtensionInterface(): void
    {
        $bundle     = new NowoPerformanceBundle();
        $reflection = new ReflectionProperty($bundle, 'extension');
        $reflection->setValue($bundle, false);

        $extension = $bundle->getContainerExtension();

        $this->assertNull($extension);
    }

    public function testBuildRegistersCompilerPass(): void
    {
        $bundle     = new NowoPerformanceBundle();
        $container  = $this->createMock(ContainerBuilder::class);
        $registered = [];

        $container->expects($this->exactly(6))
            ->method('addCompilerPass')
            ->willReturnCallback(static function (object $pass, string $type = PassConfig::TYPE_BEFORE_OPTIMIZATION, int $priority = 0) use (&$registered, $container): ContainerBuilder {
                $registered[] = $pass;
                if ($pass instanceof RemovedNotificationChannelPass) {
                    self::assertSame(PassConfig::TYPE_BEFORE_OPTIMIZATION, $type);
                    self::assertContains($priority, [1000, 0]);
                }

                return $container;
            });

        $bundle->build($container);

        $this->assertCount(6, $registered);
        $this->assertInstanceOf(CsrfProtectionPass::class, $registered[0]);
        $this->assertInstanceOf(NotificationChannelsPass::class, $registered[1]);
        $this->assertInstanceOf(RemovedNotificationChannelPass::class, $registered[2]);
        $this->assertInstanceOf(RemovedNotificationChannelPass::class, $registered[3]);
        $this->assertInstanceOf(QueryTrackingMiddlewarePass::class, $registered[4]);
        $this->assertInstanceOf(TwigPathsPass::class, $registered[5]);
    }

    public function testBuildCallsParentBuild(): void
    {
        $bundle    = new NowoPerformanceBundle();
        $container = $this->createMock(ContainerBuilder::class);

        $container->expects($this->exactly(6))
            ->method('addCompilerPass')
            ->willReturnSelf();

        $bundle->build($container);
    }

    public function testGetPathReturnsString(): void
    {
        $bundle = new NowoPerformanceBundle();
        $path   = $bundle->getPath();
        $this->assertIsString($path);
        $this->assertNotEmpty($path);
    }

    /** REQ-DI-001: the bundle no longer overrides boot() (no kernel lookup in the container, no global VarDumper handler). */
    public function testBundleDoesNotOverrideBoot(): void
    {
        self::assertNotSame(NowoPerformanceBundle::class, (new ReflectionMethod(NowoPerformanceBundle::class, 'boot'))->getDeclaringClass()->getName());
    }

    public function testBuildReceivesContainerBuilder(): void
    {
        $bundle    = new NowoPerformanceBundle();
        $container = new ContainerBuilder();

        $bundle->build($container);

        $this->addToAssertionCount(1);
    }
}
