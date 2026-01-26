<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Command;

use Nowo\PerformanceBundle\Command\SetRouteMetricsCommand;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;

final class SetRouteMetricsCommandTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;
    private SetRouteMetricsCommand $command;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->command = new SetRouteMetricsCommand($this->metricsService);
    }

    public function testCommandName(): void
    {
        $this->assertSame('nowo:performance:set-route', $this->command->getName());
    }

    public function testCommandDescription(): void
    {
        $this->assertSame('Set or update route performance metrics', $this->command->getDescription());
    }

    public function testExecuteWithNewRoute(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);
        $routeData->setQueryTime(0.2);

        $this->metricsService
            ->expects($this->once())
            ->method('getRouteData')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with('app_home', 'dev', 0.5, 10, 0.2, null);

        $this->metricsService
            ->expects($this->once())
            ->method('getRouteData')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'route' => 'app_home',
            '--request-time' => '0.5',
            '--queries' => '10',
            '--query-time' => '0.2',
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
        $updatedRoute->setRequestTime(0.8);
        $updatedRoute->setTotalQueries(15);

        $this->metricsService
            ->expects($this->exactly(2))
            ->method('getRouteData')
            ->with('app_home', 'dev')
            ->willReturnOnConsecutiveCalls($existingRoute, $updatedRoute);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with('app_home', 'dev', 0.8, 15, null, null);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'route' => 'app_home',
            '--request-time' => '0.8',
            '--queries' => '15',
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
            ->with('app_home', 'prod', 1.2, 25, null, null);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'route' => 'app_home',
            '--env' => 'prod',
            '--request-time' => '1.2',
            '--queries' => '25',
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
            ->with('app_home', 'dev', 0.5, null, null, $params);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'route' => 'app_home',
            '--request-time' => '0.5',
            '--params' => '{"id":123,"slug":"test"}',
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());
    }

    public function testExecuteWithInvalidJsonParams(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'route' => 'app_home',
            '--request-time' => '0.5',
            '--params' => 'invalid json',
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
            ->willThrowException(new \Exception('Database error'));

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'route' => 'app_home',
            '--request-time' => '0.5',
        ]);

        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('Error saving route metrics', $commandTester->getDisplay());
    }
}
