<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests for PerformanceController::getDriverName() method.
 *
 * This test class specifically tests the getDriverName() method which handles
 * driver name detection for both wrapped and unwrapped drivers.
 */
final class PerformanceControllerDriverNameTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
    }


    /**
     * Test getDriverName() with unwrapped driver through diagnose() method.
     */
    public function testGetDriverNameWithUnwrappedDriver(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('count')->willReturn(0);

        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $query->method('getSingleScalarResult')->willReturn(0);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $entityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $connection->method('executeQuery')->with('SELECT 1')->willReturn($result);
        $connection->method('getDatabase')->willReturn('test_db');

        // Create driver stub that implements getName() via __call magic method
        $driver = $this->getMockBuilder(\Doctrine\DBAL\Driver::class)
            ->addMethods(['getName'])
            ->getMockForAbstractClass();
        $driver->method('getName')->willReturn('pdo_mysql');
        $connection->method('getDriver')->willReturn($driver);

        $entityManager->method('getConnection')->willReturn($connection);
        $this->metricsService->method('getRepository')->willReturn($repository);
        $repository->method('getEntityManager')->willReturn($entityManager);

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
                null,
                null,
                false,
                false,
                null,
                0.5,
                1.0,
                20,
                50,
                20.0,
                50.0,
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                0,
                [200, 404, 500, 503],
                null,
                false,
                true,
                ['dev', 'test'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with(
                $this->anything(),
                $this->callback(function (array $data) {
                    return isset($data['diagnostic']['database_connection']['driver'])
                        && $data['diagnostic']['database_connection']['driver'] === 'pdo_mysql';
                })
            )
            ->willReturn(new Response());

        $request = Request::create('/performance/diagnose');
        $controller->diagnose($request);
    }

    /**
     * Test getDriverName() with wrapped driver through diagnose() method.
     */
    public function testGetDriverNameWithWrappedDriver(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('count')->willReturn(0);

        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $query->method('getSingleScalarResult')->willReturn(0);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $entityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $connection->method('executeQuery')->with('SELECT 1')->willReturn($result);
        $connection->method('getDatabase')->willReturn('test_db');

        // Create wrapped driver (simulating AbstractDriverMiddleware)
        // We'll use a mock that doesn't have getName() but has a 'driver' property accessible via reflection
        $wrappedDriver = $this->getMockBuilder(\Doctrine\DBAL\Driver::class)
            ->addMethods(['getName'])
            ->getMockForAbstractClass();
        $wrappedDriver->method('getName')->willReturn('pdo_pgsql');

        // Create a mock wrapper that simulates AbstractDriverMiddleware
        // We'll use a partial mock that allows us to set a 'driver' property
        $middlewareWrapper = $this->getMockBuilder(\Doctrine\DBAL\Driver::class)
            ->getMockForAbstractClass();
        
        // Use reflection to set the 'driver' property (simulating AbstractDriverMiddleware)
        $reflection = new \ReflectionClass($middlewareWrapper);
        if (!$reflection->hasProperty('driver')) {
            $driverProperty = $reflection->getProperty('driver');
            $driverProperty->setAccessible(true);
            $driverProperty->setValue($middlewareWrapper, $wrappedDriver);
        }

        // Mock connection to return the wrapper
        $connection->method('getDriver')->willReturn($middlewareWrapper);

        $entityManager->method('getConnection')->willReturn($connection);
        $this->metricsService->method('getRepository')->willReturn($repository);
        $repository->method('getEntityManager')->willReturn($entityManager);

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
                null,
                null,
                false,
                false,
                null,
                0.5,
                1.0,
                20,
                50,
                20.0,
                50.0,
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                0,
                [200, 404, 500, 503],
                null,
                false,
                true,
                ['dev', 'test'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with(
                $this->anything(),
                $this->callback(function (array $data) {
                    // Should detect the wrapped driver via reflection
                    return isset($data['diagnostic']['database_connection']['driver'])
                        && $data['diagnostic']['database_connection']['driver'] === 'pdo_pgsql';
                })
            )
            ->willReturn(new Response());

        $request = Request::create('/performance/diagnose');
        $controller->diagnose($request);
    }

    /**
     * Test getDriverName() fallback to MySQL platform class name through diagnose().
     */
    public function testGetDriverNameFallbackToMySQLPlatform(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('count')->willReturn(0);

        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $query->method('getSingleScalarResult')->willReturn(0);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $entityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $connection->method('executeQuery')->with('SELECT 1')->willReturn($result);
        $connection->method('getDatabase')->willReturn('test_db');

        // Create driver without getName() method
        $driver = $this->createMock(\Doctrine\DBAL\Driver::class);
        $connection->method('getDriver')->willReturn($driver);

        // Create platform with MySQL in class name
        $platform = $this->getMockBuilder(\Doctrine\DBAL\Platforms\AbstractPlatform::class)
            ->setMockClassName('MySQLPlatform')
            ->getMockForAbstractClass();
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $entityManager->method('getConnection')->willReturn($connection);
        $this->metricsService->method('getRepository')->willReturn($repository);
        $repository->method('getEntityManager')->willReturn($entityManager);

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
                null,
                null,
                false,
                false,
                null,
                0.5,
                1.0,
                20,
                50,
                20.0,
                50.0,
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                0,
                [200, 404, 500, 503],
                null,
                false,
                true,
                ['dev', 'test'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with(
                $this->anything(),
                $this->callback(function (array $data) {
                    return isset($data['diagnostic']['database_connection']['driver'])
                        && $data['diagnostic']['database_connection']['driver'] === 'pdo_mysql';
                })
            )
            ->willReturn(new Response());

        $request = Request::create('/performance/diagnose');
        $controller->diagnose($request);
    }

    /**
     * Test getDriverName() fallback to PostgreSQL platform class name through diagnose().
     */
    public function testGetDriverNameFallbackToPostgreSQLPlatform(): void
    {
        $this->testPlatformFallback('PostgreSQLPlatform', 'pdo_pgsql');
    }

    /**
     * Test getDriverName() fallback to SQLite platform class name through diagnose().
     */
    public function testGetDriverNameFallbackToSQLitePlatform(): void
    {
        $this->testPlatformFallback('SQLitePlatform', 'pdo_sqlite');
    }

    /**
     * Test getDriverName() fallback to SQLServer platform class name through diagnose().
     */
    public function testGetDriverNameFallbackToSQLServerPlatform(): void
    {
        $this->testPlatformFallback('SQLServerPlatform', 'pdo_sqlsrv');
    }

    /**
     * Test getDriverName() returns 'unknown' when all methods fail through diagnose().
     */
    public function testGetDriverNameReturnsUnknownWhenAllMethodsFail(): void
    {
        $this->testPlatformFallback('UnknownPlatform', 'unknown');
    }

    /**
     * Helper method to test platform fallback scenarios.
     */
    private function testPlatformFallback(string $platformClassName, string $expectedDriver): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('count')->willReturn(0);

        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $query->method('getSingleScalarResult')->willReturn(0);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $entityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $connection->method('executeQuery')->with('SELECT 1')->willReturn($result);
        $connection->method('getDatabase')->willReturn('test_db');

        // Create driver without getName() method
        $driver = $this->createMock(\Doctrine\DBAL\Driver::class);
        $connection->method('getDriver')->willReturn($driver);

        // Create platform with specified class name
        $platform = $this->getMockBuilder(\Doctrine\DBAL\Platforms\AbstractPlatform::class)
            ->setMockClassName($platformClassName)
            ->getMockForAbstractClass();
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $entityManager->method('getConnection')->willReturn($connection);
        $this->metricsService->method('getRepository')->willReturn($repository);
        $repository->method('getEntityManager')->willReturn($entityManager);

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
                null,
                null,
                false,
                false,
                null,
                0.5,
                1.0,
                20,
                50,
                20.0,
                50.0,
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                0,
                [200, 404, 500, 503],
                null,
                false,
                true,
                ['dev', 'test'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with(
                $this->anything(),
                $this->callback(function (array $data) use ($expectedDriver) {
                    return isset($data['diagnostic']['database_connection']['driver'])
                        && $data['diagnostic']['database_connection']['driver'] === $expectedDriver;
                })
            )
            ->willReturn(new Response());

        $request = Request::create('/performance/diagnose');
        $controller->diagnose($request);
    }

    /**
     * Test getDriverName() handles exception gracefully through diagnose().
     */
    public function testGetDriverNameHandlesExceptionGracefully(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('count')->willReturn(0);

        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $query->method('getSingleScalarResult')->willReturn(0);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $entityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $connection->method('executeQuery')->with('SELECT 1')->willReturn($result);
        $connection->method('getDatabase')->willReturn('test_db');
        
        // Make getDriver() throw an exception
        $connection->method('getDriver')->willThrowException(new \Exception('Driver error'));

        $entityManager->method('getConnection')->willReturn($connection);
        $this->metricsService->method('getRepository')->willReturn($repository);
        $repository->method('getEntityManager')->willReturn($entityManager);

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
                null,
                null,
                false,
                false,
                null,
                0.5,
                1.0,
                20,
                50,
                20.0,
                50.0,
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                0,
                [200, 404, 500, 503],
                null,
                false,
                true,
                ['dev', 'test'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with(
                $this->anything(),
                $this->callback(function (array $data) {
                    // Should return 'unknown' when exception occurs
                    return isset($data['diagnostic']['database_connection']['driver'])
                        && $data['diagnostic']['database_connection']['driver'] === 'unknown';
                })
            )
            ->willReturn(new Response());

        $request = Request::create('/performance/diagnose');
        $controller->diagnose($request);
    }

    /**
     * Test getDriverName() through diagnose() method with wrapped driver.
     */
    public function testDiagnoseWithWrappedDriverShowsCorrectDriverName(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('count')->willReturn(0);

        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $query->method('getSingleScalarResult')->willReturn(0);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $entityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $connection->method('executeQuery')->with('SELECT 1')->willReturn($result);
        $connection->method('getDatabase')->willReturn('test_db');

        // Create wrapped driver (simulating AbstractDriverMiddleware)
        $wrappedDriver = $this->createMock(\Doctrine\DBAL\Driver::class);
        $wrappedDriver->method('getName')->willReturn('pdo_pgsql');

        // Create middleware wrapper that doesn't have getName() but has 'driver' property
        // This simulates AbstractDriverMiddleware structure
        $middlewareWrapper = new class($wrappedDriver) implements \Doctrine\DBAL\Driver {
            private \Doctrine\DBAL\Driver $driver;

            public function __construct(\Doctrine\DBAL\Driver $driver)
            {
                $this->driver = $driver;
            }

            public function connect(array $params): \Doctrine\DBAL\Driver\Connection
            {
                return $this->driver->connect($params);
            }

            public function getDatabasePlatform(): \Doctrine\DBAL\Platforms\AbstractPlatform
            {
                return $this->driver->getDatabasePlatform();
            }

            public function getSchemaManager(\Doctrine\DBAL\Connection $conn, \Doctrine\DBAL\Platforms\AbstractPlatform $platform): \Doctrine\DBAL\Schema\AbstractSchemaManager
            {
                return $this->driver->getSchemaManager($conn, $platform);
            }

            public function getExceptionConverter(): \Doctrine\DBAL\Exception\Converter
            {
                return $this->driver->getExceptionConverter();
            }
        };

        $connection->method('getDriver')->willReturn($middlewareWrapper);

        $entityManager->method('getConnection')->willReturn($connection);
        $this->metricsService->method('getRepository')->willReturn($repository);
        $repository->method('getEntityManager')->willReturn($entityManager);

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
                null,
                null,
                false,
                false,
                null,
                0.5,
                1.0,
                20,
                50,
                20.0,
                50.0,
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                0,
                [200, 404, 500, 503],
                null,
                false,
                true,
                ['dev', 'test'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with(
                $this->anything(),
                $this->callback(function (array $data) {
                    // Check that driver name is correctly detected
                    return isset($data['diagnostic']['database_connection']['driver'])
                        && $data['diagnostic']['database_connection']['driver'] === 'pdo_pgsql';
                })
            )
            ->willReturn(new Response());

        $request = Request::create('/performance/diagnose');
        $controller->diagnose($request);
    }
}
