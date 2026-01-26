<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle;

use Nowo\PerformanceBundle\DependencyInjection\Compiler\QueryTrackingMiddlewarePass;
use Nowo\PerformanceBundle\DependencyInjection\PerformanceExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle for route performance metrics tracking.
 *
 * This bundle provides a complete solution for tracking and analyzing route performance
 * in Symfony applications. It records request time, database query count, and query
 * execution time for performance analysis.
 *
 * Features:
 * - Automatic route performance tracking
 * - Database query counting and timing
 * - Request time measurement
 * - Route data persistence
 * - Command to set/update route metrics
 * - Support for multiple environments
 * - Symfony 6.1+, 7.x, and 8.x compatible
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class PerformanceBundle extends Bundle
{
    /**
     * Overridden to allow for the custom extension alias.
     *
     * Creates and returns the container extension instance if not already created.
     * The extension is cached after the first call to ensure the same instance is returned
     * on subsequent calls.
     *
     * @return ExtensionInterface|null The container extension instance, or null if not available
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return $this->extension ??= new PerformanceExtension();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Register compiler pass for QueryTrackingMiddleware
        $container->addCompilerPass(new QueryTrackingMiddlewarePass());
    }
}
