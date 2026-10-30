<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection;

use LogicException;
use Nowo\PerformanceBundle\Security\AllowAllPerformanceAccessChecker;
use Nowo\PerformanceBundle\Security\ConfigurablePerformanceAccessChecker;
use Nowo\PerformanceBundle\Security\PerformanceAccessCheckerInterface;
use Nowo\PerformanceBundle\Ui\PerformancePages;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

use function array_key_exists;
use function is_array;
use function is_bool;
use function is_string;
use function sprintf;
use function str_starts_with;

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

        // Optional: Symfony UX Twig Component (deprecated nowo_performance.* components). The former check on a
        // ComponentInterface that ux-twig-component never shipped meant the components were never registered.
        if (class_exists(AsTwigComponent::class)) {
            $loader->load('services_twig_component.yaml');
        }

        $configuration = $this->getConfiguration($configs, $container);
        $config        = $this->processConfiguration($configuration, $configs);

        $dashboardConfig = $config['dashboard'] ?? [];
        $securityConfig  = $config['security'] ?? [
            'access_roles'          => ['ROLE_ADMIN'],
            'access_checker'        => null,
            'allow_unauthenticated' => false,
        ];

        // SEC-004 (BP L1 / I1): both switches decide at compile time which routes, access checker and CSRF checks are
        // registered, so an %env()% placeholder (a truthy string here) is refused instead of silently meaning "true".
        $this->assertLiteralBoolean($config['web_ui']['enabled'] ?? true, 'web_ui.enabled (or the deprecated dashboard.enabled)');
        $this->assertLiteralBoolean($securityConfig['allow_unauthenticated'] ?? false, 'security.allow_unauthenticated');

        if (
            ($config['web_ui']['enabled'] ?? true)
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

        // Web UI (REQ-UI-003 canonical keys; the deprecated dashboard.* UI keys are mapped in Configuration)
        $webUiConfig = $config['web_ui'] ?? [];
        $webUiPath   = Configuration::ALIAS . '.web_ui';
        $uiEnabled   = ($webUiConfig['enabled'] ?? true) === true;
        $pathPrefix  = (string) ($webUiConfig['path_prefix'] ?? '/performance');
        $layout      = $webUiConfig['layout_template'] ?? null;
        $container->setParameter($webUiPath . '.enabled', $uiEnabled);
        $container->setParameter($webUiPath . '.path_prefix', $pathPrefix);
        $container->setParameter($webUiPath . '.layout_template', $layout);
        $container->setParameter($webUiPath . '.templates', $webUiConfig['templates'] ?? []);

        // Dashboard configuration (BC parameters: dashboard.path carries the whole web_ui.path_prefix)
        $dashboardPath = Configuration::ALIAS . '.dashboard';
        $accessRoles   = $securityConfig['access_roles'] ?? ['ROLE_ADMIN'];
        $container->setParameter($dashboardPath . '.enabled', $uiEnabled);
        $container->setParameter($dashboardPath . '.path', $pathPrefix);
        $container->setParameter($dashboardPath . '.prefix', '');
        // BC: keep dashboard.roles in sync with effective security.access_roles
        $container->setParameter($dashboardPath . '.roles', $accessRoles);
        // Configuration normalises the deprecated css_framework alias bootstrap → bootstrap5
        $cssFramework = $dashboardConfig['css_framework'] ?? 'bootstrap5';
        // BC: keep dashboard.template as bootstrap|tailwind markup family synced from css_framework
        $templateFamily = $cssFramework === 'tailwind' ? 'tailwind' : 'bootstrap';
        $container->setParameter($dashboardPath . '.css_framework', $cssFramework);
        $container->setParameter($dashboardPath . '.template', $templateFamily);
        $container->setParameter($dashboardPath . '.enable_record_management', $dashboardConfig['enable_record_management'] ?? false);
        $container->setParameter($dashboardPath . '.enable_review_system', $dashboardConfig['enable_review_system'] ?? false);
        // BC (deprecated Twig global nowo_performance_layout_template): the resolved layout of the pages
        $container->setParameter($dashboardPath . '.layout_template', is_string($layout) && $layout !== '' ? $layout : PerformancePages::UI_KIT_ADMIN_LAYOUT);

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

        $exportConfig = $config['export'] ?? [];
        $container->setParameter(Configuration::ALIAS . '.export.max_rows', $exportConfig['max_rows'] ?? 5000);

        $this->registerAccessChecker($container, $securityConfig, $uiEnabled);

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
     * Refuses anything but a literal boolean (e.g. an `%env(bool:...)%` placeholder, resolved only at runtime).
     */
    private function assertLiteralBoolean(mixed $value, string $path): void
    {
        if (!is_bool($value)) {
            throw new InvalidConfigurationException(sprintf('nowo_performance.%s must be a literal true or false: it is read at container compile time (routes, access checker, CSRF checks), so environment variables are not supported.', $path));
        }
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
    private function registerAccessChecker(ContainerBuilder $container, array $security, bool $dashboardEnabled): void
    {
        if ($security['allow_unauthenticated'] ?? false) {
            $this->refuseAllowUnauthenticatedInProduction($container);
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

        if (($security['access_roles'] ?? ['ROLE_ADMIN']) === [] && $dashboardEnabled) {
            throw new LogicException('NowoPerformanceBundle: security.access_roles (or the deprecated dashboard.roles) is empty, so nobody could use the dashboard (an empty list denies). Configure at least one role, set security.access_checker, set web_ui.enabled: false, or set security.allow_unauthenticated: true (demo/dev only).');
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
        $this->prependFormKitDefaults($container);
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

        $this->prependUiKitDefaults($container);
    }

    /**
     * When FormKit is installed, register the performance profile. Forms select it via #[FormKitConfig].
     */
    private function prependFormKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_form_kit')) {
            return;
        }

        $hostHasCssFramework = false;
        $hostHasProfile      = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            /** @var array<string, mixed> $cfg */
            if (array_key_exists('css_framework', $cfg)) {
                $hostHasCssFramework = true;
            }
            $profiles = $cfg['profiles'] ?? null;
            if (is_array($profiles) && array_key_exists('performance', $profiles)) {
                $hostHasProfile = true;
            }
        }

        $seed = [];

        if (!$hostHasCssFramework) {
            $seed['css_framework'] = 'bootstrap';
        }

        if (!$hostHasProfile) {
            $seed['profiles'] = [
                'performance' => [
                    'alias'              => 'performance',
                    'translation_domain' => 'NowoPerformanceBundle',
                    // SubmitType rejects help/placeholder; filter forms use SubmitType via addWithDefaults.
                    'auto_help'        => false,
                    'auto_placeholder' => false,
                    // REQ-UI-003: no framework utility classes; the host form theme / UiKit theme slots style the fields.
                    'defaults' => [
                        'attr' => ['class' => 'nowo-ui-input'],
                    ],
                ],
            ];
        }

        if ($seed !== []) {
            $container->prependExtensionConfig('nowo_form_kit', $seed);
        }
    }

    /**
     * REQ-UI-003: the dashboard inherits the CSS stack and icon set from nowo_ui_kit. Only when the host still sets the
     * deprecated dashboard.css_framework (or dashboard.template) and nowo_ui_kit has no css_framework of its own, that
     * value is copied to nowo_ui_kit.css_framework (and the matching icon set when nowo_ui_kit has none).
     */
    private function prependUiKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_ui_kit')) {
            return;
        }

        $hostSetsFramework = false;
        foreach ($container->getExtensionConfig(Configuration::ALIAS) as $cfg) {
            $dashboard = is_array($cfg) && is_array($cfg['dashboard'] ?? null) ? $cfg['dashboard'] : [];
            if (array_key_exists('css_framework', $dashboard) || array_key_exists('template', $dashboard)) {
                $hostSetsFramework = true;
            }
        }
        if (!$hostSetsFramework) {
            return;
        }

        $hostHasCssFramework = false;
        $hostHasIconSet      = false;
        foreach ($container->getExtensionConfig('nowo_ui_kit') as $cfg) {
            if (array_key_exists('css_framework', $cfg)) {
                $hostHasCssFramework = true;
            }
            if (array_key_exists('icon_set', $cfg)) {
                $hostHasIconSet = true;
            }
        }

        if ($hostHasCssFramework) {
            return;
        }

        $config   = $this->processConfiguration(new Configuration(), $container->getExtensionConfig(Configuration::ALIAS));
        $fw       = (string) ($config['dashboard']['css_framework'] ?? 'bootstrap5');
        $fw       = $fw === 'bootstrap' ? 'bootstrap5' : $fw;
        $defaults = ['css_framework' => $fw];
        if (!$hostHasIconSet) {
            $defaults['icon_set'] = $fw === 'tabler' ? 'tabler-icons' : 'bootstrap-icons';
        }

        $container->prependExtensionConfig('nowo_ui_kit', $defaults);
    }

    /**
     * `allow_unauthenticated` opens the performance dashboard to anonymous users: a dev / demo switch, refused in the prod environment and in
     * any non-debug kernel outside the test environments (test*), as in ContactFormBundle.
     */
    private function refuseAllowUnauthenticatedInProduction(ContainerBuilder $container): void
    {
        $environment = $container->hasParameter('kernel.environment') ? $container->getParameter('kernel.environment') : '';
        $environment = is_string($environment) ? $environment : '';
        $debug       = $container->hasParameter('kernel.debug') ? (bool) $container->getParameter('kernel.debug') : true;

        if ($environment === 'prod' || (!$debug && !str_starts_with($environment, 'test'))) {
            throw new LogicException('nowo_performance.security.allow_unauthenticated is a dev/demo switch: it opens the performance dashboard to anonymous users and is refused in the prod environment and in non-debug kernels (test* environments excepted).');
        }
    }
}
