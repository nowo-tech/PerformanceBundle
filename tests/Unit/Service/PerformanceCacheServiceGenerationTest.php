<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * REQ-TEST-003: without a cache pool the statistics cache generation is 0.
 */
final class PerformanceCacheServiceGenerationTest extends TestCase
{
    public function testGenerationIsZeroWithoutPool(): void
    {
        $method = new ReflectionMethod(PerformanceCacheService::class, 'getStatisticsGeneration');

        self::assertSame(0, $method->invoke(new PerformanceCacheService(), 'dev'));
    }
}
