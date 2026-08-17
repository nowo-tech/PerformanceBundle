<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\PerformanceBundle\NowoPerformanceBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\UX\Icons\UXIconsBundle;

use function str_contains;

/**
 * Minimal Kernel for integration tests.
 * Uses SQLite in-memory so no external database is required.
 */
class TestKernel extends BaseKernel
{
    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new SecurityBundle(),
            new TwigBundle(),
            new UXIconsBundle(),
            new NowoFormKitBundle(),
            new NowoUiKitBundle(),
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
        $loader->load($confDir . '/packages/' . $this->performanceConfigFile());
        $loader->load($confDir . '/services.yaml');
    }

    /**
     * ORM 3.6 SchemaTool + DBAL 4.4: DoctrineDbalCacheAdapterSchemaListener calls
     * GenerateSchemaEventArgs::setSchema(), which needs DBAL Schema::edit() (dbal ^4.5, unreleased).
     */
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(
            new class implements CompilerPassInterface {
                public function process(ContainerBuilder $container): void
                {
                    foreach ($container->getDefinitions() as $definition) {
                        $class = $definition->getClass();
                        if ($class === null || !str_contains($class, '\\SchemaListener\\')) {
                            continue;
                        }

                        $definition
                            ->setClass(NoopDoctrineCacheSchemaListener::class)
                            ->setArguments([]);
                    }
                }
            },
            PassConfig::TYPE_BEFORE_REMOVING,
            -1024,
        );
    }

    protected function performanceConfigFile(): string
    {
        return 'nowo_performance.yaml';
    }
}
