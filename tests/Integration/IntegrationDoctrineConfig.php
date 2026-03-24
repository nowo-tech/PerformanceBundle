<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration;

use Symfony\Component\Config\Loader\LoaderInterface;

use const PHP_VERSION_ID;

/**
 * Loads Doctrine integration config; enables native lazy objects on PHP 8.4+ (see doctrine_native_lazy_php84.yaml).
 */
final class IntegrationDoctrineConfig
{
    public static function load(LoaderInterface $loader, string $packagesConfigDir): void
    {
        $loader->load($packagesConfigDir . '/doctrine.yaml');
        if (PHP_VERSION_ID >= 80400) {
            $loader->load($packagesConfigDir . '/doctrine_native_lazy_php84.yaml');
        }
    }
}
