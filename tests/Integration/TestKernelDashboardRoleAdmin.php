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
 * Dashboard enabled but requires ROLE_ADMIN (anonymous user → AccessDenied).
 */
final class TestKernelDashboardRoleAdmin extends BaseKernel
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
        IntegrationDoctrineConfig::load($loader, $confDir . '/packages');
        $loader->load($confDir . '/packages/security.yaml');
        $loader->load($confDir . '/packages/twig.yaml');
        $loader->load($confDir . '/packages/nowo_performance_dashboard_role_admin.yaml');
        $loader->load($confDir . '/services.yaml');
    }
}
