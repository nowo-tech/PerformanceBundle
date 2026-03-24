<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\PerformanceBundle\NowoPerformanceBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\UX\Icons\UXIconsBundle;

/**
 * Same as TestKernel but with the performance dashboard disabled (covers controller 403/404 branches).
 */
final class TestKernelDashboardDisabled extends BaseKernel
{
    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new SecurityBundle(),
            new TwigBundle(),
            new UXIconsBundle(),
            new NowoPerformanceBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $confDir = $this->getProjectDir() . '/config';
        $loader->load($confDir . '/packages/framework.yaml');
        $loader->load($confDir . '/packages/doctrine.yaml');
        $loader->load($confDir . '/packages/security.yaml');
        $loader->load($confDir . '/packages/twig.yaml');
        $loader->load($confDir . '/packages/nowo_performance_dashboard_disabled.yaml');
        $loader->load($confDir . '/services.yaml');
    }
}
