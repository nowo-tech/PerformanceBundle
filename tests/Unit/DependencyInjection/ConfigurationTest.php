<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DependencyInjection;

use Nowo\PerformanceBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    private Processor $processor;

    protected function setUp(): void
    {
        $this->processor = new Processor();
    }

    public function testDefaultConfiguration(): void
    {
        $configuration = new Configuration();
        $config = $this->processor->processConfiguration($configuration, []);

        $this->assertTrue($config['enabled']);
        $this->assertSame(['dev', 'test'], $config['environments']);
        $this->assertSame('default', $config['connection']);
        $this->assertSame('routes_data', $config['table_name']);
        $this->assertTrue($config['track_queries']);
        $this->assertTrue($config['track_request_time']);
        $this->assertSame(['_wdt', '_profiler', '_error'], $config['ignore_routes']);
        
        // Dashboard configuration defaults
        $this->assertTrue($config['dashboard']['enabled']);
        $this->assertSame('/performance', $config['dashboard']['path']);
        $this->assertSame('', $config['dashboard']['prefix']);
        $this->assertSame([], $config['dashboard']['roles']);
    }

    public function testCustomConfiguration(): void
    {
        $configuration = new Configuration();
        $config = $this->processor->processConfiguration($configuration, [[
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
        ]]);

        $this->assertFalse($config['enabled']);
        $this->assertSame(['prod'], $config['environments']);
        $this->assertSame('custom_connection', $config['connection']);
        $this->assertSame('custom_table', $config['table_name']);
        $this->assertFalse($config['track_queries']);
        $this->assertFalse($config['track_request_time']);
        $this->assertSame(['_custom'], $config['ignore_routes']);
        
        // Dashboard configuration
        $this->assertFalse($config['dashboard']['enabled']);
        $this->assertSame('/metrics', $config['dashboard']['path']);
        $this->assertSame('/admin', $config['dashboard']['prefix']);
        $this->assertSame(['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER'], $config['dashboard']['roles']);
    }

    public function testPartialConfiguration(): void
    {
        $configuration = new Configuration();
        $config = $this->processor->processConfiguration($configuration, [[
            'enabled' => false,
        ]]);

        $this->assertFalse($config['enabled']);
        $this->assertSame(['dev', 'test'], $config['environments']); // Default
        $this->assertSame('default', $config['connection']); // Default
        
        // Dashboard should have defaults even when not specified
        $this->assertTrue($config['dashboard']['enabled']);
        $this->assertSame('/performance', $config['dashboard']['path']);
        $this->assertSame('', $config['dashboard']['prefix']);
        $this->assertSame([], $config['dashboard']['roles']);
    }

    public function testDashboardConfigurationWithRoles(): void
    {
        $configuration = new Configuration();
        $config = $this->processor->processConfiguration($configuration, [[
            'dashboard' => [
                'roles' => ['ROLE_ADMIN'],
            ],
        ]]);

        $this->assertSame(['ROLE_ADMIN'], $config['dashboard']['roles']);
        $this->assertTrue($config['dashboard']['enabled']); // Default
        $this->assertSame('/performance', $config['dashboard']['path']); // Default
    }
}
