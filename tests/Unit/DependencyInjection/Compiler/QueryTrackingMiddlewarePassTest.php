<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Nowo\PerformanceBundle\DependencyInjection\Compiler\QueryTrackingMiddlewarePass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

final class QueryTrackingMiddlewarePassTest extends TestCase
{
    private const MIDDLEWARE_ID = 'nowo_performance.dbal.query_tracking_middleware';

    public function testProcessDoesNothingWhenDoctrineExtensionMissing(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_performance.enabled', true);
        $container->setParameter('nowo_performance.track_queries', true);
        $container->setParameter('nowo_performance.connection', 'default');

        $pass = new QueryTrackingMiddlewarePass();
        $pass->process($container);

        $this->assertFalse($container->hasDefinition(self::MIDDLEWARE_ID));
    }

    public function testProcessDoesNothingWhenEnabledParameterMissing(): void
    {
        $container = new ContainerBuilder();
        $this->registerDoctrineExtension($container);
        $container->setParameter('nowo_performance.track_queries', true);
        $container->setParameter('nowo_performance.connection', 'default');

        $pass = new QueryTrackingMiddlewarePass();
        $pass->process($container);

        $this->assertFalse($container->hasDefinition(self::MIDDLEWARE_ID));
    }

    public function testProcessDoesNothingWhenTrackQueriesParameterMissing(): void
    {
        $container = new ContainerBuilder();
        $this->registerDoctrineExtension($container);
        $container->setParameter('nowo_performance.enabled', true);
        $container->setParameter('nowo_performance.connection', 'default');

        $pass = new QueryTrackingMiddlewarePass();
        $pass->process($container);

        $this->assertFalse($container->hasDefinition(self::MIDDLEWARE_ID));
    }

    public function testProcessDoesNothingWhenConnectionParameterMissing(): void
    {
        $container = new ContainerBuilder();
        $this->registerDoctrineExtension($container);
        $container->setParameter('nowo_performance.enabled', true);
        $container->setParameter('nowo_performance.track_queries', true);

        $pass = new QueryTrackingMiddlewarePass();
        $pass->process($container);

        $this->assertFalse($container->hasDefinition(self::MIDDLEWARE_ID));
    }

    public function testProcessDoesNothingWhenDisabled(): void
    {
        $container = new ContainerBuilder();
        $this->registerDoctrineExtension($container);
        $container->setParameter('nowo_performance.enabled', false);
        $container->setParameter('nowo_performance.track_queries', true);
        $container->setParameter('nowo_performance.connection', 'default');

        $pass = new QueryTrackingMiddlewarePass();
        $pass->process($container);

        $this->assertFalse($container->hasDefinition(self::MIDDLEWARE_ID));
    }

    public function testProcessDoesNothingWhenTrackQueriesDisabled(): void
    {
        $container = new ContainerBuilder();
        $this->registerDoctrineExtension($container);
        $container->setParameter('nowo_performance.enabled', true);
        $container->setParameter('nowo_performance.track_queries', false);
        $container->setParameter('nowo_performance.connection', 'default');

        $pass = new QueryTrackingMiddlewarePass();
        $pass->process($container);

        $this->assertFalse($container->hasDefinition(self::MIDDLEWARE_ID));
    }

    public function testProcessRegistersMiddlewareWhenEnabledAndTrackQueries(): void
    {
        $container = new ContainerBuilder();
        $this->registerDoctrineExtension($container);
        $container->setParameter('nowo_performance.enabled', true);
        $container->setParameter('nowo_performance.track_queries', true);
        $container->setParameter('nowo_performance.connection', 'default');

        $pass = new QueryTrackingMiddlewarePass();
        $pass->process($container);

        $this->assertTrue($container->hasDefinition(self::MIDDLEWARE_ID));
        $def = $container->getDefinition(self::MIDDLEWARE_ID);
        $this->assertSame(QueryTrackingMiddleware::class, $def->getClass());
        $this->assertFalse($def->isPublic());
    }

    public function testProcessDoesNotOverwriteExistingMiddlewareDefinition(): void
    {
        $container = new ContainerBuilder();
        $this->registerDoctrineExtension($container);
        $container->setParameter('nowo_performance.enabled', true);
        $container->setParameter('nowo_performance.track_queries', true);
        $container->setParameter('nowo_performance.connection', 'default');
        $container->register(self::MIDDLEWARE_ID, QueryTrackingMiddleware::class)->setPublic(true);

        $pass = new QueryTrackingMiddlewarePass();
        $pass->process($container);

        $def = $container->getDefinition(self::MIDDLEWARE_ID);
        $this->assertTrue($def->isPublic(), 'Existing definition should not be overwritten');
    }

    private function registerDoctrineExtension(ContainerBuilder $container): void
    {
        $ext = $this->createMock(ExtensionInterface::class);
        $ext->method('getAlias')->willReturn('doctrine');
        $container->registerExtension($ext);
    }
}
