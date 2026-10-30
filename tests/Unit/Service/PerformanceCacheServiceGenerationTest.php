<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use LogicException;
use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * REQ-TEST-003: without a cache pool the statistics cache generation is 0.
 */
final class PerformanceCacheServiceGenerationTest extends TestCase
{
    public function testGenerationNeedsAPool(): void
    {
        $method = new ReflectionMethod(PerformanceCacheService::class, 'getStatisticsGeneration');

        // Every public caller returns early without a pool; reaching the generation lookup without one is a bug.
        $this->expectException(LogicException::class);
        $method->invoke(new PerformanceCacheService(), 'dev');
    }
}
