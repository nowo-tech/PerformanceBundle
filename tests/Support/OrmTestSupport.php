<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Support;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function dirname;
use function sys_get_temp_dir;

use const CASE_LOWER;
use const PHP_VERSION_ID;

/**
 * Real ORM entity managers for the bundle's entities (attribute mapping, underscore naming, service repositories
 * resolved through a registry stub), on SQLite in memory or on any given DBAL connection.
 */
final class OrmTestSupport
{
    /**
     * An ORM configuration whose repositories resolve their manager through $registry (DoctrineBundle-like).
     */
    public static function configuration(ManagerRegistry $registry): Configuration
    {
        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 2) . '/src/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        } else {
            $config->setProxyDir(sys_get_temp_dir() . '/nowo-performance-proxies');
            $config->setProxyNamespace('NowoPerformanceTestProxies');
            $config->setAutoGenerateProxyClasses(true);
        }

        $config->setRepositoryFactory(new class($registry) implements RepositoryFactory {
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

        return $config;
    }

    /**
     * An entity manager on $connection (SQLite in memory when null); its repositories resolve it through a registry
     * stub. With $createSchema, the routes_data / routes_data_records tables are created.
     */
    public static function entityManager(TestCase $test, ?Connection $connection = null, bool $createSchema = true): EntityManager
    {
        $registry = (new ReflectionMethod($test, 'createStub'))->invoke($test, ManagerRegistry::class);
        $config   = self::configuration($registry);
        $connection ??= DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $em = new EntityManager($connection, $config, new EventManager());
        if ($registry instanceof Stub) {
            $registry->method('getManagerForClass')->willReturn($em);
            $registry->method('getManager')->willReturn($em);
        }
        if ($createSchema) {
            (new SchemaTool($em))->createSchema([
                $em->getClassMetadata(RouteData::class),
                $em->getClassMetadata(RouteDataRecord::class),
            ]);
        }

        return $em;
    }
}
