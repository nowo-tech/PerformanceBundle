<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Command;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\TransactionDoctrine\DoctrineTransactionRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_chunk;
use function array_map;
use function count;
use function sprintf;

/**
 * Command to rebuild RouteData aggregates from RouteDataRecord entries.
 *
 * Updates lastAccessedAt and related metadata on RouteData from records (normalized: metrics live in records).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(
    name: 'nowo:performance:rebuild-aggregates',
    description: 'Rebuilds RouteData lastAccessedAt from RouteDataRecord entries (normalized: metrics are in records).',
)]
final class RebuildAggregatesCommand extends Command
{
    /**
     * Creates a new instance.
     *
     * The repositories are no longer used (3.18.0: the command updates through DQL and loads no entity); the
     * parameters stay for backward compatibility of the constructor.
     *
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     * @param RouteDataRepository|null $routeDataRepository Unused since 3.18.0
     * @param RouteDataRecordRepository|null $recordRepository Unused since 3.18.0
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        ?RouteDataRepository $routeDataRepository = null,
        ?RouteDataRecordRepository $recordRepository = null,
    ) {
        unset($routeDataRepository, $recordRepository);
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(
            <<<'HELP'
The <info>nowo:performance:rebuild-aggregates</info> command rebuilds RouteData aggregates from RouteDataRecord entries.

Updates lastAccessedAt and related metadata on RouteData from records (normalized: metrics live in records).

<info>php bin/console nowo:performance:rebuild-aggregates</info>
<info>php bin/console nowo:performance:rebuild-aggregates --env=prod --batch-size=100</info>
HELP
        );
        $this
            ->addOption(
                'env',
                null,
                InputOption::VALUE_OPTIONAL,
                'Environment to rebuild (dev, test, prod, etc.). If omitted, all environments are processed.',
            )
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_OPTIONAL,
                'Number of RouteData records to process per batch.',
                '200',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $envFilter = $input->getOption('env');
        $batchSize = (int) $input->getOption('batch-size');

        if ($batchSize < 1) {
            $batchSize = 200;
        }

        $io->title('[NowoPerformanceBundle] Rebuilding RouteData aggregates from RouteDataRecord');

        if ($envFilter !== null) {
            $io->text(sprintf('Restricting rebuild to environment: <info>%s</info>', $envFilter));
        } else {
            $io->text('Rebuilding aggregates for all environments.');
        }

        // Ids only: no RouteData or RouteDataRecord is loaded into the (host's) entity manager, which the command
        // therefore never needs to flush or clear (REQ-RUNTIME-003 rule 2).
        $idQuery = $this->entityManager->createQueryBuilder()
            ->select('r.id')
            ->from(RouteData::class, 'r')
            ->orderBy('r.env', 'ASC')
            ->addOrderBy('r.name', 'ASC');
        if ($envFilter !== null) {
            $idQuery->where('r.env = :env')->setParameter('env', $envFilter);
        }

        /** @var list<int> $ids */
        $ids   = array_map(intval(...), $idQuery->getQuery()->getSingleColumnResult());
        $total = count($ids);

        if ($total === 0) {
            $io->warning('No RouteData records found for the given criteria. Nothing to rebuild.');

            return Command::SUCCESS;
        }

        $io->text(sprintf('Found <info>%d</info> RouteData records to process.', $total));
        $io->progressStart($total);

        // One short transaction per batch (rule 9): DQL updates only, so nothing is left pending in the entity manager.
        $runner     = DoctrineTransactionRunner::forConnection($this->entityManager->getConnection());
        $batchIndex = 0;
        $processed  = 0;

        foreach (array_chunk($ids, $batchSize) as $batch) {
            $runner->run(fn () => $this->rebuildBatch($batch));

            $processed += count($batch);
            $io->progressAdvance(count($batch));
            if (count($batch) === $batchSize) {
                ++$batchIndex;
                $io->text(sprintf('Processed batch %d (%d records).', $batchIndex, $processed));
            }
        }

        $io->progressFinish();
        $io->success(sprintf('Rebuilt aggregates for %d RouteData records.', $total));

        return Command::SUCCESS;
    }

    /**
     * Sets lastAccessedAt of each RouteData of the batch to the time of its latest RouteDataRecord (routes without
     * records keep their value).
     *
     * @param list<int> $ids RouteData ids
     */
    private function rebuildBatch(array $ids): void
    {
        /** @var list<array{id: int|string, last: string}> $rows (accessedAt is never NULL) */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(rec.routeData) AS id', 'MAX(rec.accessedAt) AS last')
            ->from(RouteDataRecord::class, 'rec')
            ->where('rec.routeData IN (:ids)')
            ->setParameter('ids', $ids)
            ->groupBy('rec.routeData')
            ->getQuery()
            ->getScalarResult();

        foreach ($rows as $row) {
            $this->entityManager->createQueryBuilder()
                ->update(RouteData::class, 'r')
                ->set('r.lastAccessedAt', ':last')
                ->where('r.id = :id')
                ->setParameter('last', new DateTimeImmutable($row['last']), Types::DATETIME_IMMUTABLE)
                ->setParameter('id', (int) $row['id'])
                ->getQuery()
                ->execute();
        }
    }
}
