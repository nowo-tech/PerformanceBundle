<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Command;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Nowo\PerformanceBundle\Command\RebuildAggregatesCommand;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Tests\Support\OrmTestSupport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * nowo:performance:rebuild-aggregates on a real ORM entity manager (SQLite in memory): DQL updates in one transaction
 * per batch, and the (host's) entity manager is never flushed nor cleared (REQ-RUNTIME-003 rule 2).
 */
final class RebuildAggregatesCommandTest extends TestCase
{
    private EntityManager $entityManager;
    private RebuildAggregatesCommand $command;

    protected function setUp(): void
    {
        $this->entityManager = OrmTestSupport::entityManager($this);
        $this->command       = new RebuildAggregatesCommand($this->entityManager);
    }

    public function testCommandName(): void
    {
        $this->assertSame('nowo:performance:rebuild-aggregates', $this->command->getName());
    }

    public function testCommandDescription(): void
    {
        $this->assertSame(
            'Rebuilds RouteData lastAccessedAt from RouteDataRecord entries (normalized: metrics are in records).',
            $this->command->getDescription(),
        );
    }

    public function testCommandOptionsAndHelp(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('env'));
        $this->assertFalse($definition->getOption('env')->isValueRequired());
        $this->assertSame('200', $definition->getOption('batch-size')->getDefault());
        $this->assertCount(0, $definition->getArguments());

        $help = $this->command->getHelp();
        $this->assertStringContainsString('rebuild', strtolower($help));
        $this->assertStringContainsString('--env', $help);
        $this->assertStringContainsString('--batch-size', $help);
    }

    public function testLegacyConstructorArgumentsAreAccepted(): void
    {
        $command = new RebuildAggregatesCommand(
            $this->entityManager,
            $this->createStub(RouteDataRepository::class),
            $this->createStub(RouteDataRecordRepository::class),
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWhenNoRoutesFound(): void
    {
        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Rebuilding aggregates for all environments', $tester->getDisplay());
        $this->assertStringContainsString('No RouteData records found', $tester->getDisplay());
    }

    public function testExecuteWhenNoRoutesFoundWithEnvFilter(): void
    {
        $this->route('home', 'dev');

        $tester = new CommandTester($this->command);
        $tester->execute(['--env' => 'prod']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Restricting rebuild to environment: prod', $tester->getDisplay());
        $this->assertStringContainsString('No RouteData records found', $tester->getDisplay());
    }

    public function testExecuteSetsLastAccessedAtToTheLatestRecordWithoutTouchingTheEntityManager(): void
    {
        $home  = $this->route('home', 'prod', '2026-01-01 00:00:00');
        $about = $this->route('about', 'prod', '2026-01-01 00:00:00');
        $this->record($home, '2026-03-01 10:00:00');
        $this->record($home, '2026-03-05 12:30:00');
        $this->record($home, '2026-03-02 08:00:00');
        $this->entityManager->clear();

        // Host state left in the entity manager: must stay managed and pending (never flushed, never cleared).
        $pending = (new RouteData())->setName('host_pending')->setEnv('prod');
        $this->entityManager->persist($pending);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Found 2 RouteData records', $tester->getDisplay());
        $this->assertStringContainsString('Rebuilt aggregates for 2 RouteData records', $tester->getDisplay());
        $this->assertSame('2026-03-05 12:30:00', $this->lastAccessed($home));
        $this->assertSame('2026-01-01 00:00:00', $this->lastAccessed($about), 'A route without records keeps its value.');
        $this->assertTrue($this->entityManager->getUnitOfWork()->isScheduledForInsert($pending));
        $this->assertSame(0, (int) $this->entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM routes_data WHERE name = 'host_pending'"));
        $this->assertSame(0, $this->entityManager->getConnection()->getTransactionNestingLevel());
    }

    public function testExecuteWithEnvFilterOnlyRebuildsThatEnvironment(): void
    {
        $prod = $this->route('home', 'prod', '2026-01-01 00:00:00');
        $dev  = $this->route('home', 'dev', '2026-01-01 00:00:00');
        $this->record($prod, '2026-04-01 00:00:00');
        $this->record($dev, '2026-04-02 00:00:00');

        $tester = new CommandTester($this->command);
        $tester->execute(['--env' => 'dev']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Found 1 RouteData records', $tester->getDisplay());
        $this->assertSame('2026-01-01 00:00:00', $this->lastAccessed($prod));
        $this->assertSame('2026-04-02 00:00:00', $this->lastAccessed($dev));
    }

    public function testExecuteWithBatchSizeShowsBatchMessages(): void
    {
        foreach (['a', 'b', 'c'] as $name) {
            $this->record($this->route($name, 'prod'), '2026-05-01 00:00:00');
        }

        $tester = new CommandTester($this->command);
        $tester->execute(['--batch-size' => '2']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Found 3 RouteData records', $tester->getDisplay());
        $this->assertStringContainsString('Processed batch 1 (2 records).', $tester->getDisplay());
        $this->assertStringNotContainsString('Processed batch 2', $tester->getDisplay());
    }

    public function testExecuteBatchSizeLessThanOneNormalizedTo200(): void
    {
        $this->record($this->route('home', 'prod'), '2026-05-01 00:00:00');

        $tester = new CommandTester($this->command);
        $tester->execute(['--batch-size' => '0']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringNotContainsString('Processed batch', $tester->getDisplay());
    }

    public function testBatchSizeIsCappedForThePostgreSqlParameterLimit(): void
    {
        $this->record($this->route('home', 'prod'), '2026-05-01 00:00:00');

        $tester = new CommandTester($this->command);
        $tester->execute(['--batch-size' => '70000']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame(10000, RebuildAggregatesCommand::MAX_BATCH_SIZE);
        $this->assertStringContainsString('at most 10000', $this->command->getDefinition()->getOption('batch-size')->getDescription());
    }

    private function route(string $name, string $env, string $lastAccessedAt = '2026-01-01 00:00:00'): RouteData
    {
        $route = (new RouteData())->setName($name)->setEnv($env)->setLastAccessedAt(new DateTimeImmutable($lastAccessedAt));
        $this->entityManager->persist($route);
        $this->entityManager->flush();

        return $route;
    }

    private function record(RouteData $route, string $accessedAt): void
    {
        $record = new RouteDataRecord();
        $record->setRouteData($this->entityManager->getReference(RouteData::class, $route->getId()));
        $record->setAccessedAt(new DateTimeImmutable($accessedAt));
        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }

    private function lastAccessed(RouteData $route): string
    {
        return (string) $this->entityManager->getConnection()->fetchOne('SELECT last_accessed_at FROM routes_data WHERE id = ?', [$route->getId()]);
    }
}
