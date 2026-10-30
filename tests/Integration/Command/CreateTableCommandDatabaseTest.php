<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata as PersistenceClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Nowo\PerformanceBundle\Command\CreateTableCommand;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * nowo:performance:create-table against real MySQL, PostgreSQL and SQLite databases (dedicated database, dropped after
 * each test): create, --force, --update (add / alter / drop obsolete columns, AUTO_INCREMENT repair with foreign keys,
 * missing indexes) for the main and the access-records tables, plus the failure paths of every DDL statement.
 */
final class CreateTableCommandDatabaseTest extends TestCase
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

    private function runCommand(ManagerRegistry $registry, array $input = [], bool $accessRecords = true): CommandTester
    {
        $tester = new CommandTester(new CreateTableCommand($registry, 'default', 'routes_data', $accessRecords));
        $tester->execute($input);

        return $tester;
    }

    private function createBoth(): void
    {
        $tester = $this->runCommand($this->registry());
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
    }

    #[DataProvider('drivers')]
    public function testCreateThenExistsThenForce(string $driver): void
    {
        $this->connect($driver);
        $tester = $this->runCommand($this->registry());
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Access records table "routes_data_records" created successfully', $tester->getDisplay());

        $tester = $this->runCommand($this->registry());
        self::assertStringContainsString('already exists', $tester->getDisplay());

        $this->sql('DROP TABLE routes_data_records');
        $tester = $this->runCommand($this->registry(), ['--force' => true], false);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Table dropped.', $tester->getDisplay());
    }

    public function testPostgresqlUpdateUpToDateRecordsMissingAndRecordsDropObsolete(): void
    {
        $this->connect('postgresql');
        $this->runCommandForMain();

        // the identity id matches the entity on PostgreSQL: nothing to change, the records table is created
        $tester = $this->runCommand($this->registry(), ['--update' => true]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('All columns are up to date', $tester->getDisplay());
        self::assertStringContainsString('"routes_data_records" does not exist', $tester->getDisplay());

        $tester = $this->runCommand($this->registry(), ['--update' => true]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringNotContainsString('MODIFY', $tester->getDisplay());

        // --drop-obsolete drops real leftovers but never the association join column route_data_id
        $this->sql('ALTER TABLE routes_data_records ADD COLUMN legacy_col INT NULL');
        $this->sql('DROP INDEX idx_record_status_code');
        $tester = $this->runCommand($this->registry(), ['--update' => true, '--drop-obsolete' => true]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Dropped column legacy_col', $tester->getDisplay());
        self::assertStringNotContainsString('route_data_id', $tester->getDisplay());
        self::assertStringContainsString('Created index idx_record_status_code on records table', $tester->getDisplay());
    }

    private function runCommandForMain(): void
    {
        $tester = $this->runCommand($this->registry(), [], false);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
    }

    public function testMysqlUpdateRepairsColumnsIndexesAndAutoIncrement(): void
    {
        $this->connect('mysql');
        $this->createBoth();
        $fk = (string) $this->realConnectionOrFail()->fetchOne("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes_data_records' AND REFERENCED_TABLE_NAME = 'routes_data'");
        $this->sql('ALTER TABLE routes_data_records DROP FOREIGN KEY ' . $fk);
        $this->sql('ALTER TABLE routes_data MODIFY id INT NOT NULL');
        $this->sql('ALTER TABLE routes_data_records ADD CONSTRAINT ' . $fk . ' FOREIGN KEY (route_data_id) REFERENCES routes_data (id) ON DELETE CASCADE');
        $this->sql('ALTER TABLE routes_data DROP COLUMN reviewed_by');
        $this->sql('ALTER TABLE routes_data ADD COLUMN legacy_col INT NULL');
        $this->sql('ALTER TABLE routes_data MODIFY name VARCHAR(100) NOT NULL DEFAULT \'x\'');
        $this->sql('ALTER TABLE routes_data MODIFY save_access_records TINYINT(1) NOT NULL DEFAULT 0');
        $this->sql('ALTER TABLE routes_data MODIFY http_method VARCHAR(20) NULL');
        $this->sql('DROP INDEX idx_route_env ON routes_data');
        $this->sql('ALTER TABLE routes_data_records DROP COLUMN referer');
        $this->sql('ALTER TABLE routes_data_records MODIFY status_code VARCHAR(10) NULL');
        $this->sql('ALTER TABLE routes_data_records MODIFY id INT NOT NULL');
        $this->sql('DROP INDEX idx_record_status_code ON routes_data_records');

        $tester  = $this->runCommand($this->registry(), ['--update' => true, '--drop-obsolete' => true], false);
        $display = $tester->getDisplay();
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString('now has AUTO_INCREMENT', $display);
        self::assertStringContainsString('Temporarily dropped foreign key', $display);
        self::assertStringContainsString('Restored foreign key', $display);
        self::assertStringContainsString('Dropped column', $display);
        self::assertStringContainsString('Added column', $display);
        self::assertStringContainsString('Updated column', $display);
        self::assertStringContainsString('Created index', $display);
        self::assertStringContainsString('updated successfully', $display);

        $tester  = $this->runCommand($this->registry(), ['--update' => true]);
        $display = $tester->getDisplay();
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString('Added column referer', $display);
        self::assertStringContainsString('Updated column status_code', $display);
        // records id: a single AUTO_INCREMENT in the MODIFY statement (the SQL type no longer declares it too)
        self::assertStringContainsString('Updated column id', $display);
        self::assertSame('auto_increment', strtolower((string) $this->realConnectionOrFail()->fetchOne("SELECT EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes_data_records' AND COLUMN_NAME = 'id'")));
        self::assertStringContainsString('Created index idx_record_status_code on records table', $display);
    }

    public function testMysqlIdUpdateRunsAutoIncrementRepairInColumnLoop(): void
    {
        $this->connect('mysql');
        $this->createBoth();
        // the INFORMATION_SCHEMA check fails, so the id column is repaired by the column-update loop
        $this->failOn('/INFORMATION_SCHEMA\.COLUMNS/');
        $fk = (string) $this->realConnectionOrFail()->fetchOne("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes_data_records' AND REFERENCED_TABLE_NAME = 'routes_data'");
        $this->sql('ALTER TABLE routes_data_records DROP FOREIGN KEY ' . $fk);
        $this->sql('ALTER TABLE routes_data MODIFY id INT NOT NULL');
        $this->sql('ALTER TABLE routes_data_records ADD CONSTRAINT ' . $fk . ' FOREIGN KEY (route_data_id) REFERENCES routes_data (id) ON DELETE CASCADE');

        $tester = $this->runCommand($this->registry(), ['--update' => true], false);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Could not check/fix AUTO_INCREMENT', $tester->getDisplay());
        self::assertStringContainsString('Updated column id', $tester->getDisplay());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function mysqlFailures(): iterable
    {
        yield 'drop column' => ['/DROP COLUMN `legacy_col`/', 'Failed to drop column'];
        yield 'add column' => ['/ADD COLUMN `reviewed_by`/', 'Failed to add column'];
        yield 'modify column' => ['/MODIFY COLUMN `name`/', 'Failed to update column'];
        yield 'drop foreign key' => ['/DROP FOREIGN KEY/', 'Could not drop foreign key'];
        yield 'restore foreign key' => ['/ADD CONSTRAINT/', 'Failed to restore foreign key'];
        yield 'auto increment' => ['/MODIFY COLUMN `id` INT NOT NULL AUTO_INCREMENT/', 'Failed to update column id'];
        yield 'index' => ['/CREATE INDEX/', 'Failed to create index'];
    }

    #[DataProvider('mysqlFailures')]
    public function testMysqlMainTableFailures(string $failPattern, string $expected): void
    {
        $this->connect('mysql');
        $this->createBoth();
        $fk = (string) $this->realConnectionOrFail()->fetchOne("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes_data_records' AND REFERENCED_TABLE_NAME = 'routes_data'");
        $this->sql('ALTER TABLE routes_data_records DROP FOREIGN KEY ' . $fk);
        $this->sql('ALTER TABLE routes_data MODIFY id INT NOT NULL');
        $this->sql('ALTER TABLE routes_data_records ADD CONSTRAINT ' . $fk . ' FOREIGN KEY (route_data_id) REFERENCES routes_data (id) ON DELETE CASCADE');
        $this->sql('ALTER TABLE routes_data DROP COLUMN reviewed_by');
        $this->sql('ALTER TABLE routes_data ADD COLUMN legacy_col INT NULL');
        $this->sql('ALTER TABLE routes_data MODIFY name VARCHAR(100) NOT NULL');
        $this->sql('DROP INDEX idx_route_env ON routes_data');
        // id is repaired by the column loop (the INFORMATION_SCHEMA check is skipped)
        $this->failOn('/INFORMATION_SCHEMA\.COLUMNS|' . substr($failPattern, 1, -1) . '/');

        $tester = $this->runCommand($this->registry(), ['--update' => true, '--drop-obsolete' => true], false);
        self::assertStringContainsString($expected, $tester->getDisplay());
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function recordsFailures(): iterable
    {
        yield 'drop column' => ['/DROP COLUMN `legacy_col`/', 'Failed to drop column legacy_col', true];
        yield 'add column' => ['/ADD COLUMN `referer`/', 'Failed to add column', false];
        yield 'modify column' => ['/MODIFY COLUMN `status_code`/', 'Failed to update column', false];
        yield 'index' => ['/CREATE INDEX `idx_record_status_code`/', 'Failed to create index', false];
    }

    #[DataProvider('recordsFailures')]
    public function testMysqlRecordsTableFailures(string $failPattern, string $expected, bool $dropObsolete): void
    {
        $this->connect('mysql');
        $this->createBoth();
        $this->sql('ALTER TABLE routes_data_records DROP COLUMN referer');
        $this->sql('ALTER TABLE routes_data_records ADD COLUMN legacy_col INT NULL');
        $this->sql('ALTER TABLE routes_data_records MODIFY status_code VARCHAR(10) NULL');
        $this->sql('DROP INDEX idx_record_status_code ON routes_data_records');
        $this->failOn($failPattern);

        $tester = $this->runCommand($this->registry(), ['--update' => true, '--drop-obsolete' => $dropObsolete]);
        self::assertStringContainsString($expected, $tester->getDisplay());
    }

    public function testRecordsIndexUpToDateSkipsAndMissingColumnIndexIsSkipped(): void
    {
        $this->connect('mysql');
        $this->createBoth();
        $this->recordMetadata()->table['indexes']['idx_missing_column']    = ['columns' => ['no_such_column']];
        $this->recordMetadata()->table['indexes']['idx_empty']             = ['columns' => []];
        $this->routeDataMetadata()->table['indexes']['idx_missing_column'] = ['columns' => ['no_such_column']];
        $this->routeDataMetadata()->table['indexes']['idx_empty']          = ['columns' => []];

        $tester = $this->runCommand($this->registry(), ['--update' => true]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringNotContainsString('idx_missing_column', $tester->getDisplay());
    }

    public function testPostgresqlUpdateOfAnOrdinaryColumnUsesTheMysqlStatementAndFails(): void
    {
        $this->connect('postgresql');
        $this->createBoth();
        $this->sql('ALTER TABLE routes_data ALTER COLUMN name TYPE VARCHAR(100)');
        $this->sql('ALTER TABLE routes_data ALTER COLUMN name SET NOT NULL');
        $tester = $this->runCommand($this->registry(), ['--update' => true], false);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Failed to update column name', $tester->getDisplay());
    }

    public function testPostgresqlNeverEmitsTheMysqlIdRepair(): void
    {
        $this->connect('postgresql');
        $this->createBoth();
        // a plain INT id differs from the identity the entity expects: repairing it is MySQL-only, so it is reported
        $this->sql('ALTER TABLE routes_data ALTER COLUMN id DROP IDENTITY');
        $tester = $this->runCommand($this->registry(), ['--update' => true], false);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('only supported on MySQL/MariaDB', $tester->getDisplay());
        self::assertStringNotContainsString('MODIFY', $tester->getDisplay());
    }

    public function testMysqlCreateAddsAutoIncrementWhenTheGeneratorIsNone(): void
    {
        $this->connect('mysql');
        $this->routeDataMetadata()->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $this->recordMetadata()->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $this->routeDataMetadata()->fieldMappings['createdAt']->options = ['default' => '0000-00-00 00:00:00'];

        $tester  = $this->runCommand($this->registry());
        $display = $tester->getDisplay();
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString('(id INT NOT NULL AUTO_INCREMENT,', $display);
        // only the primary key gets AUTO_INCREMENT, never the route_data_id join column
        self::assertStringContainsString('route_data_id INT NOT NULL,', $display);
        self::assertStringContainsString('Access records table "routes_data_records" created successfully', $display);
        self::assertStringNotContainsString("'0000-00-00", $display);
    }

    public function testColumnDefaultsAndUnknownTypesAreCompared(): void
    {
        $this->connect('mysql');
        $this->createBoth();
        $meta = $this->routeDataMetadata();
        // numeric / string / boolean defaults and an unknown type (fallback SQL type mapping)
        $meta->fieldMappings['httpMethod']->options = ['default' => 'GET', 'fixed' => false, 'unsigned' => false, 'length' => 10];
        $meta->fieldMappings['reviewed']->options   = ['default' => true];
        $meta->fieldMappings['name']->options       = ['default' => 5, 'length' => 255];
        $this->sql('ALTER TABLE routes_data MODIFY name VARCHAR(255) NULL DEFAULT \'7\'');
        $this->sql('ALTER TABLE routes_data MODIFY http_method VARCHAR(10) NULL DEFAULT \'POST\'');
        $meta->fieldMappings['env']->type = 'no_such_dbal_type';

        $tester = $this->runCommand($this->registry(), ['--update' => true], false);
        self::assertStringContainsString('Differences:', $tester->getDisplay());
    }

    public function testMetadataWithoutTheOrmTableNameApiUsesTheConfiguredTableNames(): void
    {
        $this->connect('sqlite');
        $this->createBoth();
        $this->sql('DROP INDEX idx_route_env');
        $this->sql('DROP INDEX idx_record_status_code');
        $this->overrideMetadata(RouteData::class, $this->createMock(PersistenceClassMetadata::class));
        $this->overrideMetadata(RouteDataRecord::class, $this->createMock(PersistenceClassMetadata::class));

        $tester = $this->runCommand($this->registry(), ['--update' => true]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Created index idx_route_env', $tester->getDisplay());
        self::assertStringContainsString('Created index idx_record_status_code on records table', $tester->getDisplay());
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

    public function testMissingMetadataAndEmptySql(): void
    {
        $this->connect('sqlite');
        $tester = $this->runCommand($this->registry([]));
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('RouteData entity metadata not found', $tester->getDisplay());

        // RouteData only: the records table is skipped
        $tester = $this->runCommand($this->registry([$this->routeDataMetadata()]));
        self::assertStringContainsString('RouteDataRecord entity metadata not found', $tester->getDisplay());

        $this->sql('DROP TABLE routes_data');
        $this->realEntityManager()->getConfiguration()->setSchemaIgnoreClasses([RouteData::class, RouteDataRecord::class]);
        $tester = $this->runCommand($this->registry());
        self::assertStringContainsString('No SQL statements to execute', $tester->getDisplay());
    }

    public function testUpdateWhenTableVanishesBetweenChecks(): void
    {
        $this->connect('sqlite');
        $this->createBoth();
        $this->tablesExistAnswers([true, false]);
        $tester = $this->runCommand($this->registry(), ['--update' => true], false);
        self::assertStringContainsString('does not exist. Use the create command', $tester->getDisplay());
    }

    private function realConnectionOrFail(): Connection
    {
        return $this->realConnection ?? throw new RuntimeException('not connected');
    }
}
