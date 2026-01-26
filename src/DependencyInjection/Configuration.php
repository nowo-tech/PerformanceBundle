<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration class for the bundle.
 *
 * Defines the configuration structure for the PerformanceBundle.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
final class Configuration implements ConfigurationInterface
{
    /**
     * The extension alias.
     */
    public const ALIAS = 'nowo_performance';

    /**
     * Builds the configuration tree.
     *
     * @return TreeBuilder The configuration tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->booleanNode('enabled')
                    ->info('Enable or disable performance tracking')
                    ->defaultValue(true)
                ->end()
                ->arrayNode('environments')
                    ->info('Environments where performance tracking is enabled')
                    ->prototype('scalar')->end()
                    ->defaultValue(['dev', 'test'])
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
                ->arrayNode('ignore_routes')
                    ->info('List of route names to ignore (e.g., _wdt, _profiler)')
                    ->prototype('scalar')->end()
                    ->defaultValue(['_wdt', '_profiler', '_error'])
                ->end()
                ->booleanNode('async')
                    ->info('Record metrics asynchronously using Symfony Messenger (requires symfony/messenger)')
                    ->defaultValue(false)
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
                            ->info('Required roles to access the dashboard (users must have at least one)')
                            ->prototype('scalar')->end()
                            ->defaultValue([])
                            ->example(['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER'])
                        ->end()
                        ->enumNode('template')
                            ->info('CSS framework to use for the dashboard (bootstrap or tailwind)')
                            ->values(['bootstrap', 'tailwind'])
                            ->defaultValue('bootstrap')
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
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
