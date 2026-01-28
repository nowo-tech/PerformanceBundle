<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PerformanceBundle\Command\RebuildAggregatesCommand;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class RebuildAggregatesCommandTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private RouteDataRepository|MockObject $routeDataRepository;
    private RouteDataRecordRepository|MockObject $recordRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->routeDataRepository = $this->createMock(RouteDataRepository::class);
        $this->recordRepository = $this->createMock(RouteDataRecordRepository::class);
    }

    private function createCommand(): RebuildAggregatesCommand
    {
        return new RebuildAggregatesCommand(
            $this->entityManager,
            $this->routeDataRepository,
            $this->recordRepository,
        );
    }

    public function testCommandName(): void
    {
        $cmd = $this->createCommand();
        $this->assertSame('nowo:performance:rebuild-aggregates', $cmd->getName());
    }

    public function testCommandDescription(): void
    {
        $cmd = $this->createCommand();
        $this->assertStringContainsString('RouteData', $cmd->getDescription());
        $this->assertStringContainsString('RouteDataRecord', $cmd->getDescription());
        $this->assertStringContainsString('lastAccessedAt', $cmd->getDescription());
    }

    public function testCommandHasEnvOption(): void
    {
        $cmd = $this->createCommand();
        $def = $cmd->getDefinition();
        $this->assertTrue($def->hasOption('env'));
        $opt = $def->getOption('env');
        $this->assertFalse($opt->isValueRequired());
        $this->assertStringContainsString('environment', $opt->getDescription());
    }

    public function testCommandHasBatchSizeOption(): void
    {
        $cmd = $this->createCommand();
        $def = $cmd->getDefinition();
        $this->assertTrue($def->hasOption('batch-size'));
        $opt = $def->getOption('batch-size');
        $this->assertSame('200', $opt->getDefault());
    }

    public function testExecuteWhenNoRouteDataReturnsSuccess(): void
    {
        $this->routeDataRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([], ['env' => 'ASC', 'name' => 'ASC'])
            ->willReturn([]);

        $this->entityManager->expects($this->never())->method('flush');

        $cmd = $this->createCommand();
        $out = new BufferedOutput();
        $code = $cmd->run(new ArrayInput([]), $out);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('No RouteData records found', $out->fetch());
    }

    public function testExecuteWhenNoRouteDataWithEnvFilter(): void
    {
        $this->routeDataRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['env' => 'prod'], ['env' => 'ASC', 'name' => 'ASC'])
            ->willReturn([]);

        $cmd = $this->createCommand();
        $out = new BufferedOutput();
        $code = $cmd->run(new ArrayInput(['--env' => 'prod']), $out);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('No RouteData records found', $out->fetch());
    }

    public function testExecuteUsesBatchSizeOption(): void
    {
        $route = new \Nowo\PerformanceBundle\Entity\RouteData();
        $route->setName('app_home');
        $route->setEnv('dev');
        $ref = new \ReflectionClass($route);
        $idProp = $ref->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($route, 1);

        $this->routeDataRepository->method('findBy')->willReturn([$route]);

        $repo = $this->createMock(\Doctrine\Persistence\ObjectRepository::class);
        $repo->method('find')->with(1)->willReturn($route);
        $this->entityManager->method('getRepository')->with(\Nowo\PerformanceBundle\Entity\RouteData::class)->willReturn($repo);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $q = $this->createMock(\Doctrine\ORM\Query::class);
        $q->method('getResult')->willReturn([]);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($q);
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $this->entityManager->expects($this->atLeastOnce())->method('flush');
        $this->entityManager->expects($this->atLeastOnce())->method('clear');

        $cmd = $this->createCommand();
        $out = new BufferedOutput();
        $code = $cmd->run(new ArrayInput(['--batch-size' => '1']), $out);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('Rebuilt aggregates', $out->fetch());
    }
}
