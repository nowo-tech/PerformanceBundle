<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DependencyInjection;

use Nowo\PerformanceBundle\DependencyInjection\PerformanceExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

/**
 * SEC-004 (Appendix BP, L1 / I1): web_ui.enabled (also through the deprecated dashboard.enabled) and
 * security.allow_unauthenticated decide at compile time which routes, access checker and CSRF checks exist, so an
 * %env()% placeholder is refused instead of silently meaning "true". Runs the real config merge
 * (MergeExtensionConfigurationPass), where env placeholders reach the extension.
 */
final class LiteralBooleanSwitchesTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function envPlaceholders(): iterable
    {
        yield 'web_ui.enabled' => [['web_ui' => ['enabled' => '%env(bool:PERFORMANCE_UI)%']], 'web_ui.enabled'];
        yield 'dashboard.enabled (deprecated)' => [['dashboard' => ['enabled' => '%env(bool:PERFORMANCE_UI)%']], 'web_ui.enabled'];
        yield 'security.allow_unauthenticated' => [['security' => ['allow_unauthenticated' => '%env(bool:PERFORMANCE_OPEN)%']], 'security.allow_unauthenticated'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('envPlaceholders')]
    #[IgnoreDeprecations]
    public function testEnvPlaceholdersAreRefused(array $config, string $path): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('nowo_performance.' . $path);

        $this->compile($config);
    }

    public function testLiteralBooleansAreAccepted(): void
    {
        $container = $this->compile(['web_ui' => ['enabled' => false], 'security' => ['allow_unauthenticated' => false]]);

        self::assertFalse($container->getParameter('nowo_performance.web_ui.enabled'));
        self::assertFalse($container->getParameter('nowo_performance.dashboard.enabled'));
        self::assertFalse($container->getParameter('nowo_performance.security.allow_unauthenticated'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function compile(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', true);
        $security = $this->createStub(ExtensionInterface::class);
        $security->method('getAlias')->willReturn('security');
        $container->registerExtension($security);
        $container->registerExtension(new PerformanceExtension());
        $container->loadFromExtension('nowo_performance', $config);

        (new MergeExtensionConfigurationPass())->process($container);

        return $container;
    }
}
