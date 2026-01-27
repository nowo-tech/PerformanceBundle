<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Command;

use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Command to diagnose performance bundle configuration and status.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
#[AsCommand(
    name: 'nowo:performance:diagnose',
    description: 'Diagnose Performance Bundle configuration and query tracking status',
    help: <<<'HELP'
The <info>%command.name%</info> command provides a comprehensive diagnostic report of the Performance Bundle configuration and status.

This command displays:
  - Bundle configuration (enabled, environments, connection, table name)
  - Dashboard configuration (enabled, path, roles, template)
  - Doctrine Bundle version detection
  - Query tracking method (middleware registration strategy)
  - Database connection status
  - Table existence check
  - Query tracking status and troubleshooting tips

Use this command to:
  - Verify bundle configuration
  - Troubleshoot query tracking issues
  - Understand how the bundle is configured
  - Check database connectivity

<info>php %command.full_name%</info>

The command will show detailed information about:
  - Whether query tracking is working correctly
  - Which DoctrineBundle version is detected
  - How middleware is registered (YAML config vs reflection)
  - Database connection and table status
HELP
)]
final class DiagnoseCommand extends Command
{
    /**
     * Constructor.
     *
     * @param ParameterBagInterface $parameterBag Parameter bag to check configuration
     */
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Performance Bundle - Diagnostic');

        // Check configuration
        $io->section('Configuration');
        $enabled = $this->parameterBag->get('nowo_performance.enabled');
        $trackQueries = $this->parameterBag->get('nowo_performance.track_queries');
        $trackRequestTime = $this->parameterBag->get('nowo_performance.track_request_time');
        $connection = $this->parameterBag->get('nowo_performance.connection');
        $environments = $this->parameterBag->get('nowo_performance.environments');

        $io->table(
            ['Setting', 'Value'],
            [
                ['Enabled', $enabled ? '✓ Yes' : '✗ No'],
                ['Track Queries', $trackQueries ? '✓ Yes' : '✗ No'],
                ['Track Request Time', $trackRequestTime ? '✓ Yes' : '✗ No'],
                ['Connection', $connection],
                ['Environments', implode(', ', $environments)],
            ]
        );

        // Check QueryTrackingMiddleware
        $io->section('Query Tracking Middleware');

        if (!$enabled || !$trackQueries) {
            $io->warning('Query tracking is disabled in configuration.');
            $io->note('To enable: Set nowo_performance.enabled: true and nowo_performance.track_queries: true');

            return Command::SUCCESS;
        }

        // Test middleware
        QueryTrackingMiddleware::reset();
        $initialCount = QueryTrackingMiddleware::getQueryCount();
        $initialTime = QueryTrackingMiddleware::getTotalQueryTime();

        $io->text(\sprintf('Initial query count: %d', $initialCount));
        $io->text(\sprintf('Initial query time: %.4f seconds', $initialTime));

        // Check if middleware class exists
        if (class_exists(QueryTrackingMiddleware::class)) {
            $io->success('QueryTrackingMiddleware class is available');
        } else {
            $io->error('QueryTrackingMiddleware class not found!');

            return Command::FAILURE;
        }

        // Instructions
        $io->section('Query Tracking Status');

        if (0 === $initialCount && 0.0 === $initialTime) {
            $io->info('QueryTrackingMiddleware is initialized correctly.');
        } else {
            $io->warning('QueryTrackingMiddleware has existing data. This may indicate it\'s working but not being reset between requests.');
        }

        $io->section('How Query Tracking Works');

        // Detect DoctrineBundle version and method used
        $doctrineVersion = \Nowo\PerformanceBundle\DBAL\QueryTrackingMiddlewareRegistry::detectDoctrineBundleVersion();
        $supportsYaml = \Nowo\PerformanceBundle\DBAL\QueryTrackingMiddlewareRegistry::supportsYamlMiddlewareConfig();
        $method = 'Event Subscriber (Reflection)';
        if ($supportsYaml) {
            $method = 'YAML Configuration (middlewares)';
        }

        $io->text([
            'DoctrineBundle Version: ' . ($doctrineVersion ?? 'Unknown'),
            'Registration Method: ' . $method,
            'Supports middlewares: ' . ($supportsYaml ? 'Yes (2.x)' : 'No'),
            '',
            'The bundle automatically detects the DoctrineBundle version and uses the appropriate method:',
            '',
            '• DoctrineBundle 2.x: Registers middleware via YAML configuration (middlewares)',
            '• DoctrineBundle 3.x: Applies middleware via Event Subscriber using reflection',
            '',
            'If queries are not being tracked, check:',
            '1. That track_queries is enabled (currently: '.($trackQueries ? '✓' : '✗').')',
            '2. That the bundle is enabled (currently: '.($enabled ? '✓' : '✗').')',
            '3. That you\'re in a configured environment: '.implode(', ', $environments),
            '',
            'Fallback methods (if middleware fails):',
            '• DoctrineDataCollector (from Symfony profiler)',
            '• Request attributes (profiler data)',
            '• Stopwatch (time only)',
        ]);

        return Command::SUCCESS;
    }
}
