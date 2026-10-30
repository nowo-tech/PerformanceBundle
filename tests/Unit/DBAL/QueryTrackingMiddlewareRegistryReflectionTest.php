<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DBAL;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\DBAL\QueryTrackingConnection;
use Nowo\PerformanceBundle\DBAL\QueryTrackingCounters;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddlewareRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use TypeError;

use function is_array;

/**
 * Every branch of the reflection-based driver wrapping (own and inherited driver property, already wrapped driver,
 * already wrapped internal connection, wrap failures) and of the DoctrineBundle version detection (Composer data,
 * fallback to the 3.x API).
 */
final class QueryTrackingMiddlewareRegistryReflectionTest extends TestCase
{
    private function registryFor(object $connection): ManagerRegistry
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willReturn($connection);

        return $registry;
    }

    private function driverOf(Connection $connection): Driver
    {
        $driver = (new ReflectionProperty(Connection::class, 'driver'))->getValue($connection);
        self::assertInstanceOf(Driver::class, $driver);

        return $driver;
    }

    public function testWrapsTheDriverOfAFreshConnection(): void
    {
        $connection = new Connection([], $this->createMock(Driver::class));

        self::assertTrue(QueryTrackingMiddlewareRegistry::applyMiddleware($this->registryFor($connection), 'default', new QueryTrackingMiddleware()));
        self::assertStringContainsString('AbstractDriverMiddleware', $this->driverOf($connection)::class);

        // second call: the driver is already wrapped
        self::assertTrue(QueryTrackingMiddlewareRegistry::applyMiddleware($this->registryFor($connection), 'default', new QueryTrackingMiddleware()));
    }

    public function testInternalConnectionAlreadyTracked(): void
    {
        $connection = new Connection([], $this->createMock(Driver::class));
        (new ReflectionProperty(Connection::class, '_conn'))->setValue($connection, new QueryTrackingConnection($this->createMock(DriverConnection::class), new QueryTrackingCounters()));

        self::assertTrue(QueryTrackingMiddlewareRegistry::applyMiddleware($this->registryFor($connection), 'default', new QueryTrackingMiddleware()));
    }

    public function testFallsBackToTheInheritedDriverPropertyWhenTheFirstWrapFails(): void
    {
        $connection = new class([], $this->createMock(Driver::class)) extends Connection {};
        $calls      = 0;
        $middleware = $this->createMock(QueryTrackingMiddleware::class);
        $middleware->method('wrap')->willReturnCallback(static function (Driver $driver) use (&$calls): Driver {
            if (++$calls === 1) {
                throw new TypeError('first wrap fails');
            }

            return (new QueryTrackingMiddleware())->wrap($driver);
        });

        self::assertTrue(QueryTrackingMiddlewareRegistry::applyMiddleware($this->registryFor($connection), 'default', $middleware));
        self::assertSame(2, $calls);
        self::assertStringContainsString('AbstractDriverMiddleware', $this->driverOf($connection)::class);
    }

    public function testInheritedDriverAlreadyWrappedWhileTheFirstWrapFailed(): void
    {
        $connection = new class([], $this->createMock(Driver::class)) extends Connection {};
        $middleware = $this->createMock(QueryTrackingMiddleware::class);
        $middleware->method('wrap')->willReturnCallback(static function (Driver $driver) use ($connection): Driver {
            // another listener wrapped the driver meanwhile, then this wrap fails
            (new ReflectionProperty(Connection::class, 'driver'))->setValue($connection, (new QueryTrackingMiddleware())->wrap($driver));

            throw new TypeError('wrap fails');
        });

        self::assertTrue(QueryTrackingMiddlewareRegistry::applyMiddleware($this->registryFor($connection), 'default', $middleware));
    }

    public function testEveryWrapFailingFallsBackToTheConnectionWrapperCheck(): void
    {
        $connection = new class([], $this->createMock(Driver::class)) extends Connection {};
        $middleware = $this->createMock(QueryTrackingMiddleware::class);
        $middleware->method('wrap')->willThrowException(new TypeError('wrap fails'));

        self::assertFalse(QueryTrackingMiddlewareRegistry::applyMiddleware($this->registryFor($connection), 'default', $middleware));
    }

    public function testReflectionExceptionOnAPlainConnectionReturnsFalse(): void
    {
        $connection = new Connection([], $this->createMock(Driver::class));
        $middleware = $this->createMock(QueryTrackingMiddleware::class);
        $middleware->method('wrap')->willThrowException(new ReflectionException('wrap fails'));

        self::assertFalse(QueryTrackingMiddlewareRegistry::applyMiddleware($this->registryFor($connection), 'default', $middleware));
    }

    public function testConnectionWrapperCheckFailureReturnsFalse(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willReturnOnConsecutiveCalls(new stdClass(), $this->throwException(new RuntimeException('gone')));

        self::assertFalse(QueryTrackingMiddlewareRegistry::applyMiddleware($registry, 'default', new QueryTrackingMiddleware()));
    }

    public function testDoctrineBundleVersionFromComposerData(): void
    {
        self::assertNotNull(QueryTrackingMiddlewareRegistry::detectDoctrineBundleVersion());
    }

    public function testDoctrineBundleVersionWithoutAComposerVersionAssumesThe3xApi(): void
    {
        $this->withComposerData(static function (array $data): array {
            unset($data['versions']['doctrine/doctrine-bundle']['version']);

            return $data;
        }, static function (): void {
            self::assertSame('3.0.0', QueryTrackingMiddlewareRegistry::detectDoctrineBundleVersion());
        });
    }

    public function testDoctrineBundleUnknownToComposerAssumesThe3xApi(): void
    {
        $this->withComposerData(static function (array $data): array {
            unset($data['versions']['doctrine/doctrine-bundle']);

            return $data;
        }, static function (): void {
            self::assertSame('3.0.0', QueryTrackingMiddlewareRegistry::detectDoctrineBundleVersion());
        });
    }

    /**
     * Runs $test with the Composer runtime data of every loaded vendor directory rewritten by $rewrite, then restores it.
     *
     * @param callable(array<string, mixed>): array<string, mixed> $rewrite
     * @param callable(): void $test
     */
    private function withComposerData(callable $rewrite, callable $test): void
    {
        InstalledVersions::getVersion('doctrine/doctrine-bundle'); // loads the caches
        $byVendor  = new ReflectionProperty(InstalledVersions::class, 'installedByVendor');
        $installed = new ReflectionProperty(InstalledVersions::class, 'installed');
        $saved     = [$byVendor->getValue(), $installed->getValue()];

        try {
            $vendors = $saved[0];
            self::assertIsArray($vendors);
            foreach ($vendors as $dir => $data) {
                $vendors[$dir] = is_array($data) ? $rewrite($data) : $data;
            }
            $byVendor->setValue(null, $vendors);
            if (is_array($saved[1])) {
                $installed->setValue(null, $rewrite($saved[1]));
            }
            $test();
        } finally {
            $byVendor->setValue(null, $saved[0]);
            $installed->setValue(null, $saved[1]);
        }
    }
}
