<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection;

use LogicException;
use Nowo\PerformanceBundle\Security\AllowAllPerformanceAccessChecker;
use Nowo\PerformanceBundle\Security\ConfigurablePerformanceAccessChecker;
use Nowo\PerformanceBundle\Security\PerformanceAccessCheckerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\UX\TwigComponent\ComponentInterface;

use function is_string;

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
     * @param array<string, mixed> $configs The configuration array
     * @param ContainerBuilder $container The container builder
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        // Optional: Symfony UX Twig Component (dashboard uses {{ component() }} when available; else includes).
        if (interface_exists(ComponentInterface::class)) {
            $loader->load('services_twig_component.yaml'); // @codeCoverageIgnore – symfony/ux-twig-component is a suggest, not required
        }

        $configuration = $this->getConfiguration($configs, $container);
        $config        = $this->processConfiguration($configuration, $configs);

        $dashboardConfig = $config['dashboard'] ?? [];
        $securityConfig  = $config['security'] ?? [
            'access_roles'          => ['ROLE_ADMIN'],
            'access_checker'        => null,
            'allow_unauthenticated' => false,
        ];

        if (
            ($dashboardConfig['enabled'] ?? true)
            && !($securityConfig['allow_unauthenticated'] ?? false)
            && !$this->isSecurityBundleAvailable($container)
        ) {
            throw new LogicException('NowoPerformanceBundle dashboard requires symfony/security-bundle when security.allow_unauthenticated is false.');
        }

        // Set configuration parameters
        $container->setParameter(Configuration::ALIAS . '.enabled', $config['enabled'] ?? true);
        $container->setParameter(Configuration::ALIAS . '.environments', $config['environments'] ?? ['prod', 'dev', 'test']);
        $container->setParameter(Configuration::ALIAS . '.connection', $config['connection'] ?? 'default');
        $container->setParameter(Configuration::ALIAS . '.table_name', $config['table_name'] ?? 'routes_data');
        $container->setParameter(Configuration::ALIAS . '.track_queries', $config['track_queries'] ?? true);
        $container->setParameter(Configuration::ALIAS . '.track_request_time', $config['track_request_time'] ?? true);
        $container->setParameter(Configuration::ALIAS . '.track_sub_requests', $config['track_sub_requests'] ?? false);
        $container->setParameter(Configuration::ALIAS . '.ignore_routes', $config['ignore_routes'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.async', $config['async'] ?? false);
        $container->setParameter(Configuration::ALIAS . '.sampling_rate', $config['sampling_rate'] ?? 1.0);
        $container->setParameter(Configuration::ALIAS . '.query_tracking_threshold', $config['query_tracking_threshold'] ?? 0);
        $container->setParameter(Configuration::ALIAS . '.track_status_codes', $config['track_status_codes'] ?? [200, 404, 500, 503]);
        $container->setParameter(Configuration::ALIAS . '.enable_access_records', $config['enable_access_records'] ?? false);
        $container->setParameter(Configuration::ALIAS . '.access_records_retention_days', $config['access_records_retention_days'] ?? null);
        $container->setParameter(Configuration::ALIAS . '.track_user', $config['track_user'] ?? false);
        $container->setParameter(Configuration::ALIAS . '.enable_logging', $config['enable_logging'] ?? true);
        $container->setParameter(Configuration::ALIAS . '.check_table_status', $config['check_table_status'] ?? true);

        $cacheConfig = $config['cache'] ?? [];
        $container->setParameter(Configuration::ALIAS . '.cache.pool', $cacheConfig['pool'] ?? 'nowo_performance.cache');

        // Thresholds configuration
        $thresholdsConfig      = $config['thresholds'] ?? [];
        $requestTimeThresholds = $thresholdsConfig['request_time'] ?? [];
        $queryCountThresholds  = $thresholdsConfig['query_count'] ?? [];
        $memoryUsageThresholds = $thresholdsConfig['memory_usage'] ?? [];

        $thresholdsPath = Configuration::ALIAS . '.thresholds';
        $container->setParameter($thresholdsPath . '.request_time.warning', $requestTimeThresholds['warning'] ?? 0.5);
        $container->setParameter($thresholdsPath . '.request_time.critical', $requestTimeThresholds['critical'] ?? 1.0);
        $container->setParameter($thresholdsPath . '.query_count.warning', $queryCountThresholds['warning'] ?? 20);
        $container->setParameter($thresholdsPath . '.query_count.critical', $queryCountThresholds['critical'] ?? 50);
        $container->setParameter($thresholdsPath . '.memory_usage.warning', $memoryUsageThresholds['warning'] ?? 20.0);
        $container->setParameter($thresholdsPath . '.memory_usage.critical', $memoryUsageThresholds['critical'] ?? 50.0);

        // Dashboard configuration
        $dashboardPath = Configuration::ALIAS . '.dashboard';
        $accessRoles   = $securityConfig['access_roles'] ?? ['ROLE_ADMIN'];
        $container->setParameter($dashboardPath . '.enabled', $dashboardConfig['enabled'] ?? true);
        $container->setParameter($dashboardPath . '.path', $dashboardConfig['path'] ?? '/performance');
        $container->setParameter($dashboardPath . '.prefix', $dashboardConfig['prefix'] ?? '');
        // BC: keep dashboard.roles in sync with effective security.access_roles
        $container->setParameter($dashboardPath . '.roles', $accessRoles);
        $cssFramework = $dashboardConfig['css_framework'] ?? 'bootstrap5';
        if ($cssFramework === 'bootstrap') {
            $cssFramework = 'bootstrap5';
        }
        // BC: keep dashboard.template as bootstrap|tailwind markup family synced from css_framework
        $templateFamily = $cssFramework === 'tailwind' ? 'tailwind' : 'bootstrap';
        $container->setParameter($dashboardPath . '.css_framework', $cssFramework);
        $container->setParameter($dashboardPath . '.template', $templateFamily);
        $container->setParameter($dashboardPath . '.enable_record_management', $dashboardConfig['enable_record_management'] ?? false);
        $container->setParameter($dashboardPath . '.enable_review_system', $dashboardConfig['enable_review_system'] ?? false);
        $container->setParameter(
            $dashboardPath . '.layout_template',
            $dashboardConfig['layout_template'] ?? '@NowoPerformanceBundle/Performance/layout.html.twig',
        );

        $dateFormatsConfig = $dashboardConfig['date_formats'] ?? [];
        $container->setParameter($dashboardPath . '.date_formats.datetime', $dateFormatsConfig['datetime'] ?? 'Y-m-d H:i:s');
        $container->setParameter($dashboardPath . '.date_formats.date', $dateFormatsConfig['date'] ?? 'Y-m-d H:i');
        $container->setParameter($dashboardPath . '.auto_refresh_interval', $dashboardConfig['auto_refresh_interval'] ?? 0);
        $container->setParameter($dashboardPath . '.enable_ranking_queries', $dashboardConfig['enable_ranking_queries'] ?? true);

        $securityPath = Configuration::ALIAS . '.security';
        $container->setParameter($securityPath, $securityConfig);
        $container->setParameter($securityPath . '.access_roles', $accessRoles);
        $container->setParameter($securityPath . '.access_checker', $securityConfig['access_checker'] ?? null);
        $container->setParameter($securityPath . '.allow_unauthenticated', $securityConfig['allow_unauthenticated'] ?? false);

        $this->registerAccessChecker($container, $securityConfig);

        // Notifications configuration
        $notificationsConfig = $config['notifications'] ?? [];
        $notificationsPath   = Configuration::ALIAS . '.notifications';
        $container->setParameter($notificationsPath . '.enabled', $notificationsConfig['enabled'] ?? false);
        $container->setParameter($notificationsPath . '.http_timeout', $notificationsConfig['http_timeout'] ?? 10.0);

        $emailConfig = $notificationsConfig['email'] ?? [];
        $container->setParameter($notificationsPath . '.email.enabled', $emailConfig['enabled'] ?? false);
        $container->setParameter($notificationsPath . '.email.from', $emailConfig['from'] ?? 'noreply@example.com');
        $container->setParameter($notificationsPath . '.email.to', $emailConfig['to'] ?? []);

        $slackConfig = $notificationsConfig['slack'] ?? [];
        $container->setParameter($notificationsPath . '.slack.enabled', $slackConfig['enabled'] ?? false);
        $container->setParameter($notificationsPath . '.slack.webhook_url', $slackConfig['webhook_url'] ?? '');

        $teamsConfig = $notificationsConfig['teams'] ?? [];
        $container->setParameter($notificationsPath . '.teams.enabled', $teamsConfig['enabled'] ?? false);
        $container->setParameter($notificationsPath . '.teams.webhook_url', $teamsConfig['webhook_url'] ?? '');

        $webhookConfig = $notificationsConfig['webhook'] ?? [];
        $container->setParameter($notificationsPath . '.webhook.enabled', $webhookConfig['enabled'] ?? false);
        $container->setParameter($notificationsPath . '.webhook.url', $webhookConfig['url'] ?? '');
        $container->setParameter($notificationsPath . '.webhook.format', $webhookConfig['format'] ?? 'json');
        $container->setParameter($notificationsPath . '.webhook.headers', $webhookConfig['headers'] ?? []);
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
     * @param array<string, mixed> $config
     */
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration();
    }

    /**
     * @param array{access_checker?: ?string, access_roles?: list<string>, allow_unauthenticated?: bool} $security
     */
    private function registerAccessChecker(ContainerBuilder $container, array $security): void
    {
        if ($security['allow_unauthenticated'] ?? false) {
            $accessCheckerId = 'nowo_performance.access_checker.allow_all';
            $container->setDefinition($accessCheckerId, new Definition(AllowAllPerformanceAccessChecker::class));
            $container->setAlias(PerformanceAccessCheckerInterface::class, $accessCheckerId);

            return;
        }

        $accessCheckerId = $security['access_checker'] ?? null;
        if (is_string($accessCheckerId) && $accessCheckerId !== '') {
            $container->setAlias(PerformanceAccessCheckerInterface::class, $accessCheckerId);

            return;
        }

        $hasAuthorizationChecker = $container->hasDefinition('security.authorization_checker')
            || $container->hasAlias('security.authorization_checker');

        $accessCheckerId = 'nowo_performance.access_checker.default';
        $definition      = new Definition(ConfigurablePerformanceAccessChecker::class);
        $definition->setArgument('$accessRoles', $security['access_roles'] ?? ['ROLE_ADMIN']);
        if ($hasAuthorizationChecker) {
            $definition->setArgument('$authorizationChecker', new Reference('security.authorization_checker'));
        } else {
            $definition->setAutowired(true);
        }
        $container->setDefinition($accessCheckerId, $definition);
        $container->setAlias(PerformanceAccessCheckerInterface::class, $accessCheckerId);
    }

    /**
     * Prefer kernel.bundles: ContainerBuilder::hasExtension() can be false while SecurityBundle
     * is already registered (e.g. during early Flex cache:clear boots).
     */
    private function isSecurityBundleAvailable(ContainerBuilder $container): bool
    {
        if ($container->hasExtension('security')) {
            return true;
        }

        if (!$container->hasParameter('kernel.bundles')) {
            return false;
        }

        /** @var array<string, class-string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        return isset($bundles['SecurityBundle']);
    }

    /**
     * Prepend cache pool configuration.
     *
     * Twig paths are registered by {@see Compiler\TwigPathsPass} (REQ-TWIG-001),
     * not via prependExtensionConfig('twig', ['paths' => ...]).
     *
     * @param ContainerBuilder $container The container builder
     */
    public function prepend(ContainerBuilder $container): void
    {
        // Prepend dedicated cache pool for Performance Bundle (filesystem, 1h default TTL)
        if ($container->hasExtension('framework')) {
            $container->prependExtensionConfig('framework', [
                'cache' => [
                    'pools' => [
                        'nowo_performance.cache' => [
                            'adapter'          => 'cache.adapter.filesystem',
                            'default_lifetime' => 3600,
                        ],
                    ],
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
