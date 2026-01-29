<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection;

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
 * @copyright 2026 Nowo.tech
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
        $container->setParameter(Configuration::ALIAS.'.enabled', $config['enabled'] ?? true);
        $container->setParameter(Configuration::ALIAS.'.environments', $config['environments'] ?? ['prod', 'dev', 'test']);
        $container->setParameter(Configuration::ALIAS.'.connection', $config['connection'] ?? 'default');
        $container->setParameter(Configuration::ALIAS.'.table_name', $config['table_name'] ?? 'routes_data');
        $container->setParameter(Configuration::ALIAS.'.track_queries', $config['track_queries'] ?? true);
        $container->setParameter(Configuration::ALIAS.'.track_request_time', $config['track_request_time'] ?? true);
        $container->setParameter(Configuration::ALIAS.'.track_sub_requests', $config['track_sub_requests'] ?? false);
        $container->setParameter(Configuration::ALIAS.'.ignore_routes', $config['ignore_routes'] ?? []);
        $container->setParameter(Configuration::ALIAS.'.async', $config['async'] ?? false);
        $container->setParameter(Configuration::ALIAS.'.sampling_rate', $config['sampling_rate'] ?? 1.0);
        $container->setParameter(Configuration::ALIAS.'.query_tracking_threshold', $config['query_tracking_threshold'] ?? 0);
        $container->setParameter(Configuration::ALIAS.'.track_status_codes', $config['track_status_codes'] ?? [200, 404, 500, 503]);
        $container->setParameter(Configuration::ALIAS.'.enable_access_records', $config['enable_access_records'] ?? false);
        $container->setParameter(Configuration::ALIAS.'.track_user', $config['track_user'] ?? false);
        $container->setParameter(Configuration::ALIAS.'.enable_logging', $config['enable_logging'] ?? true);

        // Thresholds configuration
        $thresholdsConfig = $config['thresholds'] ?? [];
        $requestTimeThresholds = $thresholdsConfig['request_time'] ?? [];
        $queryCountThresholds = $thresholdsConfig['query_count'] ?? [];
        $memoryUsageThresholds = $thresholdsConfig['memory_usage'] ?? [];

        $thresholdsPath = Configuration::ALIAS.'.thresholds';
        $container->setParameter($thresholdsPath.'.request_time.warning', $requestTimeThresholds['warning'] ?? 0.5);
        $container->setParameter($thresholdsPath.'.request_time.critical', $requestTimeThresholds['critical'] ?? 1.0);
        $container->setParameter($thresholdsPath.'.query_count.warning', $queryCountThresholds['warning'] ?? 20);
        $container->setParameter($thresholdsPath.'.query_count.critical', $queryCountThresholds['critical'] ?? 50);
        $container->setParameter($thresholdsPath.'.memory_usage.warning', $memoryUsageThresholds['warning'] ?? 20.0);
        $container->setParameter($thresholdsPath.'.memory_usage.critical', $memoryUsageThresholds['critical'] ?? 50.0);

        // Dashboard configuration
        $dashboardConfig = $config['dashboard'] ?? [];
        $dashboardPath = Configuration::ALIAS.'.dashboard';
        $container->setParameter($dashboardPath.'.enabled', $dashboardConfig['enabled'] ?? true);
        $container->setParameter($dashboardPath.'.path', $dashboardConfig['path'] ?? '/performance');
        $container->setParameter($dashboardPath.'.prefix', $dashboardConfig['prefix'] ?? '');
        $container->setParameter($dashboardPath.'.roles', $dashboardConfig['roles'] ?? []);
        $container->setParameter($dashboardPath.'.template', $dashboardConfig['template'] ?? 'bootstrap');
        $container->setParameter($dashboardPath.'.enable_record_management', $dashboardConfig['enable_record_management'] ?? false);
        $container->setParameter($dashboardPath.'.enable_review_system', $dashboardConfig['enable_review_system'] ?? false);

        $dateFormatsConfig = $dashboardConfig['date_formats'] ?? [];
        $container->setParameter($dashboardPath.'.date_formats.datetime', $dateFormatsConfig['datetime'] ?? 'Y-m-d H:i:s');
        $container->setParameter($dashboardPath.'.date_formats.date', $dateFormatsConfig['date'] ?? 'Y-m-d H:i');
        $container->setParameter($dashboardPath.'.auto_refresh_interval', $dashboardConfig['auto_refresh_interval'] ?? 0);
        $container->setParameter($dashboardPath.'.enable_ranking_queries', $dashboardConfig['enable_ranking_queries'] ?? true);

        // Notifications configuration
        $notificationsConfig = $config['notifications'] ?? [];
        $notificationsPath = Configuration::ALIAS.'.notifications';
        $container->setParameter($notificationsPath.'.enabled', $notificationsConfig['enabled'] ?? false);

        $emailConfig = $notificationsConfig['email'] ?? [];
        $container->setParameter($notificationsPath.'.email.enabled', $emailConfig['enabled'] ?? false);
        $container->setParameter($notificationsPath.'.email.from', $emailConfig['from'] ?? 'noreply@example.com');
        $container->setParameter($notificationsPath.'.email.to', $emailConfig['to'] ?? []);

        $slackConfig = $notificationsConfig['slack'] ?? [];
        $container->setParameter($notificationsPath.'.slack.enabled', $slackConfig['enabled'] ?? false);
        $container->setParameter($notificationsPath.'.slack.webhook_url', $slackConfig['webhook_url'] ?? '');

        $teamsConfig = $notificationsConfig['teams'] ?? [];
        $container->setParameter($notificationsPath.'.teams.enabled', $teamsConfig['enabled'] ?? false);
        $container->setParameter($notificationsPath.'.teams.webhook_url', $teamsConfig['webhook_url'] ?? '');

        $webhookConfig = $notificationsConfig['webhook'] ?? [];
        $container->setParameter($notificationsPath.'.webhook.enabled', $webhookConfig['enabled'] ?? false);
        $container->setParameter($notificationsPath.'.webhook.url', $webhookConfig['url'] ?? '');
        $container->setParameter($notificationsPath.'.webhook.format', $webhookConfig['format'] ?? 'json');
        $container->setParameter($notificationsPath.'.webhook.headers', $webhookConfig['headers'] ?? []);
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

        // Middleware registration is handled via QueryTrackingConnectionSubscriber
        // which uses reflection to apply middleware at runtime.
        // This approach works across all DoctrineBundle versions (2.x and 3.x)
        // and avoids configuration issues with YAML middleware options that may
        // not be available in all versions.
    }
}
