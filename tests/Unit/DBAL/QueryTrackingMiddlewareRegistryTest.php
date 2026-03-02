<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DBAL;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddlewareRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

use function is_string;

final class QueryTrackingMiddlewareRegistryTest extends TestCase
{
    public function testSupportsYamlMiddlewareConfigReturnsFalse(): void
    {
        $this->assertFalse(QueryTrackingMiddlewareRegistry::supportsYamlMiddlewareConfig());
    }

    public function testSupportsYamlMiddlewareReturnsFalse(): void
    {
        $this->assertFalse(QueryTrackingMiddlewareRegistry::supportsYamlMiddleware());
    }

    public function testDetectDoctrineBundleVersionReturnsNullOrString(): void
    {
        $version = QueryTrackingMiddlewareRegistry::detectDoctrineBundleVersion();
        $this->assertTrue($version === null || is_string($version));
        if ($version !== null) {
            $this->assertNotEmpty($version);
        }
    }

    public function testApplyMiddlewareReturnsFalseWhenRegistryReturnsNonConnection(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->with('default')->willReturn(new stdClass());

        $middleware = $this->createMock(QueryTrackingMiddleware::class);

        $this->assertFalse(QueryTrackingMiddlewareRegistry::applyMiddleware($registry, 'default', $middleware));
    }

    public function testApplyMiddlewareReturnsFalseWhenRegistryThrows(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willThrowException(new RuntimeException('no connection'));

        $middleware = $this->createMock(QueryTrackingMiddleware::class);

        $this->assertFalse(QueryTrackingMiddlewareRegistry::applyMiddleware($registry, 'default', $middleware));
    }

    public function testApplyMiddlewareWithCustomConnectionName(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->atLeastOnce())
            ->method('getConnection')
            ->with('custom_conn')
            ->willReturn(new stdClass());

        $middleware = $this->createMock(QueryTrackingMiddleware::class);

        $this->assertFalse(QueryTrackingMiddlewareRegistry::applyMiddleware($registry, 'custom_conn', $middleware));
    }
}
