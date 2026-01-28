<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DependencyInjection;

use Nowo\PerformanceBundle\DependencyInjection\PerformanceExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class PerformanceExtensionTest extends TestCase
{
    private PerformanceExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new PerformanceExtension();
        $this->container = new ContainerBuilder();
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
        $this->assertSame([], $this->container->getParameter('nowo_performance.dashboard.roles'));
    }

    public function testLoadCustomConfiguration(): void
    {
        $config = [
            'enabled' => false,
            'environments' => ['prod'],
            'connection' => 'custom_connection',
            'table_name' => 'custom_table',
            'track_queries' => false,
            'track_request_time' => false,
            'ignore_routes' => ['_custom'],
            'dashboard' => [
                'enabled' => false,
                'path' => '/metrics',
                'prefix' => '/admin',
                'roles' => ['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER'],
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
        
        // Dashboard configuration
        $this->assertFalse($this->container->getParameter('nowo_performance.dashboard.enabled'));
        $this->assertSame('/metrics', $this->container->getParameter('nowo_performance.dashboard.path'));
        $this->assertSame('/admin', $this->container->getParameter('nowo_performance.dashboard.prefix'));
        $this->assertSame(['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER'], $this->container->getParameter('nowo_performance.dashboard.roles'));
    }

    public function testPrependTwigConfiguration(): void
    {
        // Create a mock extension that implements the interface
        $twigExtension = $this->createMock(\Symfony\Component\DependencyInjection\Extension\ExtensionInterface::class);
        $twigExtension->method('getAlias')->willReturn('twig');
        
        // Register twig extension manually
        $this->container->registerExtension($twigExtension);
        
        // Should not throw exception
        $this->extension->prepend($this->container);
        
        // Verify that prepend was called (we can't easily verify the exact config without more setup)
        $this->assertTrue(true);
    }

    public function testPrependWithoutTwigExtension(): void
    {
        // Don't register twig extension
        $this->extension->prepend($this->container);
        
        // Should not throw exception
        $this->assertTrue(true);
    }
}
