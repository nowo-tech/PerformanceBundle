<?php

declare(strict_types=1);

namespace App\Command;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function in_array;
use function sprintf;

/**
 * Demo only (REQ-DEMO-013): seeds a week of realistic, deterministic access records for a few demo routes so the
 * dashboard, statistics, heatmap and access records pages have data worth looking at. Re-running replaces the
 * seeded routes (env "dev", names prefixed with "demo_").
 */
#[AsCommand(name: 'app:demo:seed-metrics', description: 'Seed a week of demo performance metrics (dev environment)')]
final class DemoSeedMetricsCommand extends Command
{
    /** Route name => [method, path, base response time (s), queries, memory (MB), hits per day, error ratio]. */
    private const ROUTES = [
        'demo_home'             => ['GET', '/', 0.045, 3, 14, 60, 0.0],
        'demo_product_index'    => ['GET', '/product/', 0.12, 12, 18, 42, 0.01],
        'demo_product_show'     => ['GET', '/product/42', 0.08, 6, 16, 30, 0.03],
        'demo_product_edit'     => ['POST', '/product/42/edit', 0.62, 28, 26, 9, 0.05],
        'demo_report_export'    => ['GET', '/reports/export', 1.35, 64, 58, 4, 0.12],
        'demo_api_search'       => ['GET', '/api/search', 0.21, 9, 21, 25, 0.02],
        'demo_checkout_confirm' => ['POST', '/checkout/confirm', 0.48, 18, 24, 7, 0.04],
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        mt_srand(20261030);
        $io  = new SymfonyStyle($input, $output);
        $now = new DateTimeImmutable('now');

        $this->entityManager->createQuery('DELETE FROM ' . RouteDataRecord::class . ' r WHERE r.routeData IN (SELECT d FROM ' . RouteData::class . ' d WHERE d.name LIKE :prefix)')
            ->setParameter('prefix', 'demo\_%')->execute();
        $this->entityManager->createQuery('DELETE FROM ' . RouteData::class . ' d WHERE d.name LIKE :prefix')
            ->setParameter('prefix', 'demo\_%')->execute();

        $records = 0;
        foreach (self::ROUTES as $name => [$method, $path, $time, $queries, $memory, $perDay, $errors]) {
            $route = (new RouteData())->setEnv('dev')->setName($name)->setHttpMethod($method)->setCreatedAt($now->modify('-7 days'));
            if ($name === 'demo_product_index') {
                $route->markAsReviewed(true, false, 'demo', $now->modify('-1 day'));
            }
            $this->entityManager->persist($route);
            $last = null;
            for ($day = 6; $day >= 0; --$day) {
                $hits = (int) round($perDay * (in_array((int) $now->modify("-{$day} days")->format('w'), [0, 6], true) ? 0.45 : 1.0));
                for ($i = 0; $i < $hits; ++$i) {
                    // Office-hours traffic: most hits between 08:00 and 19:00.
                    $hour = mt_rand(0, 9) < 8 ? mt_rand(8, 19) : mt_rand(0, 23);
                    $at   = $now->modify("-{$day} days")->setTime($hour, mt_rand(0, 59), mt_rand(0, 59));
                    if ($at > $now) {
                        continue;
                    }
                    $jitter = 0.6 + mt_rand(0, 100) / 100 * (mt_rand(0, 19) === 0 ? 2.4 : 0.9);
                    $status = mt_rand(0, 999) < (int) ($errors * 1000) ? (mt_rand(0, 1) === 0 ? 500 : 404) : 200;
                    $record = (new RouteDataRecord())
                        ->setRouteData($route)
                        ->setAccessedAt($at)
                        ->setStatusCode($status)
                        ->setResponseTime(round($time * $jitter, 4))
                        ->setTotalQueries(max(1, (int) round($queries * $jitter)))
                        ->setQueryTime(round($time * $jitter * 0.55, 4))
                        ->setMemoryUsage((int) round($memory * (0.9 + mt_rand(0, 30) / 100) * 1048576))
                        ->setRoutePath($path)
                        ->setReferer(mt_rand(0, 2) === 0 ? 'https://example.com/' : null)
                        ->setUserIdentifier(mt_rand(0, 3) === 0 ? 'alice@example.com' : null);
                    $this->entityManager->persist($record);
                    $last = max($last ?? $at, $at);
                    ++$records;
                }
            }
            $route->setLastAccessedAt($last ?? $now);
        }
        $this->entityManager->flush();

        $io->success(sprintf('Seeded %d demo routes and %d access records.', count(self::ROUTES), $records));

        return Command::SUCCESS;
    }
}
