<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle;

use Nowo\PerformanceBundle\DependencyInjection\Compiler\QueryTrackingMiddlewarePass;
use Nowo\PerformanceBundle\DependencyInjection\PerformanceExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\KernelInterface;

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
 * @copyright 2026 Nowo.tech
 */
class NowoPerformanceBundle extends Bundle
{
    /**
     * Ensures dump()/dd() work when the default VarDumper stream is invalid (e.g. FrankenPHP).
     */
    public function boot(): void
    {
        parent::boot();

        $kernel = $this->container->get('kernel');
        if (!$kernel instanceof KernelInterface || !$kernel->isDebug()) {
            return;
        }

        if (!class_exists(\Symfony\Component\VarDumper\VarDumper::class)) {
            return;
        }

        \Symfony\Component\VarDumper\VarDumper::setHandler(function ($var, ...$moreVars): void {
            $cloner = new \Symfony\Component\VarDumper\Cloner\VarCloner();
            $stream = null;
            if ('cli' === \PHP_SAPI) {
                $stream = @fopen('php://stderr', 'w') ?: (\defined('STDOUT') && \is_resource(\STDOUT) ? \STDOUT : null);
            } else {
                // Web: avoid php://output when headers not yet sent (e.g. redirects) to prevent
                // "Cannot modify header information - headers already sent"
                if (\headers_sent()) {
                    $stream = @fopen('php://output', 'w');
                }
                if (false === $stream || null === $stream) {
                    $stream = @fopen('php://stderr', 'w') ?: @fopen('php://output', 'w');
                }
                if (false === $stream) {
                    $stream = \defined('STDOUT') && \is_resource(\STDOUT) ? \STDOUT : null;
                }
            }
            if (null === $stream) {
                return;
            }
            $dumper = 'cli' === \PHP_SAPI
                ? new \Symfony\Component\VarDumper\Dumper\CliDumper($stream)
                : new \Symfony\Component\VarDumper\Dumper\HtmlDumper($stream);
            $dumper->dump($cloner->cloneVar($var));
            foreach ($moreVars as $v) {
                $dumper->dump($cloner->cloneVar($v));
            }
        });
    }

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
