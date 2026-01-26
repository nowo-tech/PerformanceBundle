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
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
