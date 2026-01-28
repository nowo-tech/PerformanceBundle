<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Command;

use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to set or update route performance metrics.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(
    name: 'nowo:performance:set-route',
    description: 'Set or update route performance metrics',
)]
final class SetRouteMetricsCommand extends Command
{
    /**
     * Creates a new instance.
     *
     * @param PerformanceMetricsService $metricsService Service for recording metrics
     */
    public function __construct(
        private readonly PerformanceMetricsService $metricsService,
    ) {
        parent::__construct();
    }

    /**
     * Configure the command.
     */
    protected function configure(): void
    {
        $this->setHelp(<<<'HELP'
The <info>%command.name%</info> command allows you to manually set or update performance metrics for a specific route.

This is useful for:
  - Testing the dashboard with sample data
  - Manually recording metrics from external monitoring tools
  - Setting baseline metrics for comparison

<info>php %command.full_name% app_home --request-time=0.5 --queries=10</info>

The command will create a new record if the route doesn't exist, or update the existing record
if the new metrics indicate worse performance (higher request time or more queries).

Options:
  - <info>--env</info>: Environment name (default: dev)
  - <info>--request-time</info>: Request execution time in seconds (float)
  - <info>--queries</info>: Total number of database queries (integer)
  - <info>--query-time</info>: Total query execution time in seconds (float)
  - <info>--memory</info>: Peak memory usage in bytes (integer)
HELP
        );

        $this
            ->addArgument('route', InputArgument::REQUIRED, 'Route name')
            ->addOption('env', 'e', InputOption::VALUE_REQUIRED, 'Environment (dev, test, prod)', 'dev')
            ->addOption('request-time', 'r', InputOption::VALUE_REQUIRED, 'Request time in seconds (float)')
            ->addOption('queries', null, InputOption::VALUE_REQUIRED, 'Total number of queries (integer)')
            ->addOption('query-time', 't', InputOption::VALUE_REQUIRED, 'Total query execution time in seconds (float)')
            ->addOption('params', 'p', InputOption::VALUE_REQUIRED, 'Route parameters as JSON string')
            ->addOption('memory', 'm', InputOption::VALUE_REQUIRED, 'Peak memory usage in bytes (integer)');
    }

    /**
     * Execute the command.
     *
     * @param InputInterface  $input  The input interface
     * @param OutputInterface $output The output interface
     *
     * @return int Command exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $routeName = $input->getArgument('route');
        $env = $input->getOption('env');
        $requestTime = null !== $input->getOption('request-time') ? (float) $input->getOption('request-time') : null;
        $totalQueries = null !== $input->getOption('queries') ? (int) $input->getOption('queries') : null;
        $queryTime = null !== $input->getOption('query-time') ? (float) $input->getOption('query-time') : null;
        $memoryUsage = null !== $input->getOption('memory') ? (int) $input->getOption('memory') : null;
        $paramsJson = $input->getOption('params');
        $params = null;

        if (null !== $paramsJson) {
            $params = json_decode($paramsJson, true);
            if (\JSON_ERROR_NONE !== json_last_error()) {
                $io->error(\sprintf('Invalid JSON in params: %s', json_last_error_msg()));

                return Command::FAILURE;
            }
        }

        // Validate that at least one metric is provided
        if (null === $requestTime && null === $totalQueries && null === $queryTime && null === $memoryUsage) {
            $io->error('At least one metric must be provided (--request-time, --queries, --query-time, or --memory)');

            return Command::FAILURE;
        }

        try {
            // Check if route exists
            $existingRoute = $this->metricsService->getRouteData($routeName, $env);

            if (null === $existingRoute) {
                $io->info(\sprintf('Creating new route metrics for "%s" in environment "%s"', $routeName, $env));
            } else {
                $io->info(\sprintf('Updating route metrics for "%s" in environment "%s"', $routeName, $env));
            }

            // Record metrics
            $this->metricsService->recordMetrics(
                $routeName,
                $env,
                $requestTime,
                $totalQueries,
                $queryTime,
                $params,
                $memoryUsage
            );

            $routeData = $this->metricsService->getRouteData($routeName, $env);
            if (null !== $routeData) {
                $routesWithAgg = $this->metricsService->getRoutesWithAggregates($env);
                $display = null;
                foreach ($routesWithAgg as $r) {
                    if ($r->getName() === $routeName) {
                        $display = $r;
                        break;
                    }
                }
                $io->success('Route metrics saved successfully!');
                $io->table(
                    ['Metric', 'Value'],
                    [
                        ['Route Name', $routeData->getName() ?? 'N/A'],
                        ['Environment', $routeData->getEnv() ?? 'N/A'],
                        ['Request Time', null !== $display?->getRequestTime() ? \sprintf('%.4f s', $display->getRequestTime()) : 'N/A (from records)'],
                        ['Total Queries', $display?->getTotalQueries() ?? 'N/A'],
                        ['Query Time', null !== $display?->getQueryTime() ? \sprintf('%.4f s', $display->getQueryTime()) : 'N/A'],
                        ['Memory Usage', null !== $display?->getMemoryUsage() ? \sprintf('%s MB', number_format($display->getMemoryUsage() / 1024 / 1024, 2)) : 'N/A'],
                        ['Last Accessed', $routeData->getLastAccessedAt()?->format('Y-m-d H:i:s') ?? 'N/A'],
                    ]
                );
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(\sprintf('Error saving route metrics: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
