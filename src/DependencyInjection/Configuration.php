<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function array_key_exists;
use function is_array;

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

    /** @var list<string> Host CSS stacks accepted by dashboard.css_framework (REQ-UI-001). */
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
                    if (!is_array($v)) {
                        return $v;
                    }

                    $dashboardRoles         = $v['dashboard']['roles'] ?? null;
                    $hasExplicitAccessRoles = is_array($v['security'] ?? null)
                        && array_key_exists('access_roles', $v['security']);

                    // BC: map dashboard.roles → security.access_roles when access_roles is not set
                    if (!$hasExplicitAccessRoles && is_array($dashboardRoles)) {
                        $v['security'] ??= [];
                        $v['security']['access_roles'] = $dashboardRoles;
                    }

                    return $v;
                })
            ->end()
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
                ->integerNode('query_tracking_threshold')
                    ->info('Minimum query count to track query execution time. Queries below this threshold are counted but not timed individually.')
                    ->defaultValue(0)
                    ->min(0)
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
                ->arrayNode('dashboard')
                    ->info('Performance dashboard configuration')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function (array $v): array {
                            // BC alias: dashboard.template → css_framework when css_framework absent
                            if (isset($v['template']) && !isset($v['css_framework'])) {
                                $v['css_framework'] = match ((string) $v['template']) {
                                    'bootstrap' => 'bootstrap5',
                                    'tailwind'  => 'tailwind',
                                    default     => (string) $v['template'],
                                };
                            }
                            // Canonical alias: bootstrap → bootstrap5
                            if (isset($v['css_framework']) && $v['css_framework'] === 'bootstrap') {
                                $v['css_framework'] = 'bootstrap5';
                            }

                            return $v;
                        })
                    ->end()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Enable or disable the performance dashboard')
                            ->defaultValue(true)
                        ->end()
                        ->scalarNode('path')
                            ->info('Route path for the dashboard (e.g., /performance, /metrics)')
                            ->defaultValue('/performance')
                        ->end()
                        ->scalarNode('prefix')
                            ->info('Route prefix for the dashboard (e.g., /admin, /monitoring)')
                            ->defaultValue('')
                        ->end()
                        ->arrayNode('roles')
                            ->info('Deprecated: use security.access_roles instead. Kept for BC; mapped to security.access_roles when access_roles is not set. Empty disables role checks (not recommended in production).')
                            ->prototype('scalar')->end()
                            ->defaultValue(['ROLE_ADMIN'])
                            ->example(['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER'])
                        ->end()
                        ->enumNode('css_framework')
                            ->info('Host-chosen CSS stack for the dashboard (REQ-UI-001). Twig global nowo_performance_css_framework. Values: bootstrap (alias of bootstrap5), bootstrap4, bootstrap5, tabler, tailwind, foundation, custom, none. Default bootstrap5 matches the Bootstrap CDN demo. Dual markup ships bootstrap-family and tailwind partials; other values use bootstrap-family markup unless you override Twig.')
                            ->values(self::CSS_FRAMEWORKS)
                            ->defaultValue('bootstrap5')
                        ->end()
                        ->enumNode('template')
                            ->info('Deprecated: use dashboard.css_framework instead. Kept for BC; mapped to css_framework when css_framework is absent (bootstrap→bootstrap5, tailwind→tailwind). Synced back to bootstrap|tailwind markup family after normalization.')
                            ->values(['bootstrap', 'tailwind'])
                            ->defaultValue('bootstrap')
                        ->end()
                        ->scalarNode('layout_template')
                            ->info('Twig layout that dashboard pages extend via global nowo_performance_layout_template (e.g. base.html.twig). Default is the bundle demo shell. Host apps should set this to the project layout; the layout must expose blocks body (or nowo_performance_content), stylesheets, and javascripts.')
                            ->defaultValue('@NowoPerformanceBundle/Performance/layout.html.twig')
                        ->end()
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
                    ->validate()
                        ->always(static function (array $v): array {
                            // Keep deprecated template synced to markup family from canonical css_framework
                            $css           = $v['css_framework'] ?? 'bootstrap5';
                            $v['template'] = $css === 'tailwind' ? 'tailwind' : 'bootstrap';

                            return $v;
                        })
                    ->end()
                ->end()
                ->arrayNode('security')
                    ->info('Dashboard access control (REQ-UI-002)')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('access_roles')
                            ->info('Roles that may access the dashboard (user needs at least one). Empty disables role checks. Default ROLE_ADMIN.')
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

        return $treeBuilder;
    }
}
