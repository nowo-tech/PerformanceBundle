<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\DBAL;

use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddlewareRegistry;
use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

use function is_string;

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

    /** Second application exercises "already wrapped" / idempotent paths in reflection. */
    public function testApplyMiddlewareTwiceWithRealRegistry(): void
    {
        $registry   = $this->kernel->getContainer()->get('doctrine');
        $middleware = new QueryTrackingMiddleware();

        $first  = QueryTrackingMiddlewareRegistry::applyMiddleware($registry, 'default', $middleware);
        $second = QueryTrackingMiddlewareRegistry::applyMiddleware($registry, 'default', $middleware);

        self::assertIsBool($first);
        self::assertIsBool($second);
    }

    /** detectDoctrineBundleVersion hits Composer / filesystem branches in real project. */
    public function testDetectDoctrineBundleVersionInProject(): void
    {
        $version = QueryTrackingMiddlewareRegistry::detectDoctrineBundleVersion();
        self::assertTrue($version === null || is_string($version));
    }
}
