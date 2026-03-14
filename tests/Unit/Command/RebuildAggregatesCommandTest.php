<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Command;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\PerformanceBundle\Command\RebuildAggregatesCommand;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Console\Tester\CommandTester;

final class RebuildAggregatesCommandTest extends TestCase
{
    private MockObject $entityManager;
    private MockObject $routeDataRepository;
    private MockObject $recordRepository;
    private RebuildAggregatesCommand $command;

    protected function setUp(): void
    {
        $this->entityManager       = $this->createMock(EntityManagerInterface::class);
        $this->routeDataRepository = $this->createMock(RouteDataRepository::class);
        $this->recordRepository    = $this->createMock(RouteDataRecordRepository::class);
        $this->command             = new RebuildAggregatesCommand(
            $this->entityManager,
            $this->routeDataRepository,
            $this->recordRepository,
        );
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

    public function testCommandHasEnvOption(): void
    {
        $this->assertTrue($this->command->getDefinition()->hasOption('env'));
    }

    public function testCommandHasBatchSizeOption(): void
    {
        $this->assertTrue($this->command->getDefinition()->hasOption('batch-size'));
    }

    public function testExecuteWhenNoRoutesFound(): void
    {
        $this->routeDataRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([], ['env' => 'ASC', 'name' => 'ASC'])
            ->willReturn([]);

        $this->entityManager->expects($this->never())->method('flush');
        $this->entityManager->expects($this->never())->method('clear');

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No RouteData records found', $tester->getDisplay());
    }

    public function testExecuteWhenNoRoutesFoundWithEnvFilter(): void
    {
        $this->routeDataRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['env' => 'prod'], ['env' => 'ASC', 'name' => 'ASC'])
            ->willReturn([]);

        $tester = new CommandTester($this->command);
        $tester->execute(['--env' => 'prod']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Restricting rebuild to environment', $tester->getDisplay());
        $this->assertStringContainsString('prod', $tester->getDisplay());
    }

    public function testExecuteRebuildsAggregatesAndFlushes(): void
    {
        $route = new RouteData();
        $route->setName('app_home')->setEnv('dev');
        $idRef = new ReflectionProperty(RouteData::class, 'id');
        $idRef->setValue($route, 1);

        $this->routeDataRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([], ['env' => 'ASC', 'name' => 'ASC'])
            ->willReturn([$route]);

        $managed = new RouteData();
        $managed->setName('app_home')->setEnv('dev');
        $idRef->setValue($managed, 1);

        $record = new RouteDataRecord();
        $record->setRouteData($managed);
        $record->setAccessedAt(new DateTimeImmutable('2024-01-15 12:00:00'));

        $repo = $this->getMockBuilder(\Doctrine\ORM\EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repo->method('find')->with(1)->willReturn($managed);

        $this->recordRepository
            ->expects($this->once())
            ->method('findBy')
            ->with($this->identicalTo(['routeData' => $managed]), $this->identicalTo(['accessedAt' => 'ASC']))
            ->willReturn([$record]);

        $this->entityManager->method('getRepository')->with(RouteData::class)->willReturn($repo);
        $this->entityManager->expects($this->atLeastOnce())->method('flush');
        $this->entityManager->expects($this->atLeastOnce())->method('clear');

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Found 1 RouteData records', $tester->getDisplay());
        $this->assertStringContainsString('Rebuilt aggregates for 1 RouteData records', $tester->getDisplay());
        $this->assertNotNull($managed->getLastAccessedAt());
        $this->assertSame('2024-01-15 12:00:00', $managed->getLastAccessedAt()->format('Y-m-d H:i:s'));
    }

    public function testExecuteBatchSizeLessThanOneNormalizedTo200(): void
    {
        $this->routeDataRepository->method('findBy')->willReturn([]);

        $tester = new CommandTester($this->command);
        $tester->execute(['--batch-size' => '0']);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testCommandHelpMentionsRebuildAndAggregates(): void
    {
        $help = $this->command->getHelp();
        $this->assertStringContainsString('rebuild', strtolower($help));
        $this->assertStringContainsString('--env', $help);
        $this->assertStringContainsString('--batch-size', $help);
    }

    public function testCommandHasNoArguments(): void
    {
        $this->assertCount(0, $this->command->getDefinition()->getArguments());
    }

    public function testCommandEnvOptionHasDefault(): void
    {
        $definition = $this->command->getDefinition();
        $option     = $definition->getOption('env');

        $this->assertFalse($option->isValueRequired());
    }

    public function testCommandBatchSizeOptionHasDefault(): void
    {
        $definition = $this->command->getDefinition();
        $option     = $definition->getOption('batch-size');

        $this->assertTrue($definition->hasOption('batch-size'));
        $this->assertSame('200', $option->getDefault());
    }

    public function testExecuteWithStageEnvFilter(): void
    {
        $this->routeDataRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['env' => 'stage'], ['env' => 'ASC', 'name' => 'ASC'])
            ->willReturn([]);

        $tester = new CommandTester($this->command);
        $tester->execute(['--env' => 'stage']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('stage', $tester->getDisplay());
    }

    public function testExecuteWithTestEnvFilter(): void
    {
        $this->routeDataRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['env' => 'test'], ['env' => 'ASC', 'name' => 'ASC'])
            ->willReturn([]);

        $tester = new CommandTester($this->command);
        $tester->execute(['--env' => 'test']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('test', $tester->getDisplay());
    }

    /** Multiple routes with batch-size 2 triggers batch flush and "Processed batch" message. */
    public function testExecuteWithMultipleRoutesAndBatchSizeShowsBatchMessage(): void
    {
        $route1 = new RouteData();
        $route1->setName('r1')->setEnv('dev');
        $idRef = new ReflectionProperty(RouteData::class, 'id');
        $idRef->setValue($route1, 1);

        $route2 = new RouteData();
        $route2->setName('r2')->setEnv('dev');
        $idRef->setValue($route2, 2);

        $route3 = new RouteData();
        $route3->setName('r3')->setEnv('dev');
        $idRef->setValue($route3, 3);

        $this->routeDataRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([], ['env' => 'ASC', 'name' => 'ASC'])
            ->willReturn([$route1, $route2, $route3]);

        $managed1 = new RouteData();
        $managed1->setName('r1')->setEnv('dev');
        $idRef->setValue($managed1, 1);
        $managed2 = new RouteData();
        $managed2->setName('r2')->setEnv('dev');
        $idRef->setValue($managed2, 2);
        $managed3 = new RouteData();
        $managed3->setName('r3')->setEnv('dev');
        $idRef->setValue($managed3, 3);

        $record1 = new RouteDataRecord();
        $record1->setRouteData($managed1);
        $record1->setAccessedAt(new DateTimeImmutable('2024-01-01 10:00:00'));
        $record2 = new RouteDataRecord();
        $record2->setRouteData($managed2);
        $record2->setAccessedAt(new DateTimeImmutable('2024-01-01 11:00:00'));
        $record3 = new RouteDataRecord();
        $record3->setRouteData($managed3);
        $record3->setAccessedAt(new DateTimeImmutable('2024-01-01 12:00:00'));

        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('find')
            ->willReturnCallback(static fn (int $id): ?RouteData => match ($id) {
                1 => $managed1,
                2 => $managed2,
                3 => $managed3,
                default => null,
            });

        $this->recordRepository
            ->method('findBy')
            ->willReturnOnConsecutiveCalls(
                [$record1],
                [$record2],
                [$record3],
            );

        $this->entityManager->method('getRepository')->with(RouteData::class)->willReturn($repo);
        $this->entityManager->expects($this->atLeastOnce())->method('flush');
        $this->entityManager->expects($this->atLeastOnce())->method('clear');

        $tester = new CommandTester($this->command);
        $tester->execute(['--batch-size' => '2']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Found 3 RouteData records', $tester->getDisplay());
        $this->assertStringContainsString('Processed batch 1 (2 records)', $tester->getDisplay());
        $this->assertStringContainsString('Rebuilt aggregates for 3 RouteData records', $tester->getDisplay());
    }

    /** When managed route is not found (find returns null), route is skipped. */
    public function testExecuteWhenManagedNotFoundSkipsRoute(): void
    {
        $route = new RouteData();
        $route->setName('orphan')->setEnv('dev');
        $idRef = new ReflectionProperty(RouteData::class, 'id');
        $idRef->setValue($route, 99);

        $this->routeDataRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn([$route]);

        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('find')->with(99)->willReturn(null);

        $this->entityManager->method('getRepository')->with(RouteData::class)->willReturn($repo);
        $this->recordRepository->expects($this->never())->method('findBy');

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Found 1 RouteData records', $tester->getDisplay());
    }

    /** When route has no records, rebuildAggregatesForRoute skips setLastAccessedAt (empty records path). */
    public function testExecuteWhenNoRecordsForRouteCompletesWithoutError(): void
    {
        $route = new RouteData();
        $route->setName('empty')->setEnv('dev');
        $idRef = new ReflectionProperty(RouteData::class, 'id');
        $idRef->setValue($route, 1);

        $this->routeDataRepository->method('findBy')->willReturn([$route]);

        $managed = new RouteData();
        $managed->setName('empty')->setEnv('dev');
        $idRef->setValue($managed, 1);

        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('find')->with(1)->willReturn($managed);

        $this->recordRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['routeData' => $managed], ['accessedAt' => 'ASC'])
            ->willReturn([]);

        $this->entityManager->method('getRepository')->with(RouteData::class)->willReturn($repo);
        $this->entityManager->expects($this->atLeastOnce())->method('flush');
        $this->entityManager->expects($this->atLeastOnce())->method('clear');

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Found 1 RouteData records', $tester->getDisplay());
    }
}
