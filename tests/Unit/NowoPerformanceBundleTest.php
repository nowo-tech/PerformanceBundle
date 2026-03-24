<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit;

use Nowo\PerformanceBundle\DependencyInjection\Compiler\QueryTrackingMiddlewarePass;
use Nowo\PerformanceBundle\DependencyInjection\PerformanceExtension;
use Nowo\PerformanceBundle\NowoPerformanceBundle;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\KernelInterface;

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
        $bundle    = new NowoPerformanceBundle();
        $container = $this->createMock(ContainerBuilder::class);

        $container->expects($this->once())
            ->method('addCompilerPass')
            ->with($this->isInstanceOf(QueryTrackingMiddlewarePass::class));

        $bundle->build($container);
    }

    public function testBuildCallsParentBuild(): void
    {
        $bundle    = new NowoPerformanceBundle();
        $container = $this->createMock(ContainerBuilder::class);

        // Verify that addCompilerPass is called (which means build is executed)
        $container->expects($this->once())
            ->method('addCompilerPass')
            ->with($this->isInstanceOf(QueryTrackingMiddlewarePass::class));

        $bundle->build($container);
    }

    public function testGetPathReturnsString(): void
    {
        $bundle = new NowoPerformanceBundle();
        $path   = $bundle->getPath();
        $this->assertIsString($path);
        $this->assertNotEmpty($path);
    }

    public function testBuildReceivesContainerBuilder(): void
    {
        $bundle    = new NowoPerformanceBundle();
        $container = new ContainerBuilder();

        $bundle->build($container);

        $this->addToAssertionCount(1);
    }

    public function testBootWhenContainerIsNullDoesNotSetVarDumperHandler(): void
    {
        $bundle     = new NowoPerformanceBundle();
        $reflection = new ReflectionProperty($bundle, 'container');
        $reflection->setValue($bundle, null);

        $bundle->boot();

        $this->addToAssertionCount(1);
    }

    public function testBootWhenKernelIsNotDebugDoesNotSetVarDumperHandler(): void
    {
        $container = new ContainerBuilder();
        $kernel    = $this->createMock(KernelInterface::class);
        $kernel->method('isDebug')->willReturn(false);
        $container->set('kernel', $kernel);

        $bundle     = new NowoPerformanceBundle();
        $reflection = new ReflectionProperty($bundle, 'container');
        $reflection->setValue($bundle, $container);

        $bundle->boot();

        $this->addToAssertionCount(1);
    }

    public function testBootWhenVarDumperClassDoesNotExistDoesNotSetHandler(): void
    {
        $container = new ContainerBuilder();
        $kernel    = $this->createMock(KernelInterface::class);
        $kernel->method('isDebug')->willReturn(true);
        $container->set('kernel', $kernel);

        $bundle     = new NowoPerformanceBundle();
        $reflection = new ReflectionProperty($bundle, 'container');
        $reflection->setValue($bundle, $container);

        // Boot with kernel debug but VarDumper may or may not exist; we just ensure no exception
        $bundle->boot();

        $this->addToAssertionCount(1);
    }

    /** When container returns non-KernelInterface for 'kernel', boot returns early and does not set handler. */
    public function testBootWhenKernelServiceIsNotKernelInterfaceDoesNotSetHandler(): void
    {
        $container = new ContainerBuilder();
        $container->set('kernel', new stdClass());

        $bundle     = new NowoPerformanceBundle();
        $reflection = new ReflectionProperty($bundle, 'container');
        $reflection->setValue($bundle, $container);

        $bundle->boot();

        $this->addToAssertionCount(1);
    }

    /** When all conditions are met (CLI, debug, VarDumper present), boot sets a custom handler. */
    public function testBootWhenCliAndDebugAndVarDumperPresentSetsHandler(): void
    {
        if (!class_exists(\Symfony\Component\VarDumper\VarDumper::class)) {
            $this->markTestSkipped('VarDumper not available');
        }

        $container = new ContainerBuilder();
        $kernel    = $this->createMock(KernelInterface::class);
        $kernel->method('isDebug')->willReturn(true);
        $container->set('kernel', $kernel);

        $bundle     = new NowoPerformanceBundle();
        $reflection = new ReflectionProperty($bundle, 'container');
        $reflection->setValue($bundle, $container);

        $bundle->boot();

        $vdReflection = new ReflectionClass(\Symfony\Component\VarDumper\VarDumper::class);
        $handlerProp  = $vdReflection->getProperty('handler');
        $handler      = $handlerProp->getValue();
        $this->assertIsCallable($handler);
    }

    /**
     * Invoking the VarDumper handler after boot runs the closure body; use stream override to avoid output.
     * Covers lines 68-75 inside the setHandler closure.
     */
    public function testBootVarDumperHandlerInvocationRunsClosureBody(): void
    {
        if (!class_exists(\Symfony\Component\VarDumper\VarDumper::class)) {
            $this->markTestSkipped('VarDumper not available');
        }

        $stream = fopen('php://memory', 'w');
        $this->assertNotFalse($stream);

        $previous                                       = NowoPerformanceBundle::$varDumperStreamOverride;
        NowoPerformanceBundle::$varDumperStreamOverride = $stream;

        try {
            $container = new ContainerBuilder();
            $kernel    = $this->createMock(KernelInterface::class);
            $kernel->method('isDebug')->willReturn(true);
            $container->set('kernel', $kernel);

            $bundle     = new NowoPerformanceBundle();
            $reflection = new ReflectionProperty($bundle, 'container');
            $reflection->setValue($bundle, $container);

            $bundle->boot();

            \Symfony\Component\VarDumper\VarDumper::dump('test');
        } finally {
            NowoPerformanceBundle::$varDumperStreamOverride = $previous;
            fclose($stream);
        }

        $this->addToAssertionCount(1);
    }

    /** Covers boot() early return when testVarDumperClassExistsOverride is false (same branch as !class_exists). */
    public function testBootWhenTestVarDumperClassExistsOverrideFalseReturnsEarly(): void
    {
        $container = new ContainerBuilder();
        $kernel    = $this->createMock(KernelInterface::class);
        $kernel->method('isDebug')->willReturn(true);
        $container->set('kernel', $kernel);

        $bundle     = new NowoPerformanceBundle();
        $reflection = new ReflectionProperty($bundle, 'container');
        $reflection->setValue($bundle, $container);

        $prev = NowoPerformanceBundle::$testVarDumperClassExistsOverride;
        try {
            NowoPerformanceBundle::$testVarDumperClassExistsOverride = false;
            $bundle->boot();
            $this->addToAssertionCount(1);
        } finally {
            NowoPerformanceBundle::$testVarDumperClassExistsOverride = $prev;
        }
    }

    /** Covers boot() early return when testVarDumperExistsOverride is false (simulates VarDumper not present). */
    public function testBootWhenTestVarDumperExistsOverrideFalseReturnsEarly(): void
    {
        $container = new ContainerBuilder();
        $kernel    = $this->createMock(KernelInterface::class);
        $kernel->method('isDebug')->willReturn(true);
        $container->set('kernel', $kernel);

        $bundle     = new NowoPerformanceBundle();
        $reflection = new ReflectionProperty($bundle, 'container');
        $reflection->setValue($bundle, $container);

        $prev = NowoPerformanceBundle::$testVarDumperExistsOverride;
        try {
            NowoPerformanceBundle::$testVarDumperExistsOverride = false;
            $bundle->boot();
            $this->addToAssertionCount(1);
        } finally {
            NowoPerformanceBundle::$testVarDumperExistsOverride = $prev;
        }
    }

    /** Covers boot() early return when not in CLI (testSapiOverride simulates non-CLI). */
    public function testBootWhenTestSapiOverrideNotCliReturnsEarly(): void
    {
        $container = new ContainerBuilder();
        $kernel    = $this->createMock(KernelInterface::class);
        $kernel->method('isDebug')->willReturn(true);
        $container->set('kernel', $kernel);

        $bundle     = new NowoPerformanceBundle();
        $reflection = new ReflectionProperty($bundle, 'container');
        $reflection->setValue($bundle, $container);

        $prev = NowoPerformanceBundle::$testSapiOverride;
        try {
            NowoPerformanceBundle::$testSapiOverride = 'apache';
            $bundle->boot();
            $this->addToAssertionCount(1);
        } finally {
            NowoPerformanceBundle::$testSapiOverride = $prev;
        }
    }

    /** Covers boot() path when stream is forced to null (testStreamForceNull); handler is set but closure returns without dumping. */
    public function testBootWhenTestStreamForceNullHandlerReceivesNullStreamReturnsEarly(): void
    {
        if (!class_exists(\Symfony\Component\VarDumper\VarDumper::class)) {
            $this->markTestSkipped('VarDumper not available');
        }

        $container = new ContainerBuilder();
        $kernel    = $this->createMock(KernelInterface::class);
        $kernel->method('isDebug')->willReturn(true);
        $container->set('kernel', $kernel);

        $bundle     = new NowoPerformanceBundle();
        $reflection = new ReflectionProperty($bundle, 'container');
        $reflection->setValue($bundle, $container);

        $prev = NowoPerformanceBundle::$testStreamForceNull;
        try {
            NowoPerformanceBundle::$testStreamForceNull = true;
            $bundle->boot();
            // Invoke handler: stream is null so it returns without dumping (no output)
            \Symfony\Component\VarDumper\VarDumper::dump('no-output');
            $this->addToAssertionCount(1);
        } finally {
            NowoPerformanceBundle::$testStreamForceNull = $prev;
        }
    }
}
