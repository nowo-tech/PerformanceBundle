<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionMethod;

/**
 * Tests for PerformanceController::getEmptyStats() method.
 */
final class PerformanceControllerGetEmptyStatsTest extends TestCase
{
    private PerformanceController $controller;
    private PerformanceMetricsService|MockObject $metricsService;
    private RouteDataRepository|MockObject $repository;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->repository = $this->createMock(RouteDataRepository::class);
        
        $this->metricsService
            ->method('getRepository')
            ->willReturn($this->repository);

        $this->controller = new PerformanceController(
            $this->metricsService,
            null,
            true,
            [],
            'bootstrap',
            null,
            null,
            null,
            false,
            false,
            null,
            0.5,
            1.0,
            20,
            50,
            20.0,
            50.0,
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            0,
            [200, 404, 500, 503],
            null,
            false,
            true,
            ['dev', 'test'],
            'default',
            true,
            true,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            null,
            null
        );
    }

    public function testGetEmptyStatsReturnsCorrectStructure(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getEmptyStats');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('label', $result);
        $this->assertArrayHasKey('unit', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('mean', $result);
        $this->assertArrayHasKey('median', $result);
        $this->assertArrayHasKey('mode', $result);
        $this->assertArrayHasKey('std_dev', $result);
        $this->assertArrayHasKey('min', $result);
        $this->assertArrayHasKey('max', $result);
        $this->assertArrayHasKey('range', $result);
        $this->assertArrayHasKey('percentiles', $result);
        $this->assertArrayHasKey('outliers_count', $result);
        $this->assertArrayHasKey('outliers', $result);
        $this->assertArrayHasKey('distribution', $result);
        $this->assertArrayHasKey('bucket_labels', $result);
    }

    public function testGetEmptyStatsReturnsEmptyStringValues(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getEmptyStats');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller);

        $this->assertSame('', $result['label']);
        $this->assertSame('', $result['unit']);
    }

    public function testGetEmptyStatsReturnsZeroNumericValues(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getEmptyStats');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller);

        $this->assertSame(0, $result['count']);
        $this->assertSame(0.0, $result['mean']);
        $this->assertSame(0.0, $result['median']);
        $this->assertSame(0.0, $result['mode']);
        $this->assertSame(0.0, $result['std_dev']);
        $this->assertSame(0.0, $result['min']);
        $this->assertSame(0.0, $result['max']);
        $this->assertSame(0.0, $result['range']);
        $this->assertSame(0, $result['outliers_count']);
    }

    public function testGetEmptyStatsReturnsEmptyArrays(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getEmptyStats');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller);

        $this->assertIsArray($result['percentiles']);
        $this->assertEmpty($result['percentiles']);
        $this->assertIsArray($result['outliers']);
        $this->assertEmpty($result['outliers']);
        $this->assertIsArray($result['distribution']);
        $this->assertEmpty($result['distribution']);
        $this->assertIsArray($result['bucket_labels']);
        $this->assertEmpty($result['bucket_labels']);
    }

    public function testGetEmptyStatsReturnsConsistentStructure(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getEmptyStats');
        $method->setAccessible(true);

        $result1 = $method->invoke($this->controller);
        $result2 = $method->invoke($this->controller);

        $this->assertSame($result1, $result2);
    }

    public function testGetEmptyStatsHasCorrectTypes(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getEmptyStats');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller);

        $this->assertIsString($result['label']);
        $this->assertIsString($result['unit']);
        $this->assertIsInt($result['count']);
        $this->assertIsFloat($result['mean']);
        $this->assertIsFloat($result['median']);
        $this->assertIsFloat($result['mode']);
        $this->assertIsFloat($result['std_dev']);
        $this->assertIsFloat($result['min']);
        $this->assertIsFloat($result['max']);
        $this->assertIsFloat($result['range']);
        $this->assertIsInt($result['outliers_count']);
        $this->assertIsArray($result['percentiles']);
        $this->assertIsArray($result['outliers']);
        $this->assertIsArray($result['distribution']);
        $this->assertIsArray($result['bucket_labels']);
    }

    public function testGetEmptyStatsCanBeUsedAsTemplate(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('getEmptyStats');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller);

        // Verify it can be used as a template (all keys present)
        $expectedKeys = [
            'label',
            'unit',
            'count',
            'mean',
            'median',
            'mode',
            'std_dev',
            'min',
            'max',
            'range',
            'percentiles',
            'outliers_count',
            'outliers',
            'distribution',
            'bucket_labels',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Key '{$key}' should be present in empty stats");
        }

        $this->assertCount(count($expectedKeys), $result, 'Empty stats should have exactly the expected number of keys');
    }
}
