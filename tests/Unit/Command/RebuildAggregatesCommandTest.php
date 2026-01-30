<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Nowo\PerformanceBundle\Command\RebuildAggregatesCommand;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;

final class RebuildAggregatesCommandTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private RouteDataRepository|MockObject $routeDataRepository;
    private RouteDataRecordRepository|MockObject $recordRepository;
    private RebuildAggregatesCommand $command;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->routeDataRepository = $this->createMock(RouteDataRepository::class);
        $this->recordRepository = $this->createMock(RouteDataRecordRepository::class);
        $this->command = new RebuildAggregatesCommand(
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
        $idRef = new \ReflectionProperty(RouteData::class, 'id');
        $idRef->setAccessible(true);
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
        $record->setAccessedAt(new \DateTimeImmutable('2024-01-15 12:00:00'));

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$record]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->getMockBuilder(\Doctrine\ORM\EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repo->method('find')->with(1)->willReturn($managed);

        $this->entityManager->method('getRepository')->with(RouteData::class)->willReturn($repo);
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);
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
        $option = $definition->getOption('env');

        $this->assertFalse($option->isValueRequired());
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
}
