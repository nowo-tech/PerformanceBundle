<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Nowo\PerformanceBundle\DependencyInjection\Compiler\QueryTrackingMiddlewarePass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

/**
 * Tests for QueryTrackingMiddlewarePass.
 */
final class QueryTrackingMiddlewarePassTest extends TestCase
{
    private QueryTrackingMiddlewarePass $pass;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->pass = new QueryTrackingMiddlewarePass();
        $this->container = new ContainerBuilder();
    }

    public function testProcessDoesNothingWhenDoctrineExtensionNotAvailable(): void
    {
        // Container without Doctrine extension
        $this->container->setParameter('nowo_performance.enabled', true);
        $this->container->setParameter('nowo_performance.track_queries', true);
        $this->container->setParameter('nowo_performance.connection', 'default');

        $this->pass->process($this->container);

        // Should not register the middleware service
        $this->assertFalse($this->container->hasDefinition('nowo_performance.dbal.query_tracking_middleware'));
    }

    public function testProcessDoesNothingWhenEnabledParameterMissing(): void
    {
        // Add Doctrine extension mock
        $doctrineExtension = $this->createMock(ExtensionInterface::class);
        $this->container->registerExtension($doctrineExtension);
        $this->container->loadFromExtension('doctrine', []);

        $this->container->setParameter('nowo_performance.track_queries', true);
        $this->container->setParameter('nowo_performance.connection', 'default');

        $this->pass->process($this->container);

        $this->assertFalse($this->container->hasDefinition('nowo_performance.dbal.query_tracking_middleware'));
    }

    public function testProcessDoesNothingWhenTrackQueriesParameterMissing(): void
    {
        $doctrineExtension = $this->createMock(ExtensionInterface::class);
        $this->container->registerExtension($doctrineExtension);
        $this->container->loadFromExtension('doctrine', []);

        $this->container->setParameter('nowo_performance.enabled', true);
        $this->container->setParameter('nowo_performance.connection', 'default');

        $this->pass->process($this->container);

        $this->assertFalse($this->container->hasDefinition('nowo_performance.dbal.query_tracking_middleware'));
    }

    public function testProcessDoesNothingWhenConnectionParameterMissing(): void
    {
        $doctrineExtension = $this->createMock(ExtensionInterface::class);
        $this->container->registerExtension($doctrineExtension);
        $this->container->loadFromExtension('doctrine', []);

        $this->container->setParameter('nowo_performance.enabled', true);
        $this->container->setParameter('nowo_performance.track_queries', true);

        $this->pass->process($this->container);

        $this->assertFalse($this->container->hasDefinition('nowo_performance.dbal.query_tracking_middleware'));
    }

    public function testProcessDoesNothingWhenBundleDisabled(): void
    {
        $doctrineExtension = $this->createMock(ExtensionInterface::class);
        $this->container->registerExtension($doctrineExtension);
        $this->container->loadFromExtension('doctrine', []);

        $this->container->setParameter('nowo_performance.enabled', false);
        $this->container->setParameter('nowo_performance.track_queries', true);
        $this->container->setParameter('nowo_performance.connection', 'default');

        $this->pass->process($this->container);

        $this->assertFalse($this->container->hasDefinition('nowo_performance.dbal.query_tracking_middleware'));
    }

    public function testProcessDoesNothingWhenTrackQueriesDisabled(): void
    {
        $doctrineExtension = $this->createMock(ExtensionInterface::class);
        $this->container->registerExtension($doctrineExtension);
        $this->container->loadFromExtension('doctrine', []);

        $this->container->setParameter('nowo_performance.enabled', true);
        $this->container->setParameter('nowo_performance.track_queries', false);
        $this->container->setParameter('nowo_performance.connection', 'default');

        $this->pass->process($this->container);

        $this->assertFalse($this->container->hasDefinition('nowo_performance.dbal.query_tracking_middleware'));
    }

    public function testProcessRegistersMiddlewareWhenAllConditionsMet(): void
    {
        $doctrineExtension = $this->createMock(ExtensionInterface::class);
        $this->container->registerExtension($doctrineExtension);
        $this->container->loadFromExtension('doctrine', []);

        $this->container->setParameter('nowo_performance.enabled', true);
        $this->container->setParameter('nowo_performance.track_queries', true);
        $this->container->setParameter('nowo_performance.connection', 'default');

        $this->pass->process($this->container);

        $this->assertTrue($this->container->hasDefinition('nowo_performance.dbal.query_tracking_middleware'));

        $definition = $this->container->getDefinition('nowo_performance.dbal.query_tracking_middleware');
        $this->assertSame(QueryTrackingMiddleware::class, $definition->getClass());
        $this->assertFalse($definition->isPublic());
    }

    public function testProcessDoesNotOverrideExistingDefinition(): void
    {
        $doctrineExtension = $this->createMock(ExtensionInterface::class);
        $this->container->registerExtension($doctrineExtension);
        $this->container->loadFromExtension('doctrine', []);

        $this->container->setParameter('nowo_performance.enabled', true);
        $this->container->setParameter('nowo_performance.track_queries', true);
        $this->container->setParameter('nowo_performance.connection', 'default');

        // Pre-register the service with custom configuration
        $existingDefinition = new Definition(QueryTrackingMiddleware::class);
        $existingDefinition->setPublic(true);
        $existingDefinition->addTag('custom.tag');
        $this->container->setDefinition('nowo_performance.dbal.query_tracking_middleware', $existingDefinition);

        $this->pass->process($this->container);

        // Should not override existing definition
        $definition = $this->container->getDefinition('nowo_performance.dbal.query_tracking_middleware');
        $this->assertTrue($definition->isPublic());
        $this->assertTrue($definition->hasTag('custom.tag'));
    }

    public function testProcessWithCustomConnectionName(): void
    {
        $doctrineExtension = $this->createMock(ExtensionInterface::class);
        $this->container->registerExtension($doctrineExtension);
        $this->container->loadFromExtension('doctrine', []);

        $this->container->setParameter('nowo_performance.enabled', true);
        $this->container->setParameter('nowo_performance.track_queries', true);
        $this->container->setParameter('nowo_performance.connection', 'custom_connection');

        $this->pass->process($this->container);

        // Should still register middleware regardless of connection name
        $this->assertTrue($this->container->hasDefinition('nowo_performance.dbal.query_tracking_middleware'));
    }
}
