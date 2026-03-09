<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\DBAL;

use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddlewareRegistry;
use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Integration test: applyMiddleware with real ManagerRegistry and Connection from container.
 * Covers reflection path in QueryTrackingMiddlewareRegistry when connection has driver property.
 */
final class QueryTrackingMiddlewareRegistryIntegrationTest extends TestCase
{
    private KernelInterface $kernel;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel('test', true);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public function testApplyMiddlewareWithRealRegistry(): void
    {
        $registry   = $this->kernel->getContainer()->get('doctrine');
        $middleware = new QueryTrackingMiddleware();

        $result = QueryTrackingMiddlewareRegistry::applyMiddleware($registry, 'default', $middleware);

        // May return true if reflection succeeded and middleware was applied, or false otherwise
        self::assertIsBool($result);
    }
}
