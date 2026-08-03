<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DependencyInjection;

use LogicException;
use Nowo\PerformanceBundle\DependencyInjection\Configuration;
use Nowo\PerformanceBundle\DependencyInjection\PerformanceExtension;
use Nowo\PerformanceBundle\Security\AllowAllPerformanceAccessChecker;
use Nowo\PerformanceBundle\Security\ConfigurablePerformanceAccessChecker;
use Nowo\PerformanceBundle\Security\PerformanceAccessCheckerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

final class PerformanceExtensionTest extends TestCase
{
    private PerformanceExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new PerformanceExtension();
        $this->container = new ContainerBuilder();
        $this->registerSecurityExtension($this->container);
    }

    private function registerSecurityExtension(ContainerBuilder $container): void
    {
        $securityExtension = $this->createMock(ExtensionInterface::class);
        $securityExtension->method('getAlias')->willReturn('security');
        $container->registerExtension($securityExtension);
    }

    public function testGetAlias(): void
    {
        $this->assertSame('nowo_performance', $this->extension->getAlias());
    }

    public function testLoadDefaultConfiguration(): void
    {
        $this->extension->load([], $this->container);

        $this->assertTrue($this->container->getParameter('nowo_performance.enabled'));
        $this->assertSame(['prod', 'dev', 'test'], $this->container->getParameter('nowo_performance.environments'));
        $this->assertSame('default', $this->container->getParameter('nowo_performance.connection'));
        $this->assertSame('routes_data', $this->container->getParameter('nowo_performance.table_name'));
        $this->assertTrue($this->container->getParameter('nowo_performance.track_queries'));
        $this->assertTrue($this->container->getParameter('nowo_performance.track_request_time'));
        $this->assertSame(['_wdt', '_profiler', 'web_profiler*', '_error'], $this->container->getParameter('nowo_performance.ignore_routes'));

        // Dashboard configuration defaults
        $this->assertTrue($this->container->getParameter('nowo_performance.dashboard.enabled'));
        $this->assertSame('/performance', $this->container->getParameter('nowo_performance.dashboard.path'));
        $this->assertSame('', $this->container->getParameter('nowo_performance.dashboard.prefix'));
        $this->assertSame(['ROLE_ADMIN'], $this->container->getParameter('nowo_performance.dashboard.roles'));
        $this->assertSame('nowo_performance.cache', $this->container->getParameter('nowo_performance.cache.pool'));
        $this->assertSame(
            '@NowoPerformanceBundle/Performance/layout.html.twig',
            $this->container->getParameter('nowo_performance.dashboard.layout_template'),
        );
        $this->assertSame('bootstrap5', $this->container->getParameter('nowo_performance.dashboard.css_framework'));
        $this->assertSame('bootstrap', $this->container->getParameter('nowo_performance.dashboard.template'));
        $this->assertSame(['ROLE_ADMIN'], $this->container->getParameter('nowo_performance.security.access_roles'));
        $this->assertFalse($this->container->getParameter('nowo_performance.security.allow_unauthenticated'));
        $this->assertTrue($this->container->hasAlias(PerformanceAccessCheckerInterface::class));
        $this->assertTrue($this->container->hasDefinition('nowo_performance.access_checker.default'));
        $this->assertSame(
            ConfigurablePerformanceAccessChecker::class,
            $this->container->getDefinition('nowo_performance.access_checker.default')->getClass(),
        );
    }

    public function testLoadCustomLayoutTemplate(): void
    {
        $this->extension->load([
            ['dashboard' => ['layout_template' => 'base.html.twig']],
        ], $this->container);

        $this->assertSame(
            'base.html.twig',
            $this->container->getParameter('nowo_performance.dashboard.layout_template'),
        );
    }

    public function testLoadCustomConfiguration(): void
    {
        $config = [
            'enabled'            => false,
            'environments'       => ['prod'],
            'connection'         => 'custom_connection',
            'table_name'         => 'custom_table',
            'track_queries'      => false,
            'track_request_time' => false,
            'ignore_routes'      => ['_custom'],
            'dashboard'          => [
                'enabled' => false,
                'path'    => '/metrics',
                'prefix'  => '/admin',
                'roles'   => ['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER'],
            ],
        ];

        $this->extension->load([$config], $this->container);

        $this->assertFalse($this->container->getParameter('nowo_performance.enabled'));
        $this->assertSame(['prod'], $this->container->getParameter('nowo_performance.environments'));
        $this->assertSame('custom_connection', $this->container->getParameter('nowo_performance.connection'));
        $this->assertSame('custom_table', $this->container->getParameter('nowo_performance.table_name'));
        $this->assertFalse($this->container->getParameter('nowo_performance.track_queries'));
        $this->assertFalse($this->container->getParameter('nowo_performance.track_request_time'));
        $this->assertSame(['_custom'], $this->container->getParameter('nowo_performance.ignore_routes'));

        // Dashboard configuration — roles BC maps into security.access_roles and dashboard.roles param
        $this->assertFalse($this->container->getParameter('nowo_performance.dashboard.enabled'));
        $this->assertSame('/metrics', $this->container->getParameter('nowo_performance.dashboard.path'));
        $this->assertSame('/admin', $this->container->getParameter('nowo_performance.dashboard.prefix'));
        $this->assertSame(['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER'], $this->container->getParameter('nowo_performance.dashboard.roles'));
        $this->assertSame(['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER'], $this->container->getParameter('nowo_performance.security.access_roles'));
    }

    public function testLoadThrowsWhenDashboardRequiresSecurityBundle(): void
    {
        $container = new ContainerBuilder();
        $extension = new PerformanceExtension();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires symfony/security-bundle');

        $extension->load([[
            'dashboard' => ['enabled' => true],
            'security'  => ['allow_unauthenticated' => false],
        ]], $container);
    }

    public function testLoadUsesAllowAllCheckerWhenUnauthenticatedAccessIsEnabled(): void
    {
        $container = new ContainerBuilder();
        $extension = new PerformanceExtension();
        $extension->load([[
            'security' => ['allow_unauthenticated' => true],
        ]], $container);

        self::assertTrue($container->hasDefinition('nowo_performance.access_checker.allow_all'));
        self::assertSame(
            AllowAllPerformanceAccessChecker::class,
            $container->getDefinition('nowo_performance.access_checker.allow_all')->getClass(),
        );
        self::assertSame(
            'nowo_performance.access_checker.allow_all',
            (string) $container->getAlias(PerformanceAccessCheckerInterface::class),
        );
    }

    public function testLoadUsesCustomAccessCheckerAliasWhenConfigured(): void
    {
        $this->container->setDefinition('app.performance_checker', new Definition());
        $this->extension->load([[
            'security' => [
                'access_checker' => 'app.performance_checker',
            ],
        ]], $this->container);

        self::assertSame(
            'app.performance_checker',
            (string) $this->container->getAlias(PerformanceAccessCheckerInterface::class),
        );
    }

    public function testLoadCustomCachePoolConfiguration(): void
    {
        $this->extension->load([
            ['cache' => ['pool' => 'cache.app']],
        ], $this->container);

        $this->assertSame('cache.app', $this->container->getParameter('nowo_performance.cache.pool'));
    }

    public function testPrependTwigConfiguration(): void
    {
        $twigExtension = $this->createMock(ExtensionInterface::class);
        $twigExtension->method('getAlias')->willReturn('twig');
        $this->container->registerExtension($twigExtension);

        $this->extension->prepend($this->container);

        $this->assertTrue(true);
    }

    public function testPrependWithoutTwigExtension(): void
    {
        $this->extension->prepend($this->container);

        $this->assertTrue(true);
    }

    public function testPrependWithTwigAndFrameworkExtensions(): void
    {
        $twigExtension = $this->createMock(ExtensionInterface::class);
        $twigExtension->method('getAlias')->willReturn('twig');
        $this->container->registerExtension($twigExtension);

        $frameworkExtension = $this->createMock(ExtensionInterface::class);
        $frameworkExtension->method('getAlias')->willReturn('framework');
        $this->container->registerExtension($frameworkExtension);

        $this->extension->prepend($this->container);

        $this->assertTrue($this->container->hasExtension('twig'));
        $this->assertTrue($this->container->hasExtension('framework'));
    }

    public function testGetConfigurationReturnsConfigurationInstance(): void
    {
        $config = $this->extension->getConfiguration([], $this->container);

        $this->assertInstanceOf(Configuration::class, $config);
    }

    public function testLoadWithPartialConfigMergesWithDefaults(): void
    {
        $this->extension->load([['enabled' => false, 'table_name' => 'custom_perf']], $this->container);

        $this->assertFalse($this->container->getParameter('nowo_performance.enabled'));
        $this->assertSame('custom_perf', $this->container->getParameter('nowo_performance.table_name'));
        $this->assertSame(['prod', 'dev', 'test'], $this->container->getParameter('nowo_performance.environments'));
    }

    public function testLoadWithStageInEnvironments(): void
    {
        $this->extension->load([['environments' => ['dev', 'stage', 'prod']]], $this->container);

        $this->assertSame(['dev', 'stage', 'prod'], $this->container->getParameter('nowo_performance.environments'));
    }
}
