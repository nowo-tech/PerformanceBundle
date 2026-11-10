<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use ArrayObject;
use DateTimeImmutable;
use Doctrine\Common\EventManager;
use Doctrine\Common\EventManagerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Error;
use Exception;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\MetricsEntityManagerFactory;
use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

use const CASE_LOWER;
use const PHP_VERSION_ID;

/**
 * Sync recording must never flush, nor reset, the host's entity manager (DDD audit 2026-11-10).
 *
 * The "host" entity manager is a real ORM entity manager on SQLite; the bundle records through its own private
 * manager on the same connection.
 */
final class PerformanceMetricsServiceHostUnitOfWorkTest extends TestCase
{
    private Connection $connection;
    private EntityManager $hostEm;
    private ManagerRegistry&MockObject $registry;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/../../../src/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        } else {
            $config->setProxyDir(sys_get_temp_dir() . '/nowo-performance-proxies');
            $config->setProxyNamespace('NowoPerformanceTestProxies');
            $config->setAutoGenerateProxyClasses(true);
        }

        // Service repositories resolve their manager through the registry, like DoctrineBundle's ContainerRepositoryFactory.
        $repositoryRegistry = $this->createStub(ManagerRegistry::class);
        $config->setRepositoryFactory(new class($repositoryRegistry) implements RepositoryFactory {
            /** @var array<class-string, EntityRepository<object>> */
            private array $repositories = [];

            public function __construct(private readonly ManagerRegistry $registry)
            {
            }

            public function getRepository(EntityManagerInterface $entityManager, string $entityName): EntityRepository
            {
                return $this->repositories[$entityName] ??= $entityName === RouteData::class
                    ? new RouteDataRepository($this->registry)
                    : new RouteDataRecordRepository($this->registry);
            }
        });

        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->hostEm     = new EntityManager($this->connection, $config, new EventManager());
        $repositoryRegistry->method('getManagerForClass')->willReturn($this->hostEm);
        (new SchemaTool($this->hostEm))->createSchema([
            $this->hostEm->getClassMetadata(RouteData::class),
            $this->hostEm->getClassMetadata(RouteDataRecord::class),
        ]);

        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->registry->method('getManager')->with('default')->willReturn($this->hostEm);
        $this->registry->expects(self::never())->method('resetManager');
    }

    public function testRecordingDoesNotPersistAnEntityTheHostLeftUnflushed(): void
    {
        $existing = $this->insertRoute('host_existing');
        $existing->setReviewed(true);

        $pending = (new RouteData())->setName('host_pending')->setEnv('prod');
        $this->hostEm->persist($pending);

        $result = $this->service()->recordMetrics('app_home', 'prod', 0.1, 1, 0.01, null, 1024, 'GET', 200, [], 'req-1');

        self::assertTrue($result['is_new']);
        self::assertSame(1, $this->countRoutes('app_home'));
        self::assertSame(1, $this->countRecords());
        self::assertSame(0, $this->countRoutes('host_pending'), 'The pending host insert must not be flushed by the bundle.');
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT reviewed FROM routes_data WHERE name = ?', ['host_existing']), 'The pending host update must not be flushed by the bundle.');

        // The host's unit of work is untouched: its changes are still pending, for the host to flush or drop.
        self::assertTrue($this->hostEm->getUnitOfWork()->isScheduledForInsert($pending));
        self::assertTrue($this->hostEm->contains($existing));
    }

    public function testRecordingUpdatesARouteManagedByTheHostWithoutFlushingIt(): void
    {
        $route = $this->insertRoute('shared_route');
        $route->setReviewed(true);

        $result = $this->service()->recordMetrics('shared_route', 'prod', 0.1, 1, null, null, null, 'GET', 200, [], 'req-2');

        self::assertTrue($result['was_updated']);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT reviewed FROM routes_data WHERE name = ?', ['shared_route']));
        self::assertNotNull($this->connection->fetchOne('SELECT last_accessed_at FROM routes_data WHERE name = ?', ['shared_route']));
    }

    public function testClosedHostEntityManagerIsNeitherResetNorNeeded(): void
    {
        $this->hostEm->close();

        $this->service()->recordMetrics('after_host_failure', 'prod', 0.1, 1, null, null, null, 'GET', 500, [], 'req-3');

        self::assertSame(1, $this->countRoutes('after_host_failure'));
        self::assertFalse($this->hostEm->isOpen(), 'The bundle must not reopen / reset the host entity manager.');
    }

    public function testPrivateManagerIsClearedAfterEachRecording(): void
    {
        $service = $this->service();
        $service->recordMetrics('worker_route', 'prod', 0.1, 1, null, null, null, 'GET', 200, [], 'req-4');
        $first = $this->writeEntityManager($service);
        self::assertSame(0, $first->getUnitOfWork()->size());

        // Another worker disables access records: the next recording reads the fresh row, not a stale entity.
        $this->connection->executeStatement('UPDATE routes_data SET save_access_records = 0 WHERE name = ?', ['worker_route']);
        $result = $service->recordMetrics('worker_route', 'prod', 0.2, 2, null, null, null, 'GET', 200, [], 'req-5');

        self::assertTrue($result['was_updated']);
        self::assertSame($first, $this->writeEntityManager($service));
        self::assertSame(0, $first->getUnitOfWork()->size());
        self::assertSame(1, $this->countRecords());
    }

    public function testDuplicateRequestIdDoesNotCreateASecondAccessRecord(): void
    {
        $service = $this->service();
        $service->recordMetrics('dup', 'prod', 0.1, 1, null, null, null, 'GET', 200, [], 'same-id');
        $service->recordMetrics('dup', 'prod', 0.2, 1, null, null, null, 'GET', 200, [], 'same-id');

        self::assertSame(1, $this->countRecords());
    }

    public function testCacheIsInvalidatedAndAfterEventDispatchedOnSuccess(): void
    {
        $cache = $this->createMock(PerformanceCacheService::class);
        $cache->expects(self::once())->method('invalidateStatistics')->with('prod');

        $events     = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(AfterMetricsRecordedEvent::class, static function (AfterMetricsRecordedEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $service = $this->service();
        $service->setCacheService($cache);
        $service->setEventDispatcher($dispatcher);
        $service->recordMetrics('evented', 'prod', 0.1, 1);

        self::assertCount(1, $events);
        self::assertSame('evented', $events[0]->getRouteData()->getName());
        self::assertTrue($events[0]->isNew());
    }

    public function testAfterEventListenerUpdatesTheRowByIdWithoutCommittingPendingHostChanges(): void
    {
        // The request left an invalid edit pending in the host manager (e.g. a form that failed validation).
        $existing = $this->insertRoute('host_invalid_edit');
        $existing->setReviewed(true);

        $hostEm     = $this->hostEm;
        $connection = $this->connection;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(AfterMetricsRecordedEvent::class, static function (AfterMetricsRecordedEvent $event) use ($hostEm, $connection): void {
            self::assertNotNull($event->getRouteDataId());
            self::assertSame('by_id', $event->getRouteName());
            self::assertSame('prod', $event->getEnv());
            self::assertFalse($hostEm->contains($event->getRouteData()), 'The event entity belongs to the private manager.');

            // Documented pattern: an UPDATE by id, never a flush of the application's entity manager.
            $connection->update('routes_data', ['reviewed' => 1], ['id' => $event->getRouteDataId()]);
        });

        $service = $this->service();
        $service->setEventDispatcher($dispatcher);
        $service->recordMetrics('by_id', 'prod', 0.1, 1);

        self::assertSame(1, $this->countRoutes('by_id'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT reviewed FROM routes_data WHERE name = ?', ['by_id']));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT reviewed FROM routes_data WHERE name = ?', ['host_invalid_edit']), 'The pending host change must stay uncommitted.');

        // Still pending in the host's unit of work, for the host to flush or drop.
        $unitOfWork = $this->hostEm->getUnitOfWork();
        $unitOfWork->computeChangeSets();
        self::assertArrayHasKey('reviewed', $unitOfWork->getEntityChangeSet($existing));
    }

    public function testFlushErrorDropsThePrivateManagerRestoresErrorReportingAndNextRecordingWorks(): void
    {
        $switch = new ArrayObject(['fail' => true]);
        $this->hostEm->getEventManager()->addEventListener(Events::onFlush, new class($switch) {
            /** @param ArrayObject<string, bool> $switch */
            public function __construct(private readonly ArrayObject $switch)
            {
            }

            public function onFlush(): void
            {
                if ($this->switch['fail'] === true) {
                    throw new Error('driver failure');
                }
            }
        });

        $service              = $this->service();
        $errorReportingBefore = error_reporting();

        try {
            $service->recordMetrics('broken', 'prod', 0.1, 1, null, null, null, 'GET', 200, [], 'req-6');
            self::fail('Error was expected');
        } catch (Error $e) {
            self::assertSame('driver failure', $e->getMessage());
        }

        self::assertSame($errorReportingBefore, error_reporting());
        self::assertNull((new ReflectionProperty(PerformanceMetricsService::class, 'writeEntityManager'))->getValue($service));
        self::assertTrue($this->hostEm->isOpen());

        $switch['fail'] = false;
        $service->recordMetrics('broken', 'prod', 0.1, 1, null, null, null, 'GET', 200, [], 'req-7');
        self::assertSame(1, $this->countRoutes('broken'));
    }

    public function testExceptionDuringFlushIsRethrownAndAClosedPrivateManagerIsReplaced(): void
    {
        /** @var ArrayObject<int, EntityManagerInterface> $created */
        $created = new ArrayObject();
        $factory = new class($created) extends MetricsEntityManagerFactory {
            /** @param ArrayObject<int, EntityManagerInterface> $created */
            public function __construct(private readonly ArrayObject $created)
            {
            }

            public function create(EntityManagerInterface $source): EntityManagerInterface
            {
                $em              = parent::create($source);
                $this->created[] = $em;

                return $em;
            }
        };

        $service = $this->service($factory);
        $service->recordMetrics('first', 'prod', 0.1, 1);
        $created[0]->close();

        $service->recordMetrics('second', 'prod', 0.1, 1);
        self::assertCount(2, $created);
        self::assertSame(1, $this->countRoutes('second'));

        $this->connection->executeStatement('DROP TABLE routes_data_records');

        try {
            $service->recordMetrics('third', 'prod', 0.1, 1, null, null, null, 'GET', 200, [], 'req-8');
            self::fail('Exception was expected');
        } catch (Exception $e) {
            self::assertNotSame('', $e->getMessage());
        }

        self::assertNull((new ReflectionProperty(PerformanceMetricsService::class, 'writeEntityManager'))->getValue($service));
    }

    public function testFactoryCreatesASeparateManagerOnTheSameConnection(): void
    {
        $em = (new MetricsEntityManagerFactory())->create($this->hostEm);

        self::assertNotSame($this->hostEm, $em);
        self::assertSame($this->connection, $em->getConnection());
        self::assertSame($this->hostEm->getEventManager(), $em->getEventManager());
    }

    public function testFactoryUsesAFreshEventManagerWhenTheSourceOneIsNotAnEventManager(): void
    {
        $source = $this->createStub(EntityManagerInterface::class);
        $source->method('getConnection')->willReturn($this->connection);
        $source->method('getConfiguration')->willReturn($this->hostEm->getConfiguration());
        $source->method('getEventManager')->willReturn($this->createStub(EventManagerInterface::class));

        $em = (new MetricsEntityManagerFactory())->create($source);

        self::assertSame($this->connection, $em->getConnection());
        self::assertInstanceOf(EventManager::class, $em->getEventManager());
    }

    private function service(?MetricsEntityManagerFactory $factory = null): PerformanceMetricsService
    {
        return new PerformanceMetricsService($this->registry, 'default', false, true, false, null, new NativeClock(), $factory);
    }

    private function insertRoute(string $name): RouteData
    {
        $route = (new RouteData())->setName($name)->setEnv('prod')->setLastAccessedAt(new DateTimeImmutable('-1 day'));
        $this->hostEm->persist($route);
        $this->hostEm->flush();

        return $route;
    }

    private function countRoutes(string $name): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM routes_data WHERE name = ?', [$name]);
    }

    private function countRecords(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM routes_data_records');
    }

    private function writeEntityManager(PerformanceMetricsService $service): EntityManagerInterface
    {
        $em = (new ReflectionProperty(PerformanceMetricsService::class, 'writeEntityManager'))->getValue($service);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }
}
