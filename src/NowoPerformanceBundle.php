<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle;

use Nowo\PerformanceBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use Nowo\PerformanceBundle\DependencyInjection\Compiler\NotificationChannelsPass;
use Nowo\PerformanceBundle\DependencyInjection\Compiler\QueryTrackingMiddlewarePass;
use Nowo\PerformanceBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\PerformanceBundle\DependencyInjection\PerformanceExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle for route performance metrics tracking.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class NowoPerformanceBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        // @igor-ignore - Boot-time Symfony Bundle extension cache (not request state).
        $this->extension ??= new PerformanceExtension();

        return $this->extension instanceof ExtensionInterface ? $this->extension : null;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new CsrfProtectionPass());
        $container->addCompilerPass(new NotificationChannelsPass());
        $container->addCompilerPass(new QueryTrackingMiddlewarePass());
        $container->addCompilerPass(new TwigPathsPass());
    }
}
