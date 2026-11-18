<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Nowo\PerformanceBundle\Tests\Support\OrmTestSupport;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function hrtime;

/**
 * The pause before a write-unit replay is short and bounded (20 ms to 50 ms × attempt).
 */
final class PerformanceMetricsServicePauseTest extends TestCase
{
    public function testPauseBeforeReplayIsShortAndBounded(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn(OrmTestSupport::entityManager($this, null, false));
        $service = new PerformanceMetricsService($registry, 'default');

        $start = hrtime(true);
        (new ReflectionMethod($service, 'pauseBeforeReplay'))->invoke($service, 1);
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        self::assertGreaterThanOrEqual(19.0, $elapsedMs);
        self::assertLessThan(500.0, $elapsedMs);
        self::assertSame(3, PerformanceMetricsService::MAX_WRITE_ATTEMPTS);
    }
}
