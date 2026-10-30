<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata as PersistenceClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Nowo\PerformanceBundle\Command\CreateRecordsTableCommand;
use Nowo\PerformanceBundle\Command\CreateTableCommand;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * nowo:performance:create-records-table against real MySQL, PostgreSQL and SQLite databases (dedicated database,
 * dropped after each test): create, --force, --update (AUTO_INCREMENT repair, add / alter / drop columns, missing
 * indexes and unique constraints, column defaults) and the failure path of every DDL statement.
 */
final class CreateRecordsTableCommandDatabaseTest extends TestCase
{
    use CommandDatabaseTrait;

    protected function tearDown(): void
    {
        $this->dropDatabase();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function drivers(): iterable
    {
        yield 'mysql' => ['mysql'];
        yield 'postgresql' => ['postgresql'];
        yield 'sqlite' => ['sqlite'];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runCommand(ManagerRegistry $registry, array $input = []): CommandTester
    {
        $tester = new CommandTester(new CreateRecordsTableCommand($registry, 'default', 'routes_data'));
        $tester->execute($input);

        return $tester;
    }

    /** Main table (FK target) then the records table. */
    private function createTables(): void
    {
        $main = new CommandTester(new CreateTableCommand($this->registry(), 'default', 'routes_data', false));
        $main->execute([]);
        self::assertSame(Command::SUCCESS, $main->getStatusCode(), $main->getDisplay());
        $tester = $this->runCommand($this->registry());
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
    }

    #[DataProvider('drivers')]
    public function testCreateExistsForceAndUpdate(string $driver): void
    {
        $this->connect($driver);
        $this->createTables();

        $tester = $this->runCommand($this->registry());
        self::assertStringContainsString('already exists', $tester->getDisplay());

        $tester = $this->runCommand($this->registry(), ['--force' => true]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Table dropped.', $tester->getDisplay());

        $tester = $this->runCommand($this->registry(), ['--update' => true]);
        self::assertStringContainsString('Updating Table Schema', $tester->getDisplay());
    }

    public function testMysqlUpdateRepairsAutoIncrementColumnsDefaultsAndIndexes(): void
    {
        $this->connect('mysql');
        $this->createTables();
        $this->sql('ALTER TABLE routes_data_records MODIFY id INT NOT NULL');
        $this->sql('ALTER TABLE routes_data_records DROP COLUMN referer');
        $this->sql('ALTER TABLE routes_data_records DROP COLUMN accessed_at');
        $this->sql('ALTER TABLE routes_data_records DROP COLUMN memory_usage');
        $this->sql('ALTER TABLE routes_data_records ADD COLUMN legacy_col INT NULL');
        $this->sql('ALTER TABLE routes_data_records MODIFY status_code INT NULL DEFAULT 3');
        $this->sql("ALTER TABLE routes_data_records MODIFY user_id VARCHAR(64) NULL DEFAULT 'a'");
        $this->sql("ALTER TABLE routes_data_records MODIFY user_identifier VARCHAR(255) NULL DEFAULT '0'");
        $this->sql('ALTER TABLE routes_data_records MODIFY request_id VARCHAR(32) NULL');
        $this->sql('ALTER TABLE routes_data_records MODIFY total_queries INT NOT NULL');
        $this->sql('ALTER TABLE routes_data_records MODIFY response_time VARCHAR(20) NULL');
        $this->sql('DROP INDEX idx_record_status_code ON routes_data_records');
        $this->sql('DROP INDEX uniq_record_request_id ON routes_data_records');

        $meta                                           = $this->recordMetadata();
        $meta->fieldMappings['statusCode']->options     = ['default' => 4];
        $meta->fieldMappings['userId']->options         = ['default' => 'b'];
        $meta->fieldMappings['userIdentifier']->options = ['default' => true];
        $meta->fieldMappings['referer']->options        = ['default' => 'none'];
        $meta->fieldMappings['accessedAt']->options     = ['default' => '2026-01-01 00:00:00'];
        $meta->fieldMappings['memoryUsage']->options    = ['default' => false];
        $meta->fieldMappings['routePath']->length       = null;
        $meta->fieldMappings['routePath']->options      = ['length' => 2048];
        $meta->table['indexes']['idx_missing_column']   = ['columns' => ['no_such_column']];
        $meta->table['indexes']['idx_empty']            = ['columns' => []];
        $meta->table['uniqueConstraints']['uniq_empty'] = ['columns' => []];
        // INFORMATION_SCHEMA reports a type outside the integer list: the repair falls back to INT
        $this->rewriteFetch(static fn (array $row): array => ['COLUMN_TYPE' => 'integer'] + $row);

        $tester  = $this->runCommand($this->registry(), ['--update' => true, '--drop-obsolete' => true]);
        $display = $tester->getDisplay();
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString('now has AUTO_INCREMENT', $display);
        self::assertStringContainsString('Dropped column legacy_col', $display);
        // the association join column is not an entity field, yet never obsolete
        self::assertStringNotContainsString('route_data_id', $display);
        self::assertStringContainsString('Added column referer', $display);
        self::assertStringContainsString('Updated column status_code', $display);
        self::assertStringContainsString('Created unique index uniq_record_request_id', $display);
        self::assertStringContainsString('Created index idx_record_status_code', $display);

        $tester = $this->runCommand($this->registry(), ['--update' => true]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
    }

    public function testMysqlUpToDateTableAndUnknownColumnType(): void
    {
        $this->connect('mysql');
        $this->createTables();
        // a string field without length: MySQL refuses VARCHAR without length, the fallback VARCHAR(255) still matches
        $this->recordMetadata()->fieldMappings['userIdentifier']->length = null;
        $tester                                                          = $this->runCommand($this->registry(), ['--update' => true]);
        self::assertStringContainsString('All columns are up to date', $tester->getDisplay());

        $this->sql('ALTER TABLE routes_data_records DROP COLUMN user_id');
        $this->recordMetadata()->fieldMappings['userId']->type = 'no_such_dbal_type';
        $tester                                                = $this->runCommand($this->registry(), ['--update' => true]);
        self::assertStringContainsString('Added column user_id', $tester->getDisplay());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function failures(): iterable
    {
        // the check fails: the column loop repairs id with a single AUTO_INCREMENT instead
        yield 'auto increment check' => ['/INFORMATION_SCHEMA\.COLUMNS/', 'Updated column id'];
        yield 'drop column' => ['/DROP COLUMN `legacy_col`/', 'Failed to drop column legacy_col'];
        yield 'add column' => ['/ADD COLUMN `referer`/', 'Failed to add column'];
        yield 'modify column' => ['/MODIFY COLUMN `status_code`/', 'Failed to update column'];
        yield 'index' => ['/CREATE INDEX `idx_record_status_code`/', 'Failed to create index'];
    }

    #[DataProvider('failures')]
    public function testMysqlFailures(string $failPattern, string $expected): void
    {
        $this->connect('mysql');
        $this->createTables();
        $this->sql('ALTER TABLE routes_data_records MODIFY id INT NOT NULL');
        $this->sql('ALTER TABLE routes_data_records DROP COLUMN referer');
        $this->sql('ALTER TABLE routes_data_records MODIFY status_code VARCHAR(10) NULL');
        $this->sql('ALTER TABLE routes_data_records ADD COLUMN legacy_col INT NULL');
        $this->sql('DROP INDEX idx_record_status_code ON routes_data_records');
        $this->failOn($failPattern);

        $tester = $this->runCommand($this->registry(), ['--update' => true, '--drop-obsolete' => true]);
        self::assertStringContainsString($expected, $tester->getDisplay());
    }

    public function testMysqlIdRepairByTheColumnLoopDeclaresAutoIncrementOnce(): void
    {
        $this->connect('mysql');
        $this->createTables();
        $this->sql('ALTER TABLE routes_data_records MODIFY id INT NOT NULL');
        $this->failOn('/INFORMATION_SCHEMA\.COLUMNS/');

        $tester = $this->runCommand($this->registry(), ['--update' => true]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Updated column id', $tester->getDisplay());
        $this->failOn(null);
        self::assertSame(1, (int) $this->realConnectionOrFail()->fetchOne("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes_data_records' AND COLUMN_NAME = 'id' AND EXTRA LIKE '%auto_increment%'"));
    }

    private function realConnectionOrFail(): Connection
    {
        return $this->realConnection ?? throw new RuntimeException('not connected');
    }

    public function testPostgresqlDropsObsoleteColumns(): void
    {
        $this->connect('postgresql');
        $this->createTables();
        $this->sql('ALTER TABLE routes_data_records ADD COLUMN legacy_col INT NULL');
        $tester = $this->runCommand($this->registry(), ['--update' => true, '--drop-obsolete' => true]);
        self::assertStringContainsString('Dropped column legacy_col', $tester->getDisplay());
        self::assertStringNotContainsString('route_data_id', $tester->getDisplay());
    }

    public function testMysqlCreateAddsAutoIncrementWhenTheGeneratorIsNone(): void
    {
        $this->connect('mysql');
        $main = new CommandTester(new CreateTableCommand($this->registry(), 'default', 'routes_data', false));
        $main->execute([]);
        $this->recordMetadata()->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $this->recordMetadata()->fieldMappings['accessedAt']->options = ['default' => '0000-00-00 00:00:00'];

        $tester = $this->runCommand($this->registry());
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('(id INT NOT NULL AUTO_INCREMENT,', $tester->getDisplay());
        // only the primary key gets AUTO_INCREMENT, never the route_data_id join column
        self::assertStringContainsString('route_data_id INT NOT NULL,', $tester->getDisplay());
        self::assertStringNotContainsString("'0000-00-00", $tester->getDisplay());
    }

    public function testMetadataWithoutTheOrmTableNameApiUsesTheDerivedTableName(): void
    {
        $this->connect('sqlite');
        $this->createTables();
        $this->sql('DROP INDEX idx_record_status_code');
        $this->overrideMetadata(RouteDataRecord::class, $this->createMock(PersistenceClassMetadata::class));

        $tester = $this->runCommand($this->registry(), ['--update' => true]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Table name: routes_data_records', $tester->getDisplay());
        self::assertStringContainsString('Created index idx_record_status_code', $tester->getDisplay());
    }

    public function testRegistryWithoutConnectionOrEntityManagerFails(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willReturn(new stdClass());
        $tester = $this->runCommand($registry);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('did not return a DBAL Connection', $tester->getDisplay());

        $this->connect('sqlite');
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willReturn($this->connectionProxy());
        $registry->method('getManager')->willReturn($this->createMock(ObjectManager::class));
        $tester = $this->runCommand($registry);
        self::assertStringContainsString('did not return an EntityManager', $tester->getDisplay());
    }

    public function testMissingMetadataEmptySqlAndVanishingTable(): void
    {
        $this->connect('sqlite');
        $tester = $this->runCommand($this->registry([]));
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('RouteDataRecord entity metadata not found', $tester->getDisplay());

        $this->realEntityManager()->getConfiguration()->setSchemaIgnoreClasses([RouteDataRecord::class]);
        $tester = $this->runCommand($this->registry());
        self::assertStringContainsString('No SQL statements to execute', $tester->getDisplay());

        $this->tablesExistAnswers([true, false]);
        $tester = $this->runCommand($this->registry(), ['--update' => true]);
        self::assertStringContainsString('does not exist. Use the create command', $tester->getDisplay());
    }
}
