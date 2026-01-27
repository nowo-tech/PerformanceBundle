<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DependencyInjection;

use Nowo\PerformanceBundle\DependencyInjection\PerformanceExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests for PerformanceExtension environment configuration.
 *
 * Tests the default value change from ['dev', 'test'] to ['prod', 'dev', 'test'].
 */
final class PerformanceExtensionEnvironmentsTest extends TestCase
{
    private PerformanceExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new PerformanceExtension();
        $this->container = new ContainerBuilder();
    }

    public function testDefaultEnvironmentsIncludesProd(): void
    {
        $this->extension->load([], $this->container);

        $environments = $this->container->getParameter('nowo_performance.environments');
        
        $this->assertIsArray($environments);
        $this->assertContains('prod', $environments);
        $this->assertContains('dev', $environments);
        $this->assertContains('test', $environments);
        $this->assertCount(3, $environments);
    }

    public function testCustomEnvironmentsConfiguration(): void
    {
        $config = [
            'environments' => ['prod', 'stage'],
        ];

        $this->extension->load([$config], $this->container);

        $environments = $this->container->getParameter('nowo_performance.environments');
        
        $this->assertSame(['prod', 'stage'], $environments);
        $this->assertCount(2, $environments);
    }

    public function testEmptyEnvironmentsUsesDefault(): void
    {
        $config = [
            'enabled' => true,
            // environments not specified
        ];

        $this->extension->load([$config], $this->container);

        $environments = $this->container->getParameter('nowo_performance.environments');
        
        $this->assertSame(['prod', 'dev', 'test'], $environments);
    }

    public function testSingleEnvironmentConfiguration(): void
    {
        $config = [
            'environments' => ['prod'],
        ];

        $this->extension->load([$config], $this->container);

        $environments = $this->container->getParameter('nowo_performance.environments');
        
        $this->assertSame(['prod'], $environments);
        $this->assertCount(1, $environments);
    }

    public function testMultipleConfigsMergesEnvironments(): void
    {
        $config1 = [
            'environments' => ['prod'],
        ];
        $config2 = [
            'environments' => ['dev'],
        ];

        $this->extension->load([$config1, $config2], $this->container);

        // Symfony merges arrays, so the last one wins
        $environments = $this->container->getParameter('nowo_performance.environments');
        
        $this->assertSame(['dev'], $environments);
    }
}
