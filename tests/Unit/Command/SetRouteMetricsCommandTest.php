<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Command;

use Exception;
use Nowo\PerformanceBundle\Command\SetRouteMetricsCommand;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SetRouteMetricsCommandTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;
    private SetRouteMetricsCommand $command;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->command        = new SetRouteMetricsCommand($this->metricsService);
    }

    public function testCommandName(): void
    {
        $this->assertSame('nowo:performance:set-route', $this->command->getName());
    }

    public function testCommandDescription(): void
    {
        $this->assertSame('Set or update route performance metrics', $this->command->getDescription());
    }

    public function testCommandHasEnvMemoryAndQueryTimeOptions(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('env'));
        $this->assertTrue($definition->hasOption('memory'));
        $this->assertTrue($definition->hasOption('query-time'));
        $this->assertTrue($definition->hasArgument('route'));
    }

    public function testCommandHelpContainsMetricsAndRoute(): void
    {
        $help = $this->command->getHelp();
        $this->assertStringContainsString('metrics', strtolower($help));
        $this->assertStringContainsString('route', strtolower($help));
    }

    public function testExecuteWithNewRoute(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $this->metricsService
            ->expects($this->exactly(2))
            ->method('getRouteData')
            ->with('app_home', 'dev')
            ->willReturnOnConsecutiveCalls(null, $routeData);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with('app_home', 'dev', 0.5, 10, 0.2, null, null);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'route'          => 'app_home',
            '--request-time' => '0.5',
            '--queries'      => '10',
            '--query-time'   => '0.2',
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Creating new route metrics', $commandTester->getDisplay());
        $this->assertStringContainsString('Route metrics saved successfully', $commandTester->getDisplay());
    }

    public function testExecuteWithExistingRoute(): void
    {
        $existingRoute = new RouteData();
        $existingRoute->setName('app_home');
        $existingRoute->setEnv('dev');

        $updatedRoute = new RouteData();
        $updatedRoute->setName('app_home');
        $updatedRoute->setEnv('dev');

        $this->metricsService
            ->expects($this->exactly(2))
            ->method('getRouteData')
            ->with('app_home', 'dev')
            ->willReturnOnConsecutiveCalls($existingRoute, $updatedRoute);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with('app_home', 'dev', 0.8, 15, null, null, null);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'route'          => 'app_home',
            '--request-time' => '0.8',
            '--queries'      => '15',
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Updating route metrics', $commandTester->getDisplay());
    }

    public function testExecuteWithCustomEnvironment(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('prod');

        $this->metricsService
            ->expects($this->exactly(2))
            ->method('getRouteData')
            ->with('app_home', 'prod')
            ->willReturn(null, $routeData);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with('app_home', 'prod', 1.2, 25, null, null, null);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'route'          => 'app_home',
            '--env'          => 'prod',
            '--request-time' => '1.2',
            '--queries'      => '25',
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());
    }

    public function testExecuteWithParams(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $params = ['id' => 123, 'slug' => 'test'];

        $this->metricsService
            ->expects($this->exactly(2))
            ->method('getRouteData')
            ->with('app_home', 'dev')
            ->willReturn(null, $routeData);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with('app_home', 'dev', 0.5, null, null, $params, null);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'route'          => 'app_home',
            '--request-time' => '0.5',
            '--params'       => '{"id":123,"slug":"test"}',
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());
    }

    public function testExecuteWithInvalidJsonParams(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'route'          => 'app_home',
            '--request-time' => '0.5',
            '--params'       => 'invalid json',
        ]);

        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('Invalid JSON', $commandTester->getDisplay());
    }

    public function testExecuteWithoutMetrics(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'route' => 'app_home',
        ]);

        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('At least one metric must be provided', $commandTester->getDisplay());
    }

    public function testExecuteWithException(): void
    {
        $this->metricsService
            ->expects($this->once())
            ->method('getRouteData')
            ->willThrowException(new Exception('Database error'));

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'route'          => 'app_home',
            '--request-time' => '0.5',
        ]);

        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('Error saving route metrics', $commandTester->getDisplay());
    }

    public function testExecuteWithMemoryOnly(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_foo');
        $routeData->setEnv('dev');

        $this->metricsService
            ->expects($this->exactly(2))
            ->method('getRouteData')
            ->with('api_foo', 'dev')
            ->willReturn(null, $routeData);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with('api_foo', 'dev', null, null, null, null, 1048576);

        $this->metricsService->method('getRoutesWithAggregates')->with('dev')->willReturn([]);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'route'    => 'api_foo',
            '--memory' => '1048576',
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Creating new route metrics', $commandTester->getDisplay());
    }

    public function testExecuteWithQueryTimeOnly(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_bar');
        $routeData->setEnv('dev');

        $this->metricsService
            ->expects($this->exactly(2))
            ->method('getRouteData')
            ->with('app_bar', 'dev')
            ->willReturn(null, $routeData);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with('app_bar', 'dev', null, null, 0.15, null, null);

        $this->metricsService->method('getRoutesWithAggregates')->with('dev')->willReturn([]);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'route'        => 'app_bar',
            '--query-time' => '0.15',
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());
    }

    public function testCommandHelpContainsOptions(): void
    {
        $help = $this->command->getHelp();
        $this->assertStringContainsString('--request-time', $help);
        $this->assertStringContainsString('--queries', $help);
        $this->assertStringContainsString('--query-time', $help);
        $this->assertStringContainsString('--memory', $help);
        $this->assertStringContainsString('--env', $help);
    }

    public function testCommandHasRouteArgument(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasArgument('route'));
        $this->assertSame(1, $definition->getArgumentCount());
    }

    public function testCommandHasParamsOption(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('params'));
    }

    public function testExecuteWithStageEnvironment(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_dashboard');
        $routeData->setEnv('stage');

        $this->metricsService
            ->expects($this->exactly(2))
            ->method('getRouteData')
            ->with('api_dashboard', 'stage')
            ->willReturn(null, $routeData);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with('api_dashboard', 'stage', 0.5, null, null, null, null);

        $this->metricsService->method('getRoutesWithAggregates')->with('stage')->willReturn([]);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'route'          => 'api_dashboard',
            '--env'          => 'stage',
            '--request-time' => '0.5',
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('stage', $commandTester->getDisplay());
    }
}
