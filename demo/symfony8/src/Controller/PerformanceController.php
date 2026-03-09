<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function count;

/**
 * Controller for displaying performance metrics.
 */
class PerformanceController extends AbstractController
{
    public function __construct(
        private readonly RouteDataRepository $routeDataRepository
    ) {
    }

    #[Route('/performance', name: 'app_performance')]
    public function index(): Response
    {
        $env    = $this->getParameter('kernel.environment');
        $routes = $this->routeDataRepository->findBy(
            ['env' => $env],
            ['requestTime' => 'DESC'],
        );

        $avgRequestTime = $this->calculateAverage($routes, 'requestTime');
        $maxRequestTime = $this->getMax($routes, 'requestTime');

        $stats = [
            'total_routes'     => count($routes),
            'total_queries'    => array_sum(array_map(static fn ($r) => $r->getTotalQueries() ?? 0, $routes)),
            'avg_request_time' => $avgRequestTime ?? 0.0,
            'avg_query_time'   => $this->calculateAverage($routes, 'queryTime') ?? 0.0,
            'max_request_time' => $maxRequestTime ?? 0.0,
            'max_query_time'   => $this->getMax($routes, 'queryTime') ?? 0.0,
            'max_queries'      => $this->getMax($routes, 'totalQueries') ?? 0,
        ];

        return $this->render('performance/index.html.twig', [
            'routes'      => $routes,
            'stats'       => $stats,
            'environment' => $env,
        ]);
    }

    private function calculateAverage(array $routes, string $field): ?float
    {
        $values = array_filter(array_map(fn ($r) => $this->getFieldValue($r, $field), $routes));
        if (empty($values)) {
            return null;
        }

        return array_sum($values) / count($values);
    }

    private function getMax(array $routes, string $field): ?float
    {
        $values = array_filter(array_map(fn ($r) => $this->getFieldValue($r, $field), $routes));

        return empty($values) ? null : max($values);
    }

    private function getFieldValue($route, string $field): ?float
    {
        return match ($field) {
            'requestTime'  => $route->getRequestTime(),
            'queryTime'    => $route->getQueryTime(),
            'totalQueries' => (float) ($route->getTotalQueries() ?? 0),
            default        => null,
        };
    }
}
