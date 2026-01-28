<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection\Compiler;

use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Compiler pass to register QueryTrackingMiddleware with Doctrine DBAL.
 *
 * This pass registers the QueryTrackingMiddleware as a DBAL middleware
 * so it can intercept and track all database queries.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class QueryTrackingMiddlewarePass implements CompilerPassInterface
{
    /**
     * Process the container builder.
     *
     * Registers QueryTrackingMiddleware with Doctrine DBAL configuration.
     *
     * @param ContainerBuilder $container The container builder
     */
    public function process(ContainerBuilder $container): void
    {
        // Check if Doctrine DBAL is available
        if (!$container->hasExtension('doctrine')) {
            return;
        }

        // Check if parameters are set
        if (!$container->hasParameter('nowo_performance.enabled')
            || !$container->hasParameter('nowo_performance.track_queries')
            || !$container->hasParameter('nowo_performance.connection')) {
            return;
        }

        // Get the connection name from configuration
        $connectionName = $container->getParameter('nowo_performance.connection');
        $enabled = $container->getParameter('nowo_performance.enabled');
        $trackQueries = $container->getParameter('nowo_performance.track_queries');

        // Only register middleware if tracking is enabled
        if (!$enabled || !$trackQueries) {
            return;
        }

        // Register QueryTrackingMiddleware service
        $middlewareId = 'nowo_performance.dbal.query_tracking_middleware';
        if (!$container->hasDefinition($middlewareId)) {
            $container->register($middlewareId, QueryTrackingMiddleware::class)
                ->setPublic(false);
        }

        // Note: The middleware is applied via QueryTrackingConnectionSubscriber
        // which uses reflection to wrap the driver after connection creation.
        // This approach works with all versions of DoctrineBundle 3.x.
    }
}
