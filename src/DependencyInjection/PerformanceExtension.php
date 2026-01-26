<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection;

use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Extension for loading the bundle configuration.
 *
 * This extension loads the services configuration and processes the bundle configuration.
 * It registers all services defined in the services.yaml file.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
final class PerformanceExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Loads the bundle configuration and services.
     *
     * Processes the bundle configuration and sets container parameters
     * for all configurable options.
     *
     * @param array<string, mixed> $configs The configuration array
     * @param ContainerBuilder $container The container builder
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        // Set configuration parameters
        $container->setParameter('nowo_performance.enabled', $config['enabled'] ?? true);
        $container->setParameter('nowo_performance.environments', $config['environments'] ?? ['dev', 'test']);
        $container->setParameter('nowo_performance.connection', $config['connection'] ?? 'default');
        $container->setParameter('nowo_performance.table_name', $config['table_name'] ?? 'routes_data');
        $container->setParameter('nowo_performance.track_queries', $config['track_queries'] ?? true);
        $container->setParameter('nowo_performance.track_request_time', $config['track_request_time'] ?? true);
        $container->setParameter('nowo_performance.ignore_routes', $config['ignore_routes'] ?? []);

            // Dashboard configuration
            $dashboardConfig = $config['dashboard'] ?? [];
            $container->setParameter('nowo_performance.dashboard.enabled', $dashboardConfig['enabled'] ?? true);
            $container->setParameter('nowo_performance.dashboard.path', $dashboardConfig['path'] ?? '/performance');
            $container->setParameter('nowo_performance.dashboard.prefix', $dashboardConfig['prefix'] ?? '');
            $container->setParameter('nowo_performance.dashboard.roles', $dashboardConfig['roles'] ?? []);
    }

    /**
     * Returns the extension alias.
     *
     * @return string The extension alias
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container): ?Configuration
    {
        return new Configuration();
    }

    /**
     * Prepend Twig and Doctrine configuration.
     *
     * @param ContainerBuilder $container The container builder
     * @return void
     */
    public function prepend(ContainerBuilder $container): void
    {
        // Prepend Twig configuration
        if ($container->hasExtension('twig')) {
            $bundleDir = \dirname(__DIR__, 2);
            $viewsPath = $bundleDir . '/src/Resources/views';

            $container->prependExtensionConfig('twig', [
                'paths' => [
                    $viewsPath => 'NowoPerformanceBundle',
                ],
            ]);
        }

        // Note: QueryTrackingMiddleware is registered via services.yaml
        // and will be automatically used by Doctrine if configured
        // The middleware uses static methods so it doesn't need to be injected
    }
}
