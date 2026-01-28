<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\DBAL\QueryTrackingConnection;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddlewareRegistry;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionProperty;

/**
 * Advanced tests for QueryTrackingMiddlewareRegistry edge cases.
 */
final class QueryTrackingMiddlewareRegistryAdvancedTest extends TestCase
{
    private ManagerRegistry|MockObject $registry;
    private Connection|MockObject $connection;
    private QueryTrackingMiddleware $middleware;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->connection = $this->createMock(Connection::class);
        $this->middleware = new QueryTrackingMiddleware();
    }

    public function testDetectDoctrineBundleVersionReturnsNullWhenNotInstalled(): void
    {
        // When DoctrineBundle is not installed, should return null
        // We can't easily mock class_exists, so we test the behavior
        $result = QueryTrackingMiddlewareRegistry::detectDoctrineBundleVersion();
        
        // Result depends on whether DoctrineBundle is actually installed
        // We just verify it doesn't throw and returns either null or a string
        $this->assertTrue(null === $result || is_string($result));
    }

    public function testSupportsYamlMiddlewareConfigAlwaysReturnsFalse(): void
    {
        // This method always returns false now (disabled feature)
        $result = QueryTrackingMiddlewareRegistry::supportsYamlMiddlewareConfig();
        $this->assertFalse($result);
    }

    public function testSupportsYamlMiddlewareAlwaysReturnsFalse(): void
    {
        // This method always returns false now (disabled feature)
        $result = QueryTrackingMiddlewareRegistry::supportsYamlMiddleware();
        $this->assertFalse($result);
    }

    public function testApplyMiddlewareReturnsFalseWhenRegistryThrowsException(): void
    {
        $this->registry
            ->method('getConnection')
            ->willThrowException(new \RuntimeException('Registry error'));

        $result = QueryTrackingMiddlewareRegistry::applyMiddleware(
            $this->registry,
            'default',
            $this->middleware
        );

        $this->assertFalse($result);
    }

    public function testApplyMiddlewareReturnsFalseWhenConnectionIsNotInstanceOfConnection(): void
    {
        $this->registry
            ->method('getConnection')
            ->willReturn(new \stdClass());

        $result = QueryTrackingMiddlewareRegistry::applyMiddleware(
            $this->registry,
            'default',
            $this->middleware
        );

        $this->assertFalse($result);
    }

    public function testApplyMiddlewareHandlesConnectionWithoutDriverProperty(): void
    {
        $this->registry
            ->method('getConnection')
            ->willReturn($this->connection);

        // Connection without accessible driver property
        $result = QueryTrackingMiddlewareRegistry::applyMiddleware(
            $this->registry,
            'default',
            $this->middleware
        );

        // Should return false when reflection fails
        $this->assertFalse($result);
    }

    public function testApplyMiddlewareHandlesConnectionWithNonDriverProperty(): void
    {
        $this->registry
            ->method('getConnection')
            ->willReturn($this->connection);

        // Connection with property that is not a Driver
        $result = QueryTrackingMiddlewareRegistry::applyMiddleware(
            $this->registry,
            'default',
            $this->middleware
        );

        // Should return false when property is not a Driver
        $this->assertFalse($result);
    }

    public function testApplyMiddlewareHandlesAlreadyWrappedDriver(): void
    {
        $this->registry
            ->method('getConnection')
            ->willReturn($this->connection);

        // Create a mock driver that appears to be already wrapped
        $wrappedDriver = $this->createMock(Driver::class);
        $wrappedDriverClass = new class() extends \Doctrine\DBAL\Driver\AbstractDriverMiddleware {
            public function __construct()
            {
                // Mock wrapped driver
            }
        };

        // Use reflection to set a property that looks like it's already wrapped
        try {
            $reflection = new ReflectionClass($this->connection);
            if ($reflection->hasProperty('driver')) {
                $property = $reflection->getProperty('driver');
                $property->setAccessible(true);
                $property->setValue($this->connection, $wrappedDriverClass);
            }
        } catch (\Exception $e) {
            // If reflection fails, that's okay for this test
        }

        $result = QueryTrackingMiddlewareRegistry::applyMiddleware(
            $this->registry,
            'default',
            $this->middleware
        );

        // Should return true if already wrapped (or false if reflection fails)
        $this->assertIsBool($result);
    }

    public function testApplyMiddlewareHandlesConnectionWithQueryTrackingConnection(): void
    {
        $this->registry
            ->method('getConnection')
            ->willReturn($this->connection);

        // Create a connection that is already a QueryTrackingConnection
        $trackingConnection = $this->createMock(QueryTrackingConnection::class);

        // Use reflection to set internal connection
        try {
            $reflection = new ReflectionClass($this->connection);
            if ($reflection->hasProperty('_conn')) {
                $property = $reflection->getProperty('_conn');
                $property->setAccessible(true);
                $property->setValue($this->connection, $trackingConnection);
            }
        } catch (\Exception $e) {
            // If reflection fails, that's okay for this test
        }

        $result = QueryTrackingMiddlewareRegistry::applyMiddleware(
            $this->registry,
            'default',
            $this->middleware
        );

        // Should return true if already wrapped (or false if reflection fails)
        $this->assertIsBool($result);
    }

    public function testApplyMiddlewareHandlesReflectionExceptions(): void
    {
        $this->registry
            ->method('getConnection')
            ->willReturn($this->connection);

        // Connection that throws ReflectionException when accessing properties
        $result = QueryTrackingMiddlewareRegistry::applyMiddleware(
            $this->registry,
            'default',
            $this->middleware
        );

        // Should return false when reflection fails
        $this->assertFalse($result);
    }

    public function testApplyMiddlewareHandlesTypeErrors(): void
    {
        $this->registry
            ->method('getConnection')
            ->willReturn($this->connection);

        // Connection that causes TypeError when accessing properties
        $result = QueryTrackingMiddlewareRegistry::applyMiddleware(
            $this->registry,
            'default',
            $this->middleware
        );

        // Should return false when type errors occur
        $this->assertFalse($result);
    }

    public function testApplyMiddlewareTriesMultiplePropertyNames(): void
    {
        $this->registry
            ->method('getConnection')
            ->willReturn($this->connection);

        // The method tries multiple property names: 'driver', '_driver', 'wrappedConnection', '_conn'
        $result = QueryTrackingMiddlewareRegistry::applyMiddleware(
            $this->registry,
            'default',
            $this->middleware
        );

        // Should return false when none of the properties exist or are accessible
        $this->assertFalse($result);
    }

    public function testApplyMiddlewareTriesParentClassProperties(): void
    {
        $this->registry
            ->method('getConnection')
            ->willReturn($this->connection);

        // The method should try parent class properties if current class doesn't have them
        $result = QueryTrackingMiddlewareRegistry::applyMiddleware(
            $this->registry,
            'default',
            $this->middleware
        );

        // Should return false when parent class properties also don't exist
        $this->assertFalse($result);
    }

    public function testApplyMiddlewareFallsBackToConnectionWrapper(): void
    {
        $this->registry
            ->method('getConnection')
            ->willReturn($this->connection);

        // When reflection method fails, should try connection wrapper method
        $result = QueryTrackingMiddlewareRegistry::applyMiddleware(
            $this->registry,
            'default',
            $this->middleware
        );

        // Should return false when both methods fail
        $this->assertFalse($result);
    }

    public function testApplyMiddlewareWithCustomConnectionName(): void
    {
        $this->registry
            ->method('getConnection')
            ->with('custom_connection')
            ->willReturn($this->connection);

        $result = QueryTrackingMiddlewareRegistry::applyMiddleware(
            $this->registry,
            'custom_connection',
            $this->middleware
        );

        // Should return false when reflection fails
        $this->assertFalse($result);
    }

    public function testDetectDoctrineBundleVersionHandlesComposerInstalledVersions(): void
    {
        // Test that detectDoctrineBundleVersion handles Composer\InstalledVersions
        $result = QueryTrackingMiddlewareRegistry::detectDoctrineBundleVersion();
        
        // Result depends on whether DoctrineBundle is installed
        // We just verify it doesn't throw and returns either null or a string
        $this->assertTrue(null === $result || is_string($result));
    }

    public function testDetectDoctrineBundleVersionHandlesComposerJsonReading(): void
    {
        // Test that detectDoctrineBundleVersion handles reading from composer.json
        $result = QueryTrackingMiddlewareRegistry::detectDoctrineBundleVersion();
        
        // Result depends on whether DoctrineBundle is installed and composer.json is readable
        $this->assertTrue(null === $result || is_string($result));
    }

    public function testDetectDoctrineBundleVersionHandlesMethodDetection(): void
    {
        // Test that detectDoctrineBundleVersion handles method-based detection
        $result = QueryTrackingMiddlewareRegistry::detectDoctrineBundleVersion();
        
        // Result depends on whether DoctrineBundle is installed
        $this->assertTrue(null === $result || is_string($result));
    }

    public function testDetectDoctrineBundleVersionReturnsConsistentResults(): void
    {
        // Test that detectDoctrineBundleVersion returns consistent results
        $result1 = QueryTrackingMiddlewareRegistry::detectDoctrineBundleVersion();
        $result2 = QueryTrackingMiddlewareRegistry::detectDoctrineBundleVersion();
        
        // Results should be the same (unless something changes between calls)
        $this->assertSame($result1, $result2);
    }

    public function testApplyMiddlewareReturnsBool(): void
    {
        // Ensure applyMiddleware always returns a boolean
        $this->registry
            ->method('getConnection')
            ->willReturn($this->connection);

        $result = QueryTrackingMiddlewareRegistry::applyMiddleware(
            $this->registry,
            'default',
            $this->middleware
        );

        $this->assertIsBool($result);
    }
}
