<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly PerformanceMetricsService $metricsService
    ) {
    }

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        $env = $this->getParameter('kernel.environment');
        
        try {
            $routes = $this->metricsService->getRoutesByEnvironment($env);
            $worstRoutes = $this->metricsService->getWorstPerformingRoutes($env, 10);
        } catch (\Exception $e) {
            // If metrics table doesn't exist yet, show empty state
            $routes = [];
            $worstRoutes = [];
        }

        return $this->render('home/index.html.twig', [
            'routes' => $routes,
            'worstRoutes' => $worstRoutes,
            'env' => $env,
        ]);
    }
}
