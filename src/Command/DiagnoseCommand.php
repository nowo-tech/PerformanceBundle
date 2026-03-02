<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Command;

use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Nowo\PerformanceBundle\Service\TableStatusChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use function sprintf;

/**
 * Command to diagnose performance bundle configuration and status.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(
    name: 'nowo:performance:diagnose',
    description: 'Diagnose Performance Bundle configuration and query tracking status',
)]
final class DiagnoseCommand extends Command
{
    /**
     * Creates a new instance.
     *
     * @param ParameterBagInterface $parameterBag Parameter bag to check configuration
     * @param TableStatusChecker|null $tableStatusChecker Table status checker (optional)
     */
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly ?TableStatusChecker $tableStatusChecker = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(
            <<<'HELP'
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

<info>php bin/console nowo:performance:diagnose</info>
<info>php %command.full_name%</info>

The command will show detailed information about:
  - Whether query tracking is working correctly
  - Which DoctrineBundle version is detected
  - How middleware is registered (YAML config vs reflection)
  - Database connection and table status
HELP
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Performance Bundle - Diagnostic');

        // Check configuration
        $io->section('Configuration');
        $enabled          = $this->parameterBag->get('nowo_performance.enabled');
        $trackQueries     = $this->parameterBag->get('nowo_performance.track_queries');
        $trackRequestTime = $this->parameterBag->get('nowo_performance.track_request_time');
        $connection       = $this->parameterBag->get('nowo_performance.connection');
        $environments     = $this->parameterBag->get('nowo_performance.environments');

        $io->table(
            ['Setting', 'Value'],
            [
                ['Enabled', $enabled ? '✓ Yes' : '✗ No'],
                ['Track Queries', $trackQueries ? '✓ Yes' : '✗ No'],
                ['Track Request Time', $trackRequestTime ? '✓ Yes' : '✗ No'],
                ['Connection', $connection],
                ['Environments', implode(', ', $environments)],
            ],
        );

        // Check database tables (main + records table when enable_access_records) — single batch to avoid N+1
        $checkTableStatus = $this->parameterBag->get('nowo_performance.check_table_status') ?? true;
        if ($this->tableStatusChecker !== null && $checkTableStatus) {
            $io->section('Database Tables');
            $mainStatus   = $this->tableStatusChecker->getMainTableStatus();
            $mainName     = $mainStatus['table_name'];
            $mainExists   = $mainStatus['exists'];
            $mainComplete = $mainStatus['complete'];
            $mainMissing  = $mainStatus['missing_columns'];

            $tableRows = [
                [
                    $mainName,
                    $mainExists ? '✓ Yes' : '✗ No',
                    $mainComplete ? '✓ Yes' : '✗ No',
                    empty($mainMissing) ? '—' : implode(', ', $mainMissing),
                ],
            ];
            $recordsStatus = $this->tableStatusChecker->getRecordsTableStatus();
            if ($recordsStatus !== null) {
                $tableRows[] = [
                    $recordsStatus['table_name'],
                    $recordsStatus['exists'] ? '✓ Yes' : '✗ No',
                    $recordsStatus['complete'] ? '✓ Yes' : '✗ No',
                    empty($recordsStatus['missing_columns']) ? '—' : implode(', ', $recordsStatus['missing_columns']),
                ];
            }
            $io->table(['Table', 'Exists', 'Complete', 'Missing columns'], $tableRows);

            if (!$mainExists) {
                $io->note('Main table: php bin/console nowo:performance:create-table');
            } elseif (!empty($mainMissing)) {
                $io->note('Main table: php bin/console nowo:performance:create-table --update');
            }
            if ($recordsStatus !== null) {
                if (!$recordsStatus['exists']) {
                    $io->note('Records table: php bin/console nowo:performance:create-records-table');
                } elseif (!empty($recordsStatus['missing_columns'])) {
                    $io->note('Records table: php bin/console nowo:performance:sync-schema or nowo:performance:create-records-table --update');
                }
            }
        } elseif ($this->tableStatusChecker !== null && !$checkTableStatus) {
            $io->section('Database Tables');
            $io->note('Table status check is disabled (nowo_performance.check_table_status: false). Set to true to see table existence and missing columns here.');
        }

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
        $initialTime  = QueryTrackingMiddleware::getTotalQueryTime();

        $io->text(sprintf('Initial query count: %d', $initialCount));
        $io->text(sprintf('Initial query time: %.4f seconds', $initialTime));

        // Check if middleware class exists
        if (class_exists(QueryTrackingMiddleware::class)) {
            $io->success('QueryTrackingMiddleware class is available');
        } else {
            $io->error('QueryTrackingMiddleware class not found!');

            return Command::FAILURE;
        }

        // Instructions
        $io->section('Query Tracking Status');

        if ($initialCount === 0 && $initialTime === 0.0) {
            $io->info('QueryTrackingMiddleware is initialized correctly.');
        } else {
            $io->warning('QueryTrackingMiddleware has existing data. This may indicate it\'s working but not being reset between requests.');
        }

        $io->section('How Query Tracking Works');

        // Detect DoctrineBundle version and method used
        $doctrineVersion = \Nowo\PerformanceBundle\DBAL\QueryTrackingMiddlewareRegistry::detectDoctrineBundleVersion();
        $method          = 'Event Subscriber (Reflection)';

        $io->text([
            'DoctrineBundle Version: ' . ($doctrineVersion ?? 'Unknown'),
            'Registration Method: ' . $method,
            '',
            'The bundle uses Event Subscriber with reflection to apply middleware:',
            '',
            '• Works across all DoctrineBundle versions (2.x and 3.x)',
            '• No YAML configuration required (avoids compatibility issues)',
            '• Applies middleware at runtime using reflection',
            '',
            'If queries are not being tracked, check:',
            '1. That track_queries is enabled (currently: ' . ($trackQueries ? '✓' : '✗') . ')',
            '2. That the bundle is enabled (currently: ' . ($enabled ? '✓' : '✗') . ')',
            '3. That you\'re in a configured environment: ' . implode(', ', $environments),
            '',
            'Fallback methods (if middleware fails):',
            '• DoctrineDataCollector (from Symfony profiler)',
            '• Request attributes (profiler data)',
            '• Stopwatch (time only)',
        ]);

        return Command::SUCCESS;
    }
}
