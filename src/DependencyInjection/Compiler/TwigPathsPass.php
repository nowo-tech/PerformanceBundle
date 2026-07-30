<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function dirname;
use function is_dir;
use function is_string;
use function rtrim;

/**
 * REQ-TWIG-001: application overrides win. When {@code templates/bundles/NowoPerformanceBundle/}
 * exists, it is registered with {@code prependPath()} first; the bundle {@code Resources/views}
 * path is registered with {@code addPath()} so the app directory is searched before the vendor
 * copy for {@code @NowoPerformanceBundle/...}. Resolves {@code twig.loader.native} alias chains.
 */
final class TwigPathsPass implements CompilerPassInterface
{
    public const TWIG_NAMESPACE = 'NowoPerformanceBundle';

    public function process(ContainerBuilder $container): void
    {
        $loaderId = $this->getNativeLoaderServiceId($container);
        if ($loaderId === null) {
            return;
        }

        $viewsPath = dirname(__DIR__, 2) . '/Resources/views';

        $definition = $container->getDefinition($loaderId);

        if ($container->hasParameter('kernel.project_dir')) {
            $projectDirParam = $container->getParameter('kernel.project_dir');
            if (!is_string($projectDirParam)) {
                $definition->addMethodCall('addPath', [$viewsPath, self::TWIG_NAMESPACE]);

                return;
            }

            $projectDir   = rtrim($projectDirParam, '/\\');
            $overridePath = $projectDir . '/templates/bundles/NowoPerformanceBundle';
            if (is_dir($overridePath)) {
                $definition->addMethodCall('prependPath', [$overridePath, self::TWIG_NAMESPACE]);
            }
        }

        $definition->addMethodCall('addPath', [$viewsPath, self::TWIG_NAMESPACE]);
    }

    private function getNativeLoaderServiceId(ContainerBuilder $container): ?string
    {
        if ($container->hasAlias('twig.loader.native')) {
            $resolved = $this->resolveDefinitionId($container, (string) $container->getAlias('twig.loader.native'));
            if ($resolved !== null) {
                return $resolved;
            }
        }

        if ($container->hasDefinition('twig.loader.native')) {
            return 'twig.loader.native';
        }

        if ($container->hasDefinition('twig.loader.native_filesystem')) {
            return 'twig.loader.native_filesystem';
        }

        return null;
    }

    private function resolveDefinitionId(ContainerBuilder $container, string $id): ?string
    {
        for ($i = 0; $i < 32 && $container->hasAlias($id); ++$i) {
            $id = (string) $container->getAlias($id);
        }

        return $container->hasDefinition($id) ? $id : null;
    }
}
