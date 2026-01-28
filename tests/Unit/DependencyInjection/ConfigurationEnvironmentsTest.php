<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DependencyInjection;

use Nowo\PerformanceBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

/**
 * Tests for Configuration environment defaults.
 *
 * Tests the default value change from ['dev', 'test'] to ['prod', 'dev', 'test'].
 */
final class ConfigurationEnvironmentsTest extends TestCase
{
    private Processor $processor;

    protected function setUp(): void
    {
        $this->processor = new Processor();
    }

    public function testDefaultEnvironmentsIncludesProd(): void
    {
        $configuration = new Configuration();
        $config = $this->processor->processConfiguration($configuration, []);

        $environments = $config['environments'];
        
        $this->assertIsArray($environments);
        $this->assertContains('prod', $environments);
        $this->assertContains('dev', $environments);
        $this->assertContains('test', $environments);
        $this->assertCount(3, $environments);
        $this->assertSame(['prod', 'dev', 'test'], $environments);
    }

    public function testCustomEnvironmentsConfiguration(): void
    {
        $configuration = new Configuration();
        $config = $this->processor->processConfiguration($configuration, [[
            'environments' => ['prod', 'stage'],
        ]]);

        $this->assertSame(['prod', 'stage'], $config['environments']);
    }

    public function testEmptyEnvironmentsUsesDefault(): void
    {
        $configuration = new Configuration();
        $config = $this->processor->processConfiguration($configuration, [[
            'enabled' => true,
            // environments not specified
        ]]);

        $this->assertSame(['prod', 'dev', 'test'], $config['environments']);
    }

    public function testSingleEnvironmentConfiguration(): void
    {
        $configuration = new Configuration();
        $config = $this->processor->processConfiguration($configuration, [[
            'environments' => ['prod'],
        ]]);

        $this->assertSame(['prod'], $config['environments']);
        $this->assertCount(1, $config['environments']);
    }

    public function testEnvironmentsOrderPreserved(): void
    {
        $configuration = new Configuration();
        $config = $this->processor->processConfiguration($configuration, [[
            'environments' => ['test', 'prod', 'dev'],
        ]]);

        $this->assertSame(['test', 'prod', 'dev'], $config['environments']);
    }

    public function testEnvironmentsWithDuplicates(): void
    {
        $configuration = new Configuration();
        $config = $this->processor->processConfiguration($configuration, [[
            'environments' => ['prod', 'dev', 'prod'],
        ]]);

        // Configuration processor should preserve duplicates (validation would happen elsewhere)
        $this->assertSame(['prod', 'dev', 'prod'], $config['environments']);
    }
}
