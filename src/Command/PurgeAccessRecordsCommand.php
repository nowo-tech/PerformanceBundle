<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Command;

use DateTimeImmutable;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use function is_string;
use function sprintf;

/**
 * Purge access records (RouteDataRecord) older than a given period or all records.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(
    name: 'nowo:performance:purge-records',
    description: 'Purge access records (temporal records) older than X days or all records',
)]
final class PurgeAccessRecordsCommand extends Command
{
    public function __construct(
        private readonly ?RouteDataRecordRepository $recordRepository,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('older-than', 'o', InputOption::VALUE_REQUIRED, 'Delete records older than this many days (e.g. 30)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Delete all access records')
            ->addOption('env', 'e', InputOption::VALUE_REQUIRED, 'Limit to a specific environment (dev, prod, etc.)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without actually deleting')
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command deletes access records (RouteDataRecord) based on age or all at once.

When <info>access_records_retention_days</info> is configured in <comment>nowo_performance</comment>,
you can run this command via cron to automatically purge old records:

  <info>php %command.full_name%</info>

Without options, uses the configured retention. With <info>--older-than=30</info>, deletes records
older than 30 days. With <info>--all</info>, deletes all access records.

Examples:

  <info>php %command.full_name% --older-than=30</info>     Delete records older than 30 days
  <info>php %command.full_name% --older-than=7 --env=prod</info>  Delete prod records older than 7 days
  <info>php %command.full_name% --all</info>               Delete all access records
  <info>php %command.full_name% --all --env=dev</info>     Delete all dev access records
  <info>php %command.full_name% --dry-run --older-than=30</info>  Preview what would be deleted

Requires <comment>enable_access_records: true</comment>.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->parameterBag->get('nowo_performance.enable_access_records')) {
            $io->error('Access records are disabled. Set enable_access_records: true in configuration.');

            return Command::FAILURE;
        }

        if ($this->recordRepository === null) {
            $io->error('RouteDataRecordRepository is not available.');

            return Command::FAILURE;
        }

        $env = $input->getOption('env');
        $env = is_string($env) && $env !== '' ? $env : null;

        $dryRun = $input->getOption('dry-run');

        if ($input->getOption('all')) {
            if ($dryRun) {
                $io->warning('Dry-run: would delete all access records' . ($env ? " for env \"{$env}\"" : '') . '.');
                $io->note('Run without --dry-run to actually delete.');

                return Command::SUCCESS;
            }

            $deleted = $this->recordRepository->deleteAllRecords($env);
            $io->success(sprintf('Deleted %d access record(s).', $deleted));

            return Command::SUCCESS;
        }

        $olderThan     = $input->getOption('older-than');
        $retentionDays = $this->parameterBag->get('nowo_performance.access_records_retention_days');

        if ($olderThan === null && $retentionDays === null) {
            $io->error('No retention configured and --older-than not specified. Configure access_records_retention_days or use --older-than=N or --all.');

            return Command::FAILURE;
        }

        $days = $olderThan !== null ? (int) $olderThan : (int) $retentionDays;
        if ($days < 1) {
            $io->error('Days must be at least 1.');

            return Command::FAILURE;
        }

        $before = new DateTimeImmutable('-' . $days . ' days');

        if ($dryRun) {
            $io->warning(sprintf('Dry-run: would delete records older than %s (%d days)' . ($env ? " for env \"{$env}\"" : '') . '.', $before->format('Y-m-d H:i'), $days));
            $io->note('Run without --dry-run to actually delete.');

            return Command::SUCCESS;
        }

        $deleted = $this->recordRepository->deleteOlderThan($before, $env);
        $io->success(sprintf('Deleted %d access record(s) older than %s (%d days).', $deleted, $before->format('Y-m-d'), $days));

        return Command::SUCCESS;
    }
}
