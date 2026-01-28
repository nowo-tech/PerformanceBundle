<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
     * @param EntityManagerInterface     $entityManager       Doctrine entity manager
     * @param RouteDataRepository        $routeDataRepository Repository for RouteData
     * @param RouteDataRecordRepository  $recordRepository    Repository for RouteDataRecord
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RouteDataRepository $routeDataRepository,
        private readonly RouteDataRecordRepository $recordRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
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

        $criteria = [];
        if (null !== $envFilter) {
            $criteria['env'] = $envFilter;
            $io->text(\sprintf('Restricting rebuild to environment: <info>%s</info>', $envFilter));
        } else {
            $io->text('Rebuilding aggregates for all environments.');
        }

        /** @var list<RouteData> $routes */
        $routes = $this->routeDataRepository->findBy($criteria, ['env' => 'ASC', 'name' => 'ASC']);
        $total = \count($routes);

        if (0 === $total) {
            $io->warning('No RouteData records found for the given criteria. Nothing to rebuild.');

            return Command::SUCCESS;
        }

        $io->text(\sprintf('Found <info>%d</info> RouteData records to process.', $total));
        $io->progressStart($total);

        $processed = 0;
        $batchIndex = 0;

        foreach ($routes as $routeData) {
            ++$processed;

            $this->rebuildAggregatesForRoute($routeData);

            if (0 === $processed % $batchSize) {
                ++$batchIndex;
                $this->entityManager->flush();
                $this->entityManager->clear();
                $io->text(\sprintf('Processed batch %d (%d records).', $batchIndex, $processed));
            }

            $io->progressAdvance();
        }

        // Flush remaining changes
        $this->entityManager->flush();
        $this->entityManager->clear();

        $io->progressFinish();
        $io->success(\sprintf('Rebuilt aggregates for %d RouteData records.', $total));

        return Command::SUCCESS;
    }

    /**
     * Rebuild aggregate fields for a single RouteData using its associated RouteDataRecord entries.
     */
    private function rebuildAggregatesForRoute(RouteData $routeData): void
    {
        // Reload the managed instance from EntityManager in case we cleared earlier
        /** @var RouteData|null $managed */
        $managed = $this->entityManager->getRepository(RouteData::class)->find($routeData->getId());
        if (null === $managed) {
            return;
        }

        // Fetch all records for this RouteData
        $qb = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(RouteDataRecord::class, 'r')
            ->where('r.routeData = :routeData')
            ->setParameter('routeData', $managed)
            ->orderBy('r.accessedAt', 'ASC');

        /** @var list<RouteDataRecord> $records */
        $records = $qb->getQuery()->getResult();

        if (empty($records)) {
            return;
        }

        // Normalized: RouteData only has lastAccessedAt; metrics live in RouteDataRecord
        $lastAccessedAt = end($records)?->getAccessedAt();
        if (null !== $lastAccessedAt) {
            $managed->setLastAccessedAt($lastAccessedAt);
        }
    }
}

