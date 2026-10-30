<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use Throwable;

use function dirname;
use function getenv;
use function getmypid;
use function is_string;
use function preg_match;

use const CASE_LOWER;

/**
 * Real-database plumbing for the create-table command tests (REQ-TEST-003): a dedicated database per driver
 * (MySQL / PostgreSQL / SQLite in memory), a fresh attribute-mapped EntityManager per test, and mock proxies around the
 * real connection / schema manager / entity manager that delegate everything but can fail on chosen SQL or report
 * chosen table-existence answers. The dedicated database is dropped in {@see self::dropDatabase()}.
 *
 * @phpstan-require-extends \PHPUnit\Framework\TestCase
 */
trait CommandDatabaseTrait
{
    /** Prefix of the dedicated database (+ process id, so parallel suite runs never share it). */
    private const DEDICATED_DATABASE = 'perf_cmd_cov_';

    private ?Connection $realConnection = null;

    private ?EntityManager $realEntityManager = null;

    /** @var string|null SQL matching this regex throws in the connection proxy */
    private ?string $failPattern = null;

    /** @var string|null SQL matching this regex is not run (reported as 0 affected rows) by the connection proxy */
    private ?string $swallowPattern = null;

    /** @var (callable(array<string, mixed>): array<string, mixed>)|null rewrites fetchAssociative() rows of the proxy */
    private $fetchRewrite;

    /** @var array<class-string, \Doctrine\Persistence\Mapping\ClassMetadata<object>> getMetadataFor() answers of the factory proxy */
    private array $metadataOverrides = [];

    /** @var list<bool> answers of the schema-manager proxy tablesExist() before delegating */
    private array $tablesExistAnswers = [];

    private string $driver = 'sqlite';

    private static function dedicatedDatabase(): string
    {
        return self::DEDICATED_DATABASE . getmypid();
    }

    protected function connect(string $driver): Connection
    {
        $this->driver = $driver;
        if ($driver === 'sqlite') {
            $this->realConnection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

            return $this->realConnection;
        }

        $url = getenv($driver === 'mysql' ? 'DATABASE_URL' : 'DATABASE_URL_PG');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('No ' . $driver . ' database configured.');
        }
        $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($url);

        try {
            $server = DriverManager::getConnection(['dbname' => $driver === 'mysql' ? null : 'postgres'] + $params);
            $server->executeStatement('DROP DATABASE IF EXISTS ' . self::dedicatedDatabase());
            $server->executeStatement('CREATE DATABASE ' . self::dedicatedDatabase());
            $server->close();
        } catch (Throwable $e) {
            self::markTestSkipped($driver . ' unavailable: ' . $e->getMessage());
        }

        $this->realConnection = DriverManager::getConnection(['dbname' => self::dedicatedDatabase()] + $params);

        return $this->realConnection;
    }

    protected function dropDatabase(): void
    {
        $this->realEntityManager?->close();
        $this->realConnection?->close();
        $this->realEntityManager = null;
        $this->realConnection    = null;
        $this->failPattern       = null;
        $this->swallowPattern    = null;
        $this->fetchRewrite      = null;
        $this->metadataOverrides = [];
        if ($this->driver === 'sqlite') {
            return;
        }
        $url    = (string) getenv($this->driver === 'mysql' ? 'DATABASE_URL' : 'DATABASE_URL_PG');
        $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($url);
        $server = DriverManager::getConnection(['dbname' => $this->driver === 'mysql' ? null : 'postgres'] + $params);
        $server->executeStatement('DROP DATABASE IF EXISTS ' . self::dedicatedDatabase());
        $server->close();
        $this->driver = 'sqlite';
    }

    protected function realEntityManager(): EntityManager
    {
        if ($this->realEntityManager === null) {
            $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 3) . '/src/Entity'], true);
            $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
            if ($this->realConnection === null) {
                throw new RuntimeException('connect() first.');
            }
            $this->realEntityManager = new EntityManager($this->realConnection, $config);
        }

        return $this->realEntityManager;
    }

    /**
     * @return ClassMetadata<RouteData>
     */
    protected function routeDataMetadata(): ClassMetadata
    {
        return $this->realEntityManager()->getClassMetadata(RouteData::class);
    }

    /**
     * @return ClassMetadata<RouteDataRecord>
     */
    protected function recordMetadata(): ClassMetadata
    {
        return $this->realEntityManager()->getClassMetadata(RouteDataRecord::class);
    }

    /**
     * Runs SQL on the real connection (test setup).
     */
    protected function sql(string $sql): void
    {
        if ($this->realConnection === null) {
            throw new RuntimeException('connect() first.');
        }
        $this->realConnection->executeStatement($sql);
    }

    /**
     * @param (callable(array<string, mixed>): array<string, mixed>)|null $rewrite
     */
    protected function rewriteFetch(?callable $rewrite): void
    {
        $this->fetchRewrite = $rewrite;
    }

    /**
     * Makes the metadata factory proxy answer $metadata for $class (e.g. a bare Persistence ClassMetadata without the
     * ORM table-name API).
     *
     * @param class-string $class
     * @param \Doctrine\Persistence\Mapping\ClassMetadata<object> $metadata
     */
    protected function overrideMetadata(string $class, \Doctrine\Persistence\Mapping\ClassMetadata $metadata): void
    {
        $this->metadataOverrides[$class] = $metadata;
    }

    protected function swallow(?string $regex): void
    {
        $this->swallowPattern = $regex;
    }

    protected function failOn(?string $regex): void
    {
        $this->failPattern = $regex;
    }

    /**
     * @param list<bool> $answers
     */
    protected function tablesExistAnswers(array $answers): void
    {
        $this->tablesExistAnswers = $answers;
    }

    /**
     * Registry returning proxies of the real connection / entity manager.
     *
     * @param list<ClassMetadata<object>>|null $allMetadata getAllMetadata() answer (null: the real one)
     */
    protected function registry(?array $allMetadata = null): ManagerRegistry&MockObject
    {
        $connection = $this->connectionProxy();
        $em         = $this->entityManagerProxy($connection, $allMetadata);
        $registry   = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willReturn($connection);
        $registry->method('getManager')->willReturn($em);

        return $registry;
    }

    protected function connectionProxy(): Connection&MockObject
    {
        $real = $this->realConnection ?? throw new RuntimeException('connect() first.');
        $conn = $this->createMock(Connection::class);
        $fail = function (string $sql): void {
            if ($this->failPattern !== null && preg_match($this->failPattern, $sql) === 1) {
                throw new RuntimeException('Forced failure: ' . $sql);
            }
        };
        $conn->method('getDatabasePlatform')->willReturnCallback(static fn () => $real->getDatabasePlatform());
        $conn->method('executeStatement')->willReturnCallback(function (string $sql) use ($real, $fail): int|string {
            $fail($sql);
            if ($this->swallowPattern !== null && preg_match($this->swallowPattern, $sql) === 1) {
                return 0;
            }

            return $real->executeStatement($sql);
        });
        $conn->method('fetchAssociative')->willReturnCallback(function (string $sql) use ($real, $fail): array|false {
            $fail($sql);
            $row = $real->fetchAssociative($sql);

            return $row !== false && $this->fetchRewrite !== null ? ($this->fetchRewrite)($row) : $row;
        });
        $conn->method('fetchAllAssociative')->willReturnCallback(static function (string $sql) use ($real, $fail): array {
            $fail($sql);

            return $real->fetchAllAssociative($sql);
        });
        $conn->method('createSchemaManager')->willReturnCallback(fn (): AbstractSchemaManager => $this->schemaManagerProxy($real->createSchemaManager()));

        return $conn;
    }

    /**
     * @param AbstractSchemaManager<AbstractPlatform> $real
     *
     * @return AbstractSchemaManager<AbstractPlatform>&MockObject
     */
    private function schemaManagerProxy(AbstractSchemaManager $real): AbstractSchemaManager&MockObject
    {
        $sm = $this->createMock(AbstractSchemaManager::class);
        $sm->method('tablesExist')->willReturnCallback(function (array $names) use ($real): bool {
            if ($this->tablesExistAnswers !== []) {
                return array_shift($this->tablesExistAnswers);
            }

            return $real->tablesExist($names);
        });
        $sm->method('introspectTable')->willReturnCallback(static fn (string $name): Table => $real->introspectTable($name));
        $sm->method('dropTable')->willReturnCallback(static function (string $name) use ($real): void {
            $real->dropTable($name);
        });
        $sm->method('createSchemaConfig')->willReturnCallback(static fn (): SchemaConfig => $real->createSchemaConfig());
        $sm->method('introspectSchema')->willReturnCallback(static fn (): Schema => $real->introspectSchema());

        return $sm;
    }

    /**
     * @param list<ClassMetadata<object>>|null $allMetadata
     */
    private function entityManagerProxy(Connection $connection, ?array $allMetadata): EntityManagerInterface&MockObject
    {
        $real    = $this->realEntityManager();
        $factory = $this->createMock(ClassMetadataFactory::class);
        $factory->method('getMetadataFor')->willReturnCallback(fn (string $class) => $this->metadataOverrides[$class] ?? $real->getMetadataFactory()->getMetadataFor($class));
        $factory->method('getAllMetadata')->willReturnCallback(static fn (): array => $allMetadata ?? $real->getMetadataFactory()->getAllMetadata());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->method('getMetadataFactory')->willReturn($factory);
        $em->method('getConfiguration')->willReturnCallback(static fn () => $real->getConfiguration());
        $em->method('getEventManager')->willReturnCallback(static fn () => $real->getEventManager());
        $em->method('getClassMetadata')->willReturnCallback(static fn (string $class) => $real->getClassMetadata($class));

        return $em;
    }
}
