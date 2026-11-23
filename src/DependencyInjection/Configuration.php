<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection;

use Nowo\PerformanceBundle\Ui\PerformancePages;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function implode;
use function is_array;
use function sprintf;
use function str_starts_with;
use function substr;

/**
 * Configuration class for the bundle.
 *
 * Defines the configuration structure for the PerformanceBundle.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class Configuration implements ConfigurationInterface
{
    /**
     * The extension alias.
     */
    public const ALIAS = 'nowo_performance';

    /**
     * Keys removed in 4.0 (deprecated in 3.x) => what replaces them. A configuration that still sets one fails with
     * this hint instead of Symfony's bare "Unrecognized option".
     *
     * @var array<string, string>
     */
    private const REMOVED_KEYS = [
        'query_tracking_threshold'  => 'nothing: it never had any effect (deprecated since 3.6.0); remove it',
        'dashboard.enabled'         => 'nowo_performance.web_ui.enabled (deprecated since 3.15.0)',
        'dashboard.path'            => 'nowo_performance.web_ui.path_prefix, which is dashboard.prefix + dashboard.path (deprecated since 3.15.0)',
        'dashboard.prefix'          => 'nowo_performance.web_ui.path_prefix, which is dashboard.prefix + dashboard.path (deprecated since 3.15.0)',
        'dashboard.layout_template' => 'nowo_performance.web_ui.layout_template (deprecated since 3.15.0)',
        'dashboard.css_framework'   => 'nowo_ui_kit.css_framework (deprecated since 3.15.0)',
        'dashboard.template'        => 'nowo_ui_kit.css_framework (deprecated since 3.15.0)',
        'dashboard.roles'           => 'nowo_performance.security.access_roles (documented as deprecated in 3.x, no runtime notice)',
    ];

    /** @var list<string> CSS stacks of the removed dashboard.css_framework key (REQ-UI-001); kept as a public constant, no longer read. */
    public const CSS_FRAMEWORKS = [
        'bootstrap',
        'bootstrap4',
        'bootstrap5',
        'tabler',
        'tailwind',
        'foundation',
        'custom',
        'none',
    ];

    /**
     * Builds the configuration tree.
     *
     * @return TreeBuilder The configuration tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $rootNode    = $treeBuilder->getRootNode();

        $rootNode
            ->beforeNormalization()
                ->always(static function (mixed $v): mixed {
                    if (is_array($v)) {
                        self::refuseRemovedKeys($v);
                    }

                    return $v;
                })
            ->end();
        $this->addEnabledToCacheSections($rootNode);
        $this->addThresholdsSection($rootNode);
        $this->addDashboardSection($rootNode);
        $this->addWebUiToExportSections($rootNode);
        $this->addNotificationsSection($rootNode);

        return $treeBuilder;
    }

    /**
     * Adds the `enabled` to `cache` settings.
     */
    private function addEnabledToCacheSections(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->booleanNode('enabled')
                    ->info('Enable or disable performance tracking')
                    ->defaultValue(true)
                ->end()
                ->arrayNode('environments')
                    ->info('Environments where performance tracking is enabled')
                    ->prototype('scalar')->end()
                    ->defaultValue(['prod', 'dev', 'test'])
                ->end()
                ->scalarNode('connection')
                    ->info('Doctrine connection name to use for storing metrics')
                    ->defaultValue('default')
                ->end()
                ->scalarNode('table_name')
                    ->info('Table name for storing route performance data')
                    ->defaultValue('routes_data')
                ->end()
                ->booleanNode('track_queries')
                    ->info('Track database query count and execution time')
                    ->defaultValue(true)
                ->end()
                ->booleanNode('track_request_time')
                    ->info('Track request execution time')
                    ->defaultValue(true)
                ->end()
                ->booleanNode('track_sub_requests')
                    ->info('Track sub-requests in addition to main requests. When enabled, performance metrics will be collected for both main requests and sub-requests (e.g., ESI, fragments, includes).')
                    ->defaultValue(false)
                ->end()
                ->arrayNode('ignore_routes')
                    ->info('List of route names (literal) or glob patterns (e.g. _wdt, _profiler, web_profiler*, admin_*) to ignore. Symfony toolbar/profiler use route names web_profiler_wdt and web_profiler_profiler.')
                    ->prototype('scalar')->end()
                    ->defaultValue(['_wdt', '_profiler', 'web_profiler*', '_error'])
                ->end()
                ->arrayNode('track_status_codes')
                    ->info('HTTP status codes to track and calculate ratios for')
                    ->prototype('integer')->end()
                    ->defaultValue([200, 404, 500, 503])
                    ->example([200, 201, 400, 404, 500, 503])
                ->end()
                ->booleanNode('async')
                    ->info('Record metrics asynchronously using Symfony Messenger (requires symfony/messenger)')
                    ->defaultValue(false)
                ->end()
                ->floatNode('sampling_rate')
                    ->info('Sampling rate for high-traffic routes (0.0 to 1.0, where 1.0 = 100% tracking). Reduces database load for frequently accessed routes.')
                    ->defaultValue(1.0)
                    ->min(0.0)
                    ->max(1.0)
                ->end()
                ->booleanNode('enable_access_records')
                    ->info('Enable temporal access records tracking. Creates individual records for each route access with timestamp, status code, and response time. Useful for analyzing access patterns by time of day.')
                    ->defaultValue(false)
                ->end()
                ->integerNode('access_records_retention_days')
                    ->info('Retention period for access records in days. Records older than this are eligible for purge. Omit or null = keep all. Use the purge command or UI to clean old records. Example: 30 to keep only the last 30 days.')
                    ->defaultNull()
                    ->min(1)
                ->end()
                ->booleanNode('track_user')
                    ->info('When access records are enabled, store the logged-in user identifier and user ID (if available) on each record. Requires Symfony Security. Disabled by default for privacy.')
                    ->defaultValue(false)
                ->end()
                ->booleanNode('enable_logging')
                    ->info('Enable or disable bundle logging. When disabled, no error_log() calls will be made. Recommended to disable in production for better performance.')
                    ->defaultValue(true)
                ->end()
                ->booleanNode('check_table_status')
                    ->info('Check that routes_data and routes_data_records tables exist and are complete (Web Profiler, dashboard diagnose, CLI diagnose). Set to false to skip these checks and save DB/introspection queries. Default true.')
                    ->defaultValue(true)
                ->end()
                ->arrayNode('cache')
                    ->info('Cache configuration. The bundle registers a dedicated pool nowo_performance.cache (filesystem) by default.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('pool')
                            ->info('Cache pool service ID. Default: nowo_performance.cache (dedicated pool). Use cache.app to share with application cache.')
                            ->defaultValue('nowo_performance.cache')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Adds the `thresholds` section.
     */
    private function addThresholdsSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('thresholds')
                    ->info('Performance thresholds for warning and critical levels')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('request_time')
                            ->info('Request time thresholds in seconds')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->floatNode('warning')
                                    ->info('Request time threshold for warning (seconds)')
                                    ->defaultValue(0.5)
                                ->end()
                                ->floatNode('critical')
                                    ->info('Request time threshold for critical (seconds)')
                                    ->defaultValue(1.0)
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('query_count')
                            ->info('Query count thresholds')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->integerNode('warning')
                                    ->info('Query count threshold for warning')
                                    ->defaultValue(20)
                                ->end()
                                ->integerNode('critical')
                                    ->info('Query count threshold for critical')
                                    ->defaultValue(50)
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('memory_usage')
                            ->info('Memory usage thresholds in MB')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->floatNode('warning')
                                    ->info('Memory usage threshold for warning (MB)')
                                    ->defaultValue(20.0)
                                ->end()
                                ->floatNode('critical')
                                    ->info('Memory usage threshold for critical (MB)')
                                    ->defaultValue(50.0)
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Adds the `dashboard` section.
     */
    private function addDashboardSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('dashboard')
                    ->info('Performance dashboard configuration')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enable_record_management')
                            ->info('Enable individual record deletion from dashboard')
                            ->defaultValue(false)
                        ->end()
                        ->booleanNode('enable_review_system')
                            ->info('Enable review system to mark records as reviewed with improvement tracking')
                            ->defaultValue(false)
                        ->end()
                        ->arrayNode('date_formats')
                            ->info('Date format configuration for displaying dates in the dashboard')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('datetime')
                                    ->info('Format for date and time (e.g., Y-m-d H:i:s)')
                                    ->defaultValue('Y-m-d H:i:s')
                                    ->example('Y-m-d H:i:s')
                                ->end()
                                ->scalarNode('date')
                                    ->info('Format for date only without seconds (e.g., Y-m-d H:i)')
                                    ->defaultValue('Y-m-d H:i')
                                    ->example('Y-m-d H:i')
                                ->end()
                            ->end()
                        ->end()
                        ->integerNode('auto_refresh_interval')
                            ->info('Auto-refresh interval in seconds (0 to disable). Dashboard will automatically reload data at this interval.')
                            ->defaultValue(0)
                            ->min(0)
                        ->end()
                        ->booleanNode('enable_ranking_queries')
                            ->info('Enable ranking queries in WebProfiler (request time and query count rankings). Disable to reduce database queries on each request.')
                            ->defaultValue(true)
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Adds the `web_ui` to `export` settings.
     */
    private function addWebUiToExportSections(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('web_ui')
                    ->info('Dashboard Web UI (REQ-UI-003 canonical keys). The pages extend the UiKit page scaffolds; the CSS stack, icon set and row-action display are inherited from nowo_ui_kit.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Register the dashboard pages (routes stay importable; pages answer 404 when false). Replaces dashboard.enabled (removed in 4.0).')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('path_prefix')
                            ->info('URL prefix of the dashboard pages (e.g. /admin/performance). Replaces dashboard.prefix + dashboard.path (removed in 4.0).')
                            ->defaultValue('/performance')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('layout_template')
                            ->info('Layout the page scaffolds extend: null (default) = nowo_ui_kit.layouts.admin. The layout must render the UiKit canonical blocks (nowo_ui_page_header, nowo_ui_content, ...); a 3.14 layout without them fails at render (the 3.15 bridge was removed in 4.0). Replaces dashboard.layout_template.')
                            ->defaultNull()
                            ->beforeNormalization()
                                ->ifString()
                                ->then(static fn (string $v): ?string => $v === '' || $v === PerformancePages::FORMER_DEFAULT_LAYOUT ? null : $v)
                            ->end()
                        ->end()
                        ->arrayNode('templates')
                            ->info('Per-page template overrides (page id => template): index, statistics, access_statistics, access_records, diagnose. Use a thin template that extends "@!NowoPerformanceBundle/..." and overrides a data block.')
                            ->useAttributeAsKey('page')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => array_diff(array_keys($v), array_keys(PerformancePages::TEMPLATES)) !== [])
                                ->then(static function (array $v): array {
                                    throw new InvalidConfigurationException(sprintf('Unknown nowo_performance.web_ui.templates page(s) "%s"; known: %s.', implode('", "', array_diff(array_keys($v), array_keys(PerformancePages::TEMPLATES))), implode(', ', array_keys(PerformancePages::TEMPLATES))));
                                })
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('security')
                    ->info('Dashboard access control (REQ-UI-002)')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('access_roles')
                            ->info('Roles that may access the dashboard (user needs at least one). An empty list denies; with the dashboard enabled it fails container compilation unless access_checker or allow_unauthenticated is set. Default ROLE_ADMIN.')
                            ->scalarPrototype()->end()
                            ->defaultValue(['ROLE_ADMIN'])
                        ->end()
                        ->scalarNode('access_checker')
                            ->info('Optional service id implementing PerformanceAccessCheckerInterface')
                            ->defaultNull()
                        ->end()
                        ->booleanNode('allow_unauthenticated')
                            ->info('When true, skips Symfony Security requirement for the dashboard (demo/dev only)')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('export')
                    ->info('Export limits for access-records CSV/JSON downloads')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_rows')
                            ->info('Maximum access-record rows returned by export endpoints. Default 5000; set up to 50000 to restore previous behavior.')
                            ->defaultValue(5000)
                            ->min(1)
                            ->max(50000)
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Adds the `notifications` section.
     */
    private function addNotificationsSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('notifications')
                    ->info('Performance alert notifications configuration')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Enable or disable performance notifications')
                            ->defaultValue(false)
                        ->end()
                        ->floatNode('http_timeout')
                            ->info('HTTP timeout in seconds for Slack/Teams/generic webhook requests. Keep below PHP max_execution_time / FrankenPHP write timeout.')
                            ->defaultValue(10.0)
                            ->min(0.1)
                        ->end()
                        ->arrayNode('email')
                            ->info('Email notification configuration')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')
                                    ->info('Enable email notifications (requires symfony/mailer)')
                                    ->defaultValue(false)
                                ->end()
                                ->scalarNode('from')
                                    ->info('Sender email address')
                                    ->defaultValue('noreply@example.com')
                                ->end()
                                ->arrayNode('to')
                                    ->info('Recipient email addresses')
                                    ->prototype('scalar')->end()
                                    ->defaultValue([])
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('slack')
                            ->info('Slack webhook notification configuration')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')
                                    ->info('Enable Slack notifications (requires symfony/http-client)')
                                    ->defaultValue(false)
                                ->end()
                                ->scalarNode('webhook_url')
                                    ->info('Slack webhook URL')
                                    ->defaultValue('')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('teams')
                            ->info('Microsoft Teams webhook notification configuration')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')
                                    ->info('Enable Teams notifications (requires symfony/http-client)')
                                    ->defaultValue(false)
                                ->end()
                                ->scalarNode('webhook_url')
                                    ->info('Teams webhook URL')
                                    ->defaultValue('')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('webhook')
                            ->info('Generic webhook notification configuration')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')
                                    ->info('Enable generic webhook notifications (requires symfony/http-client)')
                                    ->defaultValue(false)
                                ->end()
                                ->scalarNode('url')
                                    ->info('Webhook URL')
                                    ->defaultValue('')
                                ->end()
                                ->scalarNode('format')
                                    ->info('Payload format: json, slack, or teams')
                                    ->defaultValue('json')
                                ->end()
                                ->arrayNode('headers')
                                    ->info('Additional HTTP headers')
                                    ->prototype('scalar')->end()
                                    ->defaultValue([])
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Refuses the keys removed in 4.0 ({@see self::REMOVED_KEYS}) with the replacement and a pointer to UPGRADING.
     *
     * @param array<mixed> $v raw root configuration
     *
     * @throws InvalidConfigurationException when a removed key is set
     */
    private static function refuseRemovedKeys(array $v): void
    {
        $dashboard = is_array($v['dashboard'] ?? null) ? $v['dashboard'] : [];
        foreach (self::REMOVED_KEYS as $path => $replacement) {
            $set = str_starts_with($path, 'dashboard.')
                ? array_key_exists(substr($path, 10), $dashboard)
                : array_key_exists($path, $v);
            if ($set) {
                throw new InvalidConfigurationException(sprintf('The option "nowo_performance.%s" was removed in 4.0. Use %s; see docs/UPGRADING.md "To 4.0.0".', $path, $replacement));
            }
        }
    }
}
