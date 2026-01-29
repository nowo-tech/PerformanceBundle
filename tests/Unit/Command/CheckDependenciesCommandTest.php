<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Command;

use Nowo\PerformanceBundle\Command\CheckDependenciesCommand;
use Nowo\PerformanceBundle\Service\DependencyChecker;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckDependenciesCommandTest extends TestCase
{
    private DependencyChecker|MockObject $dependencyChecker;
    private CheckDependenciesCommand $command;

    protected function setUp(): void
    {
        $this->dependencyChecker = $this->createMock(DependencyChecker::class);
        $this->command = new CheckDependenciesCommand($this->dependencyChecker);
    }

    public function testCommandName(): void
    {
        $this->assertSame('nowo:performance:check-dependencies', $this->command->getName());
    }

    public function testCommandDescription(): void
    {
        $this->assertSame('Check if optional dependencies are installed for the Performance Bundle', $this->command->getDescription());
    }

    public function testExecuteWithAllDependenciesInstalled(): void
    {
        $this->dependencyChecker->method('getDependencyStatus')->willReturn([
            'twig_component' => [
                'available' => true,
                'package' => 'symfony/ux-twig-component',
                'required' => false,
            ],
            'icons' => [
                'available' => true,
                'package' => 'symfony/ux-icons',
                'required' => false,
            ],
        ]);

        $this->dependencyChecker->method('getMissingDependencies')->willReturn([]);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('All optional dependencies are installed', $tester->getDisplay());
    }

    public function testExecuteWithMissingDependencies(): void
    {
        $this->dependencyChecker->method('getDependencyStatus')->willReturn([
            'twig_component' => [
                'available' => false,
                'package' => 'symfony/ux-twig-component',
                'required' => false,
            ],
            'icons' => [
                'available' => true,
                'package' => 'symfony/ux-icons',
                'required' => false,
            ],
        ]);

        $this->dependencyChecker->method('getMissingDependencies')->willReturn([
            'twig_component' => [
                'required' => false,
                'package' => 'symfony/ux-twig-component',
                'message' => 'Symfony UX Twig Component is not installed.',
                'install_command' => 'composer require symfony/ux-twig-component',
                'feature' => 'Twig Components',
            ],
        ]);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Missing Optional Dependencies', $tester->getDisplay());
        $this->assertStringContainsString('symfony/ux-twig-component', $tester->getDisplay());
    }

    public function testCommandHelpMentionsDependencies(): void
    {
        $help = $this->command->getHelp();
        $this->assertStringContainsString('check-dependencies', $help);
        $this->assertStringContainsString('dependencies', $help);
    }

    public function testExecuteShowsStatusForEachFeature(): void
    {
        $this->dependencyChecker->method('getDependencyStatus')->willReturn([
            'twig_component' => [
                'available' => true,
                'package' => 'symfony/ux-twig-component',
                'required' => false,
            ],
            'icons' => [
                'available' => false,
                'package' => 'symfony/ux-icons',
                'required' => false,
            ],
        ]);

        $this->dependencyChecker->method('getMissingDependencies')->willReturn([
            'icons' => [
                'required' => false,
                'package' => 'symfony/ux-icons',
                'message' => 'Symfony UX Icons is not installed.',
                'install_command' => 'composer require symfony/ux-icons',
                'feature' => 'UX Icons',
            ],
        ]);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('twig_component', $output);
        $this->assertStringContainsString('icons', $output);
        $this->assertStringContainsString('✓ Installed', $output);
        $this->assertStringContainsString('✗ Not installed', $output);
    }
}
