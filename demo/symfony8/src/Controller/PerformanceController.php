<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Legacy demo URL: metrics now live on the bundle dashboard (RouteData no longer stores
 * request/query times; they are aggregated from access records), so /performance
 * redirects there instead of reading removed RouteData fields.
 */
class PerformanceController extends AbstractController
{
    #[Route('/performance', name: 'app_performance')]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('nowo_performance.index');
    }
}
