<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Routing;

use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Host kernel for REQ-SF-004 rule 7: same bundles and config as {@see TestKernel}, but its routing is only the
 * Symfony default `controllers: { resource: routing.controllers }` (no import of the bundle routes.yaml).
 */
final class OptInRoutesKernel extends TestKernel
{
    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/opt-in-routes';
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'router' => ['resource' => '%kernel.project_dir%/config/routes_controllers_only.yaml'],
            ]);
        });
    }
}
