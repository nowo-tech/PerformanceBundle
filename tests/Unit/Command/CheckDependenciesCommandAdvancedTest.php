<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Command;

use Nowo\PerformanceBundle\Command\CheckDependenciesCommand;
use Nowo\PerformanceBundle\Service\DependencyChecker;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Advanced tests for CheckDependenciesCommand edge cases.
 */
final class CheckDependenciesCommandAdvancedTest extends TestCase
{
    private DependencyChecker|MockObject $dependencyChecker;
    private CheckDependenciesCommand $command;

    protected function setUp(): void
    {
        $this->dependencyChecker = $this->createMock(DependencyChecker::class);
        $this->command = new CheckDependenciesCommand($this->dependencyChecker);
    }

    public function testExecuteWithAllDependenciesInstalled(): void
    {
        $tester = new CommandTester($this->command);

        $this->dependencyChecker
            ->method('getDependencyStatus')
            ->willReturn([
                'twig_component' => ['available' => true, 'package' => 'symfony/ux-twig-component', 'required' => false],
                'icons' => ['available' => true, 'package' => 'symfony/ux-icons', 'required' => true],
                'messenger' => ['available' => true, 'package' => 'symfony/messenger', 'required' => false],
                'mailer' => ['available' => true, 'package' => 'symfony/mailer', 'required' => false],
                'http_client' => ['available' => true, 'package' => 'symfony/http-client', 'required' => false],
            ]);

        $this->dependencyChecker
            ->method('getMissingDependencies')
            ->willReturn([]);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('All optional dependencies are installed', $tester->getDisplay());
    }

    public function testExecuteWithAllDependenciesMissing(): void
    {
        $tester = new CommandTester($this->command);

        $this->dependencyChecker
            ->method('getDependencyStatus')
            ->willReturn([
                'twig_component' => ['available' => false, 'package' => 'symfony/ux-twig-component', 'required' => false],
                'icons' => ['available' => false, 'package' => 'symfony/ux-icons', 'required' => true],
                'messenger' => ['available' => false, 'package' => 'symfony/messenger', 'required' => false],
                'mailer' => ['available' => false, 'package' => 'symfony/mailer', 'required' => false],
                'http_client' => ['available' => false, 'package' => 'symfony/http-client', 'required' => false],
            ]);

        $this->dependencyChecker
            ->method('getMissingDependencies')
            ->willReturn([
                'twig_component' => [
                    'required' => false,
                    'package' => 'symfony/ux-twig-component',
                    'message' => 'Twig Component not installed',
                    'install_command' => 'composer require symfony/ux-twig-component',
                    'feature' => 'Twig Components',
                ],
                'icons' => [
                    'required' => false,
                    'package' => 'symfony/ux-icons',
                    'message' => 'Icons not installed',
                    'install_command' => 'composer require symfony/ux-icons',
                    'feature' => 'UX Icons',
                ],
            ]);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Missing Optional Dependencies', $tester->getDisplay());
        $this->assertStringContainsString('symfony/ux-twig-component', $tester->getDisplay());
        $this->assertStringContainsString('symfony/ux-icons', $tester->getDisplay());
    }

    public function testExecuteWithMixedDependencies(): void
    {
        $tester = new CommandTester($this->command);

        $this->dependencyChecker
            ->method('getDependencyStatus')
            ->willReturn([
                'twig_component' => ['available' => true, 'package' => 'symfony/ux-twig-component', 'required' => false],
                'icons' => ['available' => false, 'package' => 'symfony/ux-icons', 'required' => true],
                'messenger' => ['available' => true, 'package' => 'symfony/messenger', 'required' => false],
                'mailer' => ['available' => false, 'package' => 'symfony/mailer', 'required' => false],
                'http_client' => ['available' => true, 'package' => 'symfony/http-client', 'required' => false],
            ]);

        $this->dependencyChecker
            ->method('getMissingDependencies')
            ->willReturn([
                'icons' => [
                    'required' => false,
                    'package' => 'symfony/ux-icons',
                    'message' => 'Icons not installed',
                    'install_command' => 'composer require symfony/ux-icons',
                    'feature' => 'UX Icons',
                ],
                'mailer' => [
                    'required' => false,
                    'package' => 'symfony/mailer',
                    'message' => 'Mailer not installed',
                    'install_command' => 'composer require symfony/mailer',
                    'feature' => 'Email notifications',
                ],
            ]);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('✓ Installed', $tester->getDisplay());
        $this->assertStringContainsString('✗ Not installed', $tester->getDisplay());
        $this->assertStringContainsString('Missing Optional Dependencies', $tester->getDisplay());
    }

    public function testExecuteDisplaysSuccessForInstalled(): void
    {
        $tester = new CommandTester($this->command);

        $this->dependencyChecker
            ->method('getDependencyStatus')
            ->willReturn([
                'twig_component' => ['available' => true, 'package' => 'symfony/ux-twig-component', 'required' => false],
            ]);

        $this->dependencyChecker
            ->method('getMissingDependencies')
            ->willReturn([]);

        $tester->execute([]);

        $this->assertStringContainsString('twig_component', $tester->getDisplay());
        $this->assertStringContainsString('✓ Installed', $tester->getDisplay());
    }

    public function testExecuteDisplaysWarningForMissing(): void
    {
        $tester = new CommandTester($this->command);

        $this->dependencyChecker
            ->method('getDependencyStatus')
            ->willReturn([
                'icons' => ['available' => false, 'package' => 'symfony/ux-icons', 'required' => true],
            ]);

        $this->dependencyChecker
            ->method('getMissingDependencies')
            ->willReturn([
                'icons' => [
                    'required' => false,
                    'package' => 'symfony/ux-icons',
                    'message' => 'Icons not installed',
                    'install_command' => 'composer require symfony/ux-icons',
                    'feature' => 'UX Icons',
                ],
            ]);

        $tester->execute([]);

        $this->assertStringContainsString('icons', $tester->getDisplay());
        $this->assertStringContainsString('✗ Not installed', $tester->getDisplay());
    }

    public function testExecuteDisplaysInstallCommands(): void
    {
        $tester = new CommandTester($this->command);

        $this->dependencyChecker
            ->method('getDependencyStatus')
            ->willReturn([
                'icons' => ['available' => false, 'package' => 'symfony/ux-icons', 'required' => true],
            ]);

        $this->dependencyChecker
            ->method('getMissingDependencies')
            ->willReturn([
                'icons' => [
                    'required' => false,
                    'package' => 'symfony/ux-icons',
                    'message' => 'Icons not installed',
                    'install_command' => 'composer require symfony/ux-icons',
                    'feature' => 'UX Icons',
                ],
            ]);

        $tester->execute([]);

        $this->assertStringContainsString('composer require symfony/ux-icons', $tester->getDisplay());
    }

    public function testExecuteDisplaysTableForMissingDependencies(): void
    {
        $tester = new CommandTester($this->command);

        $this->dependencyChecker
            ->method('getDependencyStatus')
            ->willReturn([
                'icons' => ['available' => false, 'package' => 'symfony/ux-icons', 'required' => true],
            ]);

        $this->dependencyChecker
            ->method('getMissingDependencies')
            ->willReturn([
                'icons' => [
                    'required' => false,
                    'package' => 'symfony/ux-icons',
                    'message' => 'Icons not installed',
                    'install_command' => 'composer require symfony/ux-icons',
                    'feature' => 'UX Icons',
                ],
            ]);

        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Feature', $output);
        $this->assertStringContainsString('Package', $output);
        $this->assertStringContainsString('Message', $output);
        $this->assertStringContainsString('Install Command', $output);
    }
}
