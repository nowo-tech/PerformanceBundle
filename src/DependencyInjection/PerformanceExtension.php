<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection;

use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddlewareRegistry;
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
     * @param array<string, mixed> $configs   The configuration array
     * @param ContainerBuilder     $container The container builder
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
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
        $container->setParameter('nowo_performance.async', $config['async'] ?? false);
        $container->setParameter('nowo_performance.sampling_rate', $config['sampling_rate'] ?? 1.0);
        $container->setParameter('nowo_performance.query_tracking_threshold', $config['query_tracking_threshold'] ?? 0);
        $container->setParameter('nowo_performance.track_status_codes', $config['track_status_codes'] ?? [200, 404, 500, 503]);
        $container->setParameter('nowo_performance.enable_access_records', $config['enable_access_records'] ?? false);

        // Thresholds configuration
        $thresholdsConfig = $config['thresholds'] ?? [];
        $requestTimeThresholds = $thresholdsConfig['request_time'] ?? [];
        $queryCountThresholds = $thresholdsConfig['query_count'] ?? [];
        $memoryUsageThresholds = $thresholdsConfig['memory_usage'] ?? [];

        $container->setParameter('nowo_performance.thresholds.request_time.warning', $requestTimeThresholds['warning'] ?? 0.5);
        $container->setParameter('nowo_performance.thresholds.request_time.critical', $requestTimeThresholds['critical'] ?? 1.0);
        $container->setParameter('nowo_performance.thresholds.query_count.warning', $queryCountThresholds['warning'] ?? 20);
        $container->setParameter('nowo_performance.thresholds.query_count.critical', $queryCountThresholds['critical'] ?? 50);
        $container->setParameter('nowo_performance.thresholds.memory_usage.warning', $memoryUsageThresholds['warning'] ?? 20.0);
        $container->setParameter('nowo_performance.thresholds.memory_usage.critical', $memoryUsageThresholds['critical'] ?? 50.0);

        // Dashboard configuration
        $dashboardConfig = $config['dashboard'] ?? [];
        $container->setParameter('nowo_performance.dashboard.enabled', $dashboardConfig['enabled'] ?? true);
        $container->setParameter('nowo_performance.dashboard.path', $dashboardConfig['path'] ?? '/performance');
        $container->setParameter('nowo_performance.dashboard.prefix', $dashboardConfig['prefix'] ?? '');
        $container->setParameter('nowo_performance.dashboard.roles', $dashboardConfig['roles'] ?? []);
        $container->setParameter('nowo_performance.dashboard.template', $dashboardConfig['template'] ?? 'bootstrap');
        $container->setParameter('nowo_performance.dashboard.enable_record_management', $dashboardConfig['enable_record_management'] ?? false);
        $container->setParameter('nowo_performance.dashboard.enable_review_system', $dashboardConfig['enable_review_system'] ?? false);
        
        $dateFormatsConfig = $dashboardConfig['date_formats'] ?? [];
        $container->setParameter('nowo_performance.dashboard.date_formats.datetime', $dateFormatsConfig['datetime'] ?? 'Y-m-d H:i:s');
        $container->setParameter('nowo_performance.dashboard.date_formats.date', $dateFormatsConfig['date'] ?? 'Y-m-d H:i');
        $container->setParameter('nowo_performance.dashboard.auto_refresh_interval', $dashboardConfig['auto_refresh_interval'] ?? 0);

        // Notifications configuration
        $notificationsConfig = $config['notifications'] ?? [];
        $container->setParameter('nowo_performance.notifications.enabled', $notificationsConfig['enabled'] ?? false);
        
        $emailConfig = $notificationsConfig['email'] ?? [];
        $container->setParameter('nowo_performance.notifications.email.enabled', $emailConfig['enabled'] ?? false);
        $container->setParameter('nowo_performance.notifications.email.from', $emailConfig['from'] ?? 'noreply@example.com');
        $container->setParameter('nowo_performance.notifications.email.to', $emailConfig['to'] ?? []);
        
        $slackConfig = $notificationsConfig['slack'] ?? [];
        $container->setParameter('nowo_performance.notifications.slack.enabled', $slackConfig['enabled'] ?? false);
        $container->setParameter('nowo_performance.notifications.slack.webhook_url', $slackConfig['webhook_url'] ?? '');
        
        $teamsConfig = $notificationsConfig['teams'] ?? [];
        $container->setParameter('nowo_performance.notifications.teams.enabled', $teamsConfig['enabled'] ?? false);
        $container->setParameter('nowo_performance.notifications.teams.webhook_url', $teamsConfig['webhook_url'] ?? '');
        
        $webhookConfig = $notificationsConfig['webhook'] ?? [];
        $container->setParameter('nowo_performance.notifications.webhook.enabled', $webhookConfig['enabled'] ?? false);
        $container->setParameter('nowo_performance.notifications.webhook.url', $webhookConfig['url'] ?? '');
        $container->setParameter('nowo_performance.notifications.webhook.format', $webhookConfig['format'] ?? 'json');
        $container->setParameter('nowo_performance.notifications.webhook.headers', $webhookConfig['headers'] ?? []);
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

    public function getConfiguration(array $config, ContainerBuilder $container): ?Configuration
    {
        return new Configuration();
    }

    /**
     * Prepend Twig and Doctrine configuration.
     *
     * @param ContainerBuilder $container The container builder
     */
    public function prepend(ContainerBuilder $container): void
    {
        // Prepend Twig configuration
        if ($container->hasExtension('twig')) {
            $bundleDir = \dirname(__DIR__, 2);
            $viewsPath = $bundleDir.'/src/Resources/views';

            $container->prependExtensionConfig('twig', [
                'paths' => [
                    $viewsPath => 'NowoPerformanceBundle',
                ],
            ]);
        }

        // Register QueryTrackingMiddleware via YAML if DoctrineBundle version supports it
        // DoctrineBundle 2.x supports 'middlewares' in YAML, 3.x does not
        if ($container->hasExtension('doctrine')) {
            // Get configuration to check if tracking is enabled
            $configs = $container->getExtensionConfig($this->getAlias());
            $enabled = true;
            $trackQueries = true;
            $connectionName = 'default';

            foreach ($configs as $config) {
                if (isset($config['enabled'])) {
                    $enabled = $config['enabled'];
                }
                if (isset($config['track_queries'])) {
                    $trackQueries = $config['track_queries'];
                }
                if (isset($config['connection'])) {
                    $connectionName = $config['connection'];
                }
            }

            // Only register middleware if tracking is enabled
            if ($enabled && $trackQueries) {
                // Check existing Doctrine config to avoid overwriting
                $existingConfigs = $container->getExtensionConfig('doctrine');
                $hasMiddleware = false;
                $hasYamlMiddleware = false;

                // Check if middleware is already registered via 'middlewares' or 'yamlMiddleware'
                foreach ($existingConfigs as $config) {
                    if (isset($config['dbal']['connections'][$connectionName])) {
                        $connectionConfig = $config['dbal']['connections'][$connectionName];
                        
                        // Check for 'middlewares' (DoctrineBundle 2.x)
                        if (isset($connectionConfig['middlewares'])) {
                            $middlewares = $connectionConfig['middlewares'];
                            if (is_array($middlewares) && in_array(QueryTrackingMiddleware::class, $middlewares, true)) {
                                $hasMiddleware = true;
                                break;
                            }
                        }
                        
                        // Check for 'yamlMiddleware' (DoctrineBundle 2.10.0+)
                        if (isset($connectionConfig['yamlMiddleware'])) {
                            $yamlMiddlewares = $connectionConfig['yamlMiddleware'];
                            if (is_array($yamlMiddlewares) && in_array(QueryTrackingMiddleware::class, $yamlMiddlewares, true)) {
                                $hasYamlMiddleware = true;
                                break;
                            }
                        }
                    }
                }

                // Only prepend if not already registered
                if (!$hasMiddleware && !$hasYamlMiddleware) {
                    // Use 'middlewares' for DoctrineBundle 2.x (more widely supported than yamlMiddleware)
                    // For DoctrineBundle 3.x, middleware is applied via QueryTrackingConnectionSubscriber
                    if (QueryTrackingMiddlewareRegistry::supportsYamlMiddlewareConfig()) {
                        $doctrineConfig = [
                            'dbal' => [
                                'connections' => [
                                    $connectionName => [
                                        'middlewares' => [
                                            QueryTrackingMiddleware::class,
                                        ],
                                    ],
                                ],
                            ],
                        ];
                        $container->prependExtensionConfig('doctrine', $doctrineConfig);
                    }
                    // For DoctrineBundle 3.x, middleware is applied via QueryTrackingConnectionSubscriber
                    // which uses reflection to wrap the driver after connection creation
                }
            }
            // For DoctrineBundle 3.x, middleware is applied via QueryTrackingConnectionSubscriber
            // which uses reflection to wrap the driver after connection creation
        }
    }
}
