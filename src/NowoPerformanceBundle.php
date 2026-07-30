<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle;

use Nowo\PerformanceBundle\DependencyInjection\Compiler\NotificationChannelsPass;
use Nowo\PerformanceBundle\DependencyInjection\Compiler\QueryTrackingMiddlewarePass;
use Nowo\PerformanceBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\PerformanceBundle\DependencyInjection\PerformanceExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\VarDumper;

use function defined;
use function is_resource;

use const PHP_SAPI;
use const STDOUT;

/**
 * Symfony bundle for route performance metrics tracking.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class NowoPerformanceBundle extends Bundle
{
    /**
     * In CLI, sets a VarDumper fallback handler (stderr) when the default stream is invalid (e.g. FrankenPHP).
     * In web context we do not replace the handler so Symfony's DumpDataCollector is used and dumps appear in the Web Debug Toolbar.
     */
    public function boot(): void
    {
        parent::boot();

        if (!$this->container instanceof ContainerInterface) {
            return;
        }

        $kernel = $this->container->get('kernel');
        if (!$kernel instanceof KernelInterface || !$kernel->isDebug()) {
            return;
        }

        if (!class_exists(VarDumper::class) || PHP_SAPI !== 'cli') {
            return;
        }

        VarDumper::setHandler(static function ($var, ...$moreVars): void {
            $cloner = new VarCloner();
            $stream = @fopen('php://stderr', 'w') ?: (defined('STDOUT') && is_resource(STDOUT) ? STDOUT : null);
            if ($stream === null) {
                return;
            }
            $dumper = new CliDumper($stream);
            $dumper->dump($cloner->cloneVar($var));
            foreach ($moreVars as $v) {
                $dumper->dump($cloner->cloneVar($v));
            }
        });
    }

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

        $container->addCompilerPass(new NotificationChannelsPass());
        $container->addCompilerPass(new QueryTrackingMiddlewarePass());
        $container->addCompilerPass(new TwigPathsPass());
    }
}
