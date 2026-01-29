<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RouteDataRepository ranking methods (now return null as ranking is computed from aggregates).
 */
final class RouteDataRepositoryRankingMethodsTest extends TestCase
{
    private ManagerRegistry $registry;
    private RouteDataRepository $repository;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->repository = new RouteDataRepository($this->registry);
    }

    public function testGetRankingByRequestTimeReturnsNull(): void
    {
        $result = $this->repository->getRankingByRequestTime('app_home', 'dev');

        $this->assertNull($result);
    }

    public function testGetRankingByRequestTimeWithRouteDataObjectReturnsNull(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $result = $this->repository->getRankingByRequestTime($routeData, 'dev');

        $this->assertNull($result);
    }

    public function testGetRankingByQueryCountReturnsNull(): void
    {
        $result = $this->repository->getRankingByQueryCount('app_home', 'dev');

        $this->assertNull($result);
    }

    public function testGetRankingByQueryCountWithRouteDataObjectReturnsNull(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $result = $this->repository->getRankingByQueryCount($routeData, 'dev');

        $this->assertNull($result);
    }

    public function testGetRankingByRequestTimeWithEmptyEnvReturnsNull(): void
    {
        $result = $this->repository->getRankingByRequestTime('app_home', '');

        $this->assertNull($result);
    }

    public function testGetRankingByQueryCountWithEmptyEnvReturnsNull(): void
    {
        $result = $this->repository->getRankingByQueryCount('app_home', '');

        $this->assertNull($result);
    }
}
