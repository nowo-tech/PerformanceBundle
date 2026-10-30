<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\Mapping\ClassMetadata as PersistenceClassMetadata;
use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\EventSubscriber\PerformanceMetricsSubscriber;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Nowo\PerformanceBundle\Service\TableStatusChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use RuntimeException;
use stdClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function array_fill;
use function implode;

/**
 * REQ-TEST-003: diagnose() branches (table checks with and without the TableStatusChecker, subscriber detection,
 * issue / warning / suggestion messages) and the private helpers it relies on (driver name, ignored routes), plus
 * the page-factory fallback of a controller built without the service.
 */
final class PerformanceControllerDiagnoseGapsTest extends TestCase
{
    use ControllerTestHarness;

    private MockObject&PerformanceMetricsService $metrics;
    private MockObject&RouteDataRepository $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RouteDataRepository::class);
        $this->metrics    = $this->createMock(PerformanceMetricsService::class);
        $this->metrics->method('getRepository')->willReturn($this->repository);
    }

    public function testDiagnoseReportsEveryIssueWarningAndSuggestion(): void
    {
        $this->metrics->method('getConnection')->willThrowException(new RuntimeException('no db'));
        $checker = $this->createMock(TableStatusChecker::class);
        $checker->method('getMainTableStatus')->willReturn(['exists' => true, 'complete' => false, 'table_name' => 'routes_data', 'missing_columns' => ['env']]);
        $checker->method('getRecordsTableStatus')->willReturn(['exists' => true, 'complete' => false, 'table_name' => 'routes_data_records', 'missing_columns' => ['user_id']]);
        $ignore = ['', '*nomatch*', ...array_fill(0, 9, 'other'), 'app_x'];

        $controller = $this->controller($this->metrics, [
            'bundleEnabled'       => false,
            'allowedEnvironments' => ['prod'],
            'samplingRate'        => 0.5,
            'trackQueries'        => false,
            'trackRequestTime'    => false,
            'ignoreRoutes'        => $ignore,
            'tableStatusChecker'  => $checker,
            'enableAccessRecords' => true,
            'checkTableStatus'    => true,
        ]);
        $controller->setContainer($this->container([
            'event_dispatcher'                  => new RuntimeException('dispatcher broken'),
            PerformanceMetricsSubscriber::class => new RuntimeException('subscriber broken'),
        ]));
        $request = Request::create('/performance/diagnose');
        $request->attributes->set('_route', 'app_x_sub');

        $controller->diagnose($request);

        $diagnostic = $this->rendered['diagnostic'];
        $all        = implode("\n", [...$diagnostic['issues'], ...$diagnostic['warnings'], ...$diagnostic['suggestions']]);
        foreach (['deshabilitado', 'entornos permitidos', 'no db', 'Faltan columnas: env', 'create-table --update', 'Faltan columnas: user_id', 'create-records-table --update', 'sampling rate', 'track_queries o track_request_time', 'Tanto el tracking', 'muchas rutas ignoradas'] as $needle) {
            self::assertStringContainsStringIgnoringCase($needle, $all);
        }
        self::assertTrue($diagnostic['route_tracking']['current_route_ignored']);
        self::assertTrue($diagnostic['subscriber_status']['subscriber_registered']);
    }

    public function testDiagnoseReportsTableCheckerErrors(): void
    {
        $checker = $this->createMock(TableStatusChecker::class);
        $checker->method('getMainTableStatus')->willThrowException(new RuntimeException('introspection failed'));

        $this->controller($this->metrics, ['tableStatusChecker' => $checker, 'checkTableStatus' => true])->diagnose(Request::create('/x'));

        self::assertStringContainsString('introspection failed', implode("\n", $this->rendered['diagnostic']['issues']));
    }

    public function testDiagnoseChecksMissingRecordsTableWithoutChecker(): void
    {
        $metadata = $this->createMock(PersistenceClassMetadata::class);
        $schema   = $this->createMock(AbstractSchemaManager::class);
        $schema->method('tablesExist')->with(['nowo_performance_records'])->willReturn(false);
        $this->entityManager($metadata, $schema);

        $this->fallbackController()->diagnose(Request::create('/x'));

        $status = $this->rendered['diagnostic']['table_status'];
        self::assertSame('nowo_performance_records', $status['records_table_name']);
        self::assertFalse($status['records_table_exists']);
    }

    public function testDiagnoseListsMissingRecordsColumnsWithoutChecker(): void
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getTableName')->willReturn('recs');
        $metadata->method('getFieldNames')->willReturn(['id', 'statusCode']);
        $metadata->method('getColumnName')->willReturnMap([['id', 'id'], ['statusCode', 'status_code']]);
        $schema = $this->createMock(AbstractSchemaManager::class);
        $schema->method('tablesExist')->willReturn(true);
        $schema->method('introspectTable')->with('recs')->willReturn(new Table('recs', [new Column('ID', Type::getType('integer'))]));
        $this->entityManager($metadata, $schema);

        $this->fallbackController()->diagnose(Request::create('/x'));

        $status = $this->rendered['diagnostic']['table_status'];
        self::assertSame(['status_code'], $status['records_missing_columns']);
        self::assertFalse($status['records_table_complete']);
    }

    public function testDiagnoseReportsRecordsTableErrorsWithoutChecker(): void
    {
        $this->metrics->method('getEntityManager')->willThrowException(new RuntimeException('em closed'));

        $this->fallbackController()->diagnose(Request::create('/x'));

        self::assertStringContainsString('em closed', implode("\n", $this->rendered['diagnostic']['warnings']));
    }

    public function testDiagnoseFindsSubscriberAmongEventListeners(): void
    {
        $subscriber = $this->createMock(PerformanceMetricsSubscriber::class);
        $dispatcher = new class($subscriber) {
            public function __construct(private readonly object $subscriber)
            {
            }

            /**
             * @return list<mixed>
             */
            public function getListeners(string $eventName): array
            {
                if ($eventName === KernelEvents::REQUEST) {
                    throw new RuntimeException('listeners unavailable');
                }

                return [new stdClass(), [$this->subscriber, 'onKernelTerminate']];
            }
        };
        $controller = $this->controller($this->metrics);
        $controller->setContainer($this->container(['event_dispatcher' => $dispatcher]));

        $controller->diagnose(Request::create('/x'));

        self::assertSame('Found in ' . KernelEvents::TERMINATE . ' listeners', $this->rendered['diagnostic']['subscriber_status']['detection_method']);
    }

    public function testDiagnoseFindsSubscriberServiceInContainer(): void
    {
        $controller = $this->controller($this->metrics);
        $controller->setContainer($this->container([PerformanceMetricsSubscriber::class => $this->createMock(PerformanceMetricsSubscriber::class)]));

        $controller->diagnose(Request::create('/x'));

        self::assertSame('Found in container by FQCN', $this->rendered['diagnostic']['subscriber_status']['detection_method']);
    }

    public function testDiagnoseFallsBackWhenContainerLookupFails(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => $id === 'event_dispatcher' ? throw new RuntimeException('container broken') : false);
        $controller = $this->controller($this->metrics);
        $controller->setContainer($container);

        $controller->diagnose(Request::create('/x'));

        $status = $this->rendered['diagnostic']['subscriber_status'];
        self::assertSame('container broken', $status['subscriber_error']);
        self::assertTrue($status['subscriber_registered']);
    }

    public function testDiagnoseWarnsAboutMissingRecentActivity(): void
    {
        $old   = (new RouteData())->setLastAccessedAt(new DateTimeImmutable('2026-10-20 08:00:00'));
        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn($old);
        $query->method('getSingleScalarResult')->willReturn('2');
        $qb = $this->createMock(QueryBuilder::class);
        foreach (['orderBy', 'setMaxResults', 'select', 'where', 'setParameter'] as $method) {
            $qb->method($method)->willReturnSelf();
        }
        $qb->method('getQuery')->willReturn($query);
        $this->repository->method('count')->willReturn(2);
        $this->repository->method('createQueryBuilder')->willReturn($qb);

        $this->controller($this->metrics, ['clock' => new MockClock('2026-10-30 12:00:00'), 'allowedEnvironments' => ['test']])->diagnose(Request::create('/x'));

        $data = $this->rendered['diagnostic']['data_status'];
        self::assertFalse($data['recent_activity']);
        self::assertSame(['test' => 2], $data['records_by_environment']);
        self::assertStringContainsString('No hay actividad reciente', implode("\n", $this->rendered['diagnostic']['warnings']));
    }

    public function testPagesFallBackToTheRouterOfTheContainer(): void
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $name): string => '/' . $name);
        $controller = $this->controller($this->metrics, ['pageFactory' => null]);
        $controller->setContainer($this->container(['router' => $router]));

        $controller->diagnose(Request::create('/x'));

        self::assertArrayHasKey('page', $this->rendered);
    }

    public function testDriverNameFromDriverAndWrappedDriver(): void
    {
        self::assertSame('pdo_named', $this->driverName($this->connection(new NamedTestDriver('pdo_named'))));
        self::assertSame('pdo_wrapped', $this->driverName($this->connection(new WrappingTestDriver(new NamedTestDriver('pdo_wrapped')))));
    }

    public function testDriverNameFallsBackToThePlatform(): void
    {
        self::assertSame('pdo_mysql', $this->driverName($this->connection(new NamedTestDriver(null), new MySQLPlatform())));
        self::assertSame('pdo_pgsql', $this->driverName($this->connection(new WrappingTestDriver(new NamedTestDriver(null)), new PostgreSQLPlatform())));
        self::assertSame('pdo_sqlite', $this->driverName($this->connection(new NamedTestDriver(null), new SQLitePlatform())));
        self::assertSame('pdo_sqlsrv', $this->driverName($this->connection(new NamedTestDriver(null), new SQLServerPlatform())));
        self::assertSame('unknown', $this->driverName($this->connection(new NamedTestDriver(null), $this->createMock(AbstractPlatform::class))));
    }

    public function testDriverNameIsUnknownWhenTheConnectionFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriver')->willThrowException(new RuntimeException('no driver'));

        self::assertSame('unknown', $this->driverName($connection));
    }

    public function testIsRouteIgnoredRejectsEmptyRouteNames(): void
    {
        $method     = new ReflectionMethod(PerformanceController::class, 'isRouteIgnored');
        $controller = $this->controller($this->metrics, ['ignoreRoutes' => ['app', 'web_*']]);

        self::assertTrue($method->invoke($controller, 'web_profiler'));
        self::assertFalse($method->invoke($controller, null));
        self::assertFalse($method->invoke($controller, ''));
        self::assertTrue($method->invoke($controller, 'app'));
    }

    private function fallbackController(): PerformanceController&MockObject
    {
        return $this->controller($this->metrics, [
            'checkTableStatus'    => true,
            'enableAccessRecords' => true,
            'recordRepository'    => $this->createMock(RouteDataRecordRepository::class),
        ]);
    }

    private function entityManager(PersistenceClassMetadata $metadata, AbstractSchemaManager $schema): void
    {
        $factory = $this->createMock(ClassMetadataFactory::class);
        $factory->method('getMetadataFor')->willReturn($metadata);
        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schema);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($factory);
        $em->method('getConnection')->willReturn($connection);
        $this->metrics->method('getEntityManager')->willReturn($em);
    }

    /**
     * @param array<string, mixed> $services id => service (an exception is thrown by get())
     */
    private function container(array $services): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => isset($services[$id]));
        $container->method('get')->willReturnCallback(static function (string $id) use ($services): mixed {
            $service = $services[$id] ?? null;
            if ($service instanceof RuntimeException) {
                throw $service;
            }

            return $service;
        });

        return $container;
    }

    private function connection(Driver $driver, ?AbstractPlatform $platform = null): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriver')->willReturn($driver);
        if ($platform instanceof AbstractPlatform) {
            $connection->method('getDatabasePlatform')->willReturn($platform);
        }

        return $connection;
    }

    private function driverName(Connection $connection): string
    {
        return (new ReflectionMethod(PerformanceController::class, 'getDriverName'))->invoke($this->controller($this->metrics), $connection);
    }
}
