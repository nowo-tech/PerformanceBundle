<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Command;

use Nowo\PerformanceBundle\Command\SetRouteMetricsCommand;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Advanced tests for SetRouteMetricsCommand edge cases.
 */
final class SetRouteMetricsCommandAdvancedTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;
    private SetRouteMetricsCommand $command;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->command = new SetRouteMetricsCommand($this->metricsService);
    }

    public function testExecuteWithInvalidJsonParams(): void
    {
        $tester = new CommandTester($this->command);

        $this->metricsService
            ->expects($this->never())
            ->method('recordMetrics');

        $tester->execute([
            'route' => 'app_home',
            '--params' => 'invalid json{',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid JSON', $tester->getDisplay());
    }

    public function testExecuteWithValidJsonParams(): void
    {
        $tester = new CommandTester($this->command);
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $params = ['id' => 123, 'slug' => 'test'];

        $this->metricsService
            ->method('getRouteData')
            ->willReturn(null, $routeData);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                null,
                null,
                null,
                $params,
                null
            );

        $tester->execute([
            'route' => 'app_home',
            '--params' => json_encode($params),
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWithEmptyJsonParams(): void
    {
        $tester = new CommandTester($this->command);
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $this->metricsService
            ->method('getRouteData')
            ->willReturn(null, $routeData);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                null,
                null,
                null,
                [],
                null
            );

        $tester->execute([
            'route' => 'app_home',
            '--params' => '{}',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWithNoMetricsProvided(): void
    {
        $tester = new CommandTester($this->command);

        $this->metricsService
            ->expects($this->never())
            ->method('recordMetrics');

        $tester->execute([
            'route' => 'app_home',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('At least one metric must be provided', $tester->getDisplay());
    }

    public function testExecuteWithOnlyRequestTime(): void
    {
        $tester = new CommandTester($this->command);
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');
        $routeData->setRequestTime(0.5);

        $this->metricsService
            ->method('getRouteData')
            ->willReturn(null, $routeData);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                0.5,
                null,
                null,
                null,
                null
            );

        $tester->execute([
            'route' => 'app_home',
            '--request-time' => '0.5',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWithOnlyQueries(): void
    {
        $tester = new CommandTester($this->command);
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');
        $routeData->setTotalQueries(10);

        $this->metricsService
            ->method('getRouteData')
            ->willReturn(null, $routeData);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                null,
                10,
                null,
                null,
                null
            );

        $tester->execute([
            'route' => 'app_home',
            '--queries' => '10',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWithOnlyQueryTime(): void
    {
        $tester = new CommandTester($this->command);
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');
        $routeData->setQueryTime(0.2);

        $this->metricsService
            ->method('getRouteData')
            ->willReturn(null, $routeData);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                null,
                null,
                0.2,
                null,
                null
            );

        $tester->execute([
            'route' => 'app_home',
            '--query-time' => '0.2',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWithOnlyMemory(): void
    {
        $tester = new CommandTester($this->command);
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');
        $routeData->setMemoryUsage(1024 * 1024);

        $this->metricsService
            ->method('getRouteData')
            ->willReturn(null, $routeData);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                null,
                null,
                null,
                null,
                1048576
            );

        $tester->execute([
            'route' => 'app_home',
            '--memory' => '1048576',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWithAllMetrics(): void
    {
        $tester = new CommandTester($this->command);
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('prod');
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);
        $routeData->setQueryTime(0.2);
        $routeData->setMemoryUsage(1024 * 1024);

        $this->metricsService
            ->method('getRouteData')
            ->willReturn(null, $routeData);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'prod',
                0.5,
                10,
                0.2,
                null,
                1048576
            );

        $tester->execute([
            'route' => 'app_home',
            '--env' => 'prod',
            '--request-time' => '0.5',
            '--queries' => '10',
            '--query-time' => '0.2',
            '--memory' => '1048576',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWithCustomEnvironment(): void
    {
        $tester = new CommandTester($this->command);
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('stage');

        $this->metricsService
            ->method('getRouteData')
            ->with('app_home', 'stage')
            ->willReturn(null, $routeData);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'stage',
                0.5,
                null,
                null,
                null,
                null
            );

        $tester->execute([
            'route' => 'app_home',
            '--env' => 'stage',
            '--request-time' => '0.5',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWithExistingRoute(): void
    {
        $tester = new CommandTester($this->command);
        $existingRoute = new RouteData();
        $existingRoute->setName('app_home');
        $existingRoute->setEnv('dev');

        $updatedRoute = new RouteData();
        $updatedRoute->setName('app_home');
        $updatedRoute->setEnv('dev');
        $updatedRoute->setRequestTime(0.6);

        $this->metricsService
            ->method('getRouteData')
            ->willReturn($existingRoute, $updatedRoute);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics');

        $tester->execute([
            'route' => 'app_home',
            '--request-time' => '0.6',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Updating route metrics', $tester->getDisplay());
    }

    public function testExecuteWithZeroValues(): void
    {
        $tester = new CommandTester($this->command);
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');
        $routeData->setRequestTime(0.0);
        $routeData->setTotalQueries(0);

        $this->metricsService
            ->method('getRouteData')
            ->willReturn(null, $routeData);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                0.0,
                0,
                null,
                null,
                null
            );

        $tester->execute([
            'route' => 'app_home',
            '--request-time' => '0',
            '--queries' => '0',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWithNegativeValues(): void
    {
        $tester = new CommandTester($this->command);
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $this->metricsService
            ->method('getRouteData')
            ->willReturn(null, $routeData);

        // Negative values are technically valid (though not realistic)
        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                -1.0,
                -5,
                null,
                null,
                null
            );

        $tester->execute([
            'route' => 'app_home',
            '--request-time' => '-1',
            '--queries' => '-5',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWithVeryLargeValues(): void
    {
        $tester = new CommandTester($this->command);
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $this->metricsService
            ->method('getRouteData')
            ->willReturn(null, $routeData);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                999999.99,
                999999,
                null,
                null,
                999999999
            );

        $tester->execute([
            'route' => 'app_home',
            '--request-time' => '999999.99',
            '--queries' => '999999',
            '--memory' => '999999999',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteDisplaysTableWithNullValues(): void
    {
        $tester = new CommandTester($this->command);
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');
        $routeData->setRequestTime(null);
        $routeData->setTotalQueries(null);
        $routeData->setQueryTime(null);
        $routeData->setMemoryUsage(null);
        $routeData->setUpdatedAt(null);

        $this->metricsService
            ->method('getRouteData')
            ->willReturn(null, $routeData);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics');

        $tester->execute([
            'route' => 'app_home',
            '--request-time' => '0.5',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('N/A', $tester->getDisplay());
    }
}
