<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Command;

use Nowo\PerformanceBundle\Service\DependencyChecker;
use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class CheckDependenciesCommandIntegrationTest extends TestCase
{
    private KernelInterface $kernel;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel('test', true);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public function testCheckDependenciesCommandRunsSuccessfully(): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $command = $application->find('nowo:performance:check-dependencies');
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        $display = $tester->getDisplay();
        self::assertTrue(
            str_contains($display, 'Dependencies') || str_contains($display, 'dependency') || str_contains($display, 'UX'),
            'Output should mention dependencies or UX',
        );
    }

    public function testDependencyCheckerGetMissingDependenciesReturnsArray(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();
        $checker = $kernel->getContainer()->get(DependencyChecker::class);
        $missing = $checker->getMissingDependencies();
        self::assertIsArray($missing);
        $kernel->shutdown();
    }

    public function testDependencyCheckerAvailabilityMethods(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();
        $checker = $kernel->getContainer()->get(DependencyChecker::class);
        self::assertIsBool($checker->isTwigComponentAvailable());
        self::assertIsBool($checker->isIconsAvailable());
        self::assertIsBool($checker->isMessengerAvailable());
        self::assertIsBool($checker->isMailerAvailable());
        self::assertIsBool($checker->isHttpClientAvailable());
        $kernel->shutdown();
    }
}
