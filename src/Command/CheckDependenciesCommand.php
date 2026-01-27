<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Command;

use Nowo\PerformanceBundle\Service\DependencyChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to check if required dependencies are installed.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
#[AsCommand(
    name: 'nowo:performance:check-dependencies',
    description: 'Check if optional dependencies are installed for the Performance Bundle',
    help: <<<'HELP'
The <info>%command.name%</info> command checks the status of optional dependencies for the Performance Bundle.

This command verifies:
  - Symfony UX TwigComponent availability
  - Other optional dependencies (if any)

Use this command to:
  - Verify which optional features are available
  - Get installation instructions for missing dependencies
  - Understand what features are using fallback mode

<info>php %command.full_name%</info>

The bundle will work without optional dependencies, but some features may use fallback implementations.
For example, if Symfony UX TwigComponent is not installed, the dashboard will use traditional Twig includes
instead of Twig Components, which may have slightly different performance characteristics.
HELP
)]
final class CheckDependenciesCommand extends Command
{
    /**
     * Constructor.
     *
     * @param DependencyChecker $dependencyChecker Dependency checker service
     */
    public function __construct(
        private readonly DependencyChecker $dependencyChecker,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Performance Bundle - Dependency Check');

        $status = $this->dependencyChecker->getDependencyStatus();
        $missing = $this->dependencyChecker->getMissingDependencies();

        $hasIssues = false;

        foreach ($status as $feature => $info) {
            if ($info['available']) {
                $io->success(\sprintf('%s: ✓ Installed (%s)', $feature, $info['package']));
            } else {
                $hasIssues = true;
                $io->warning(\sprintf('%s: ✗ Not installed (%s)', $feature, $info['package']));
            }
        }

        if (!empty($missing)) {
            $io->section('Missing Optional Dependencies');
            $io->note('The bundle will work with fallback functionality, but installing these will improve performance:');

            $rows = [];
            foreach ($missing as $dep) {
                $rows[] = [
                    $dep['feature'],
                    $dep['package'],
                    $dep['message'],
                    $dep['install_command'],
                ];
            }

            $io->table(
                ['Feature', 'Package', 'Message', 'Install Command'],
                $rows
            );

            $io->newLine();
            $io->info('To install all missing optional dependencies, run:');
            foreach ($missing as $dep) {
                $io->text(\sprintf('  <comment>%s</comment>', $dep['install_command']));
            }
        } else {
            $io->success('All optional dependencies are installed!');
        }

        return $hasIssues ? Command::SUCCESS : Command::SUCCESS;
    }
}
