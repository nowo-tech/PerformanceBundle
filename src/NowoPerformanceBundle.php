<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle;

use Nowo\PerformanceBundle\DependencyInjection\Compiler\QueryTrackingMiddlewarePass;
use Nowo\PerformanceBundle\DependencyInjection\PerformanceExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\KernelInterface;

use function defined;
use function is_resource;

use const PHP_SAPI;
use const STDOUT;

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
     * Optional stream for VarDumper handler (used in tests to avoid output). When null, php://stderr is used.
     *
     * @var resource|null
     */
    public static $varDumperStreamOverride;

    /**
     * Test-only: when set to false, boot() acts as if VarDumper class does not exist (early return). Leave null in production.
     */
    public static ?bool $testVarDumperExistsOverride = null;

    /**
     * Test-only: when set to false, boot() treats VarDumper as unavailable (covers the same exit as !class_exists). Leave null in production.
     */
    public static ?bool $testVarDumperClassExistsOverride = null;

    /**
     * Test-only: when set, boot() uses this instead of PHP_SAPI for CLI check. Leave null in production.
     */
    public static ?string $testSapiOverride = null;

    /**
     * Test-only: when true, boot() forces stream to null to cover the $stream === null path. Leave false in production.
     */
    public static bool $testStreamForceNull = false;

    /**
     * In CLI, sets a VarDumper fallback handler (stderr) when the default stream is invalid (e.g. FrankenPHP).
     * In web context we do not replace the handler so Symfony's DumpDataCollector is used and dumps appear in the Web Debug Toolbar.
     */
    public function boot(): void
    {
        parent::boot();

        if (!$this->container instanceof \Symfony\Component\DependencyInjection\ContainerInterface) {
            return;
        }

        $kernel = $this->container->get('kernel');
        if (!$kernel instanceof KernelInterface || !$kernel->isDebug()) {
            return;
        }

        if (self::$testVarDumperExistsOverride === false) {
            return;
        }

        $varDumperAvailable = self::$testVarDumperClassExistsOverride ?? class_exists(\Symfony\Component\VarDumper\VarDumper::class);
        if (!$varDumperAvailable) {
            return;
        }

        $sapi = self::$testSapiOverride ?? PHP_SAPI;
        if ($sapi !== 'cli') {
            return;
        }

        \Symfony\Component\VarDumper\VarDumper::setHandler(static function ($var, ...$moreVars): void {
            $cloner = new \Symfony\Component\VarDumper\Cloner\VarCloner();
            $stream = self::$varDumperStreamOverride ?? @fopen('php://stderr', 'w') ?: (defined('STDOUT') && is_resource(STDOUT) ? STDOUT : null);
            if (self::$testStreamForceNull) {
                $stream = null;
            }
            if ($stream === null) {
                return;
            }
            $dumper = new \Symfony\Component\VarDumper\Dumper\CliDumper($stream);
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
        if ($this->extension === null) {
            $this->extension = new PerformanceExtension();
        }

        return $this->extension instanceof ExtensionInterface ? $this->extension : null;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Register compiler pass for QueryTrackingMiddleware
        $container->addCompilerPass(new QueryTrackingMiddlewarePass());
    }
}
