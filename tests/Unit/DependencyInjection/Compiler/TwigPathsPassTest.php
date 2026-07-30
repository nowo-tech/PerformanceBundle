<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\PerformanceBundle\DependencyInjection\Compiler\TwigPathsPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function dirname;

#[CoversClass(TwigPathsPass::class)]
final class TwigPathsPassTest extends TestCase
{
    public function testProcessAddsOnlyVendorPathWhenOverrideDirectoryMissing(): void
    {
        $tmp = sys_get_temp_dir() . '/perf_twig_pass_' . bin2hex(random_bytes(4));
        self::assertTrue(mkdir($tmp, 0777, true));

        try {
            $container = $this->createContainerWithLoader($tmp);
            (new TwigPathsPass())->process($container);

            $loaderDef = $container->getDefinition('twig.loader.native_filesystem');
            $calls     = $loaderDef->getMethodCalls();

            self::assertSame(
                [['addPath', $this->expectedVendorAddPathArgs()]],
                $calls,
            );
        } finally {
            self::removeDir($tmp);
        }
    }

    public function testProcessPrependsOverrideThenAddsVendorPathWhenOverrideDirectoryExists(): void
    {
        $tmp          = sys_get_temp_dir() . '/perf_twig_pass_' . bin2hex(random_bytes(4));
        $overridePath = $tmp . '/templates/bundles/NowoPerformanceBundle';
        self::assertTrue(mkdir($overridePath, 0777, true));

        try {
            $container = $this->createContainerWithLoader($tmp);
            (new TwigPathsPass())->process($container);

            $loaderDef = $container->getDefinition('twig.loader.native_filesystem');
            $calls     = $loaderDef->getMethodCalls();

            self::assertSame(
                [
                    ['prependPath', [$overridePath, TwigPathsPass::TWIG_NAMESPACE]],
                    ['addPath', $this->expectedVendorAddPathArgs()],
                ],
                $calls,
            );
        } finally {
            self::removeDir($tmp);
        }
    }

    public function testProcessUsesTwigLoaderNativeWhenAliasExists(): void
    {
        $tmp = sys_get_temp_dir() . '/perf_twig_pass_' . bin2hex(random_bytes(4));
        self::assertTrue(mkdir($tmp, 0777, true));

        try {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.project_dir', $tmp);
            $loaderDef = new Definition();
            $container->setDefinition('twig.loader.native_filesystem', $loaderDef);
            $container->setAlias('twig.loader.native', 'twig.loader.native_filesystem');

            (new TwigPathsPass())->process($container);

            $addPathCalls = array_filter(
                $loaderDef->getMethodCalls(),
                static fn (array $c): bool => $c[0] === 'addPath' && ($c[1][1] ?? '') === TwigPathsPass::TWIG_NAMESPACE,
            );
            self::assertCount(1, $addPathCalls);
        } finally {
            self::removeDir($tmp);
        }
    }

    public function testProcessDoesNothingWhenTwigLoaderNotDefined(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        (new TwigPathsPass())->process($container);

        self::assertFalse($container->hasDefinition('twig.loader.native_filesystem'));
    }

    public function testProcessUsesNativeLoaderDefinitionWhenRegisteredWithoutAlias(): void
    {
        $tmp       = sys_get_temp_dir() . '/perf_twig_native_' . bin2hex(random_bytes(4));
        $loaderDef = new Definition();
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $tmp);
        $container->setDefinition('twig.loader.native', $loaderDef);

        try {
            self::assertTrue(mkdir($tmp, 0777, true));
            (new TwigPathsPass())->process($container);
            $calls = array_filter(
                $loaderDef->getMethodCalls(),
                static fn (array $c): bool => ($c[0] ?? '') === 'addPath',
            );
            self::assertCount(1, $calls);
        } finally {
            self::removeDir($tmp);
        }
    }

    public function testProcessResolvesChainedLoaderAlias(): void
    {
        $tmp = sys_get_temp_dir() . '/perf_twig_chain_' . bin2hex(random_bytes(4));
        self::assertTrue(mkdir($tmp, 0777, true));

        try {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.project_dir', $tmp);
            $targetDef = new Definition();
            $container->setDefinition('twig.loader.native_filesystem', $targetDef);
            $container->setAlias('twig.loader.native', 'twig.loader.chain_a');
            $container->setAlias('twig.loader.chain_a', 'twig.loader.native_filesystem');

            (new TwigPathsPass())->process($container);

            $addPathCalls = array_filter(
                $targetDef->getMethodCalls(),
                static fn (array $c): bool => ($c[0] ?? '') === 'addPath',
            );
            self::assertCount(1, $addPathCalls);
        } finally {
            self::removeDir($tmp);
        }
    }

    public function testProcessAddsVendorPathWhenProjectDirParameterIsNotString(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', ['invalid']);
        $loaderDef = new Definition();
        $container->setDefinition('twig.loader.native_filesystem', $loaderDef);

        (new TwigPathsPass())->process($container);

        self::assertSame(
            [['addPath', $this->expectedVendorAddPathArgs()]],
            $loaderDef->getMethodCalls(),
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function expectedVendorAddPathArgs(): array
    {
        $bundleRoot = dirname(__DIR__, 4);
        $viewsPath  = $bundleRoot . '/src/Resources/views';

        return [$viewsPath, TwigPathsPass::TWIG_NAMESPACE];
    }

    private function createContainerWithLoader(string $projectDir): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $projectDir);
        $container->setDefinition('twig.loader.native_filesystem', new Definition());

        return $container;
    }

    private static function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . '/' . $item;
            is_dir($full) ? self::removeDir($full) : @unlink($full);
        }

        @rmdir($path);
    }
}
