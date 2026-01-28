<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Advanced tests for PerformanceCacheService edge cases.
 */
final class PerformanceCacheServiceAdvancedTest extends TestCase
{
    private CacheItemPoolInterface|MockObject $cachePool;
    private PerformanceCacheService $service;

    protected function setUp(): void
    {
        $this->cachePool = $this->createMock(CacheItemPoolInterface::class);
        $this->service = new PerformanceCacheService($this->cachePool);
    }

    public function testGetCachedStatisticsWithNullCachePool(): void
    {
        $service = new PerformanceCacheService(null);

        $result = $service->getCachedStatistics('dev');

        $this->assertNull($result);
    }

    public function testGetCachedStatisticsWithCacheMiss(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);

        $this->cachePool
            ->method('getItem')
            ->with('nowo_performance_stats_dev')
            ->willReturn($item);

        $result = $this->service->getCachedStatistics('dev');

        $this->assertNull($result);
    }

    public function testGetCachedStatisticsWithCacheHit(): void
    {
        $statistics = ['total_routes' => 10, 'avg_time' => 0.5];
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn($statistics);

        $this->cachePool
            ->method('getItem')
            ->with('nowo_performance_stats_dev')
            ->willReturn($item);

        $result = $this->service->getCachedStatistics('dev');

        $this->assertSame($statistics, $result);
    }

    public function testCacheStatisticsWithNullCachePool(): void
    {
        $service = new PerformanceCacheService(null);

        $result = $service->cacheStatistics('dev', ['total' => 10]);

        $this->assertFalse($result);
    }

    public function testCacheStatisticsWithCustomTtl(): void
    {
        $statistics = ['total_routes' => 10];
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())
            ->method('set')
            ->with($statistics);
        $item->expects($this->once())
            ->method('expiresAfter')
            ->with(7200);

        $this->cachePool
            ->method('getItem')
            ->with('nowo_performance_stats_dev')
            ->willReturn($item);

        $this->cachePool
            ->method('save')
            ->with($item)
            ->willReturn(true);

        $result = $this->service->cacheStatistics('dev', $statistics, 7200);

        $this->assertTrue($result);
    }

    public function testCacheStatisticsWithDefaultTtl(): void
    {
        $statistics = ['total_routes' => 10];
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())
            ->method('expiresAfter')
            ->with(3600); // DEFAULT_TTL

        $this->cachePool
            ->method('getItem')
            ->willReturn($item);

        $this->cachePool
            ->method('save')
            ->willReturn(true);

        $result = $this->service->cacheStatistics('dev', $statistics);

        $this->assertTrue($result);
    }

    public function testCacheStatisticsWithSaveFailure(): void
    {
        $statistics = ['total_routes' => 10];
        $item = $this->createMock(CacheItemInterface::class);

        $this->cachePool
            ->method('getItem')
            ->willReturn($item);

        $this->cachePool
            ->method('save')
            ->willReturn(false);

        $result = $this->service->cacheStatistics('dev', $statistics);

        $this->assertFalse($result);
    }

    public function testGetCachedEnvironmentsWithNullCachePool(): void
    {
        $service = new PerformanceCacheService(null);

        $result = $service->getCachedEnvironments();

        $this->assertNull($result);
    }

    public function testGetCachedEnvironmentsWithCacheMiss(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);

        $this->cachePool
            ->method('getItem')
            ->with('nowo_performance_environments')
            ->willReturn($item);

        $result = $this->service->getCachedEnvironments();

        $this->assertNull($result);
    }

    public function testGetCachedEnvironmentsWithCacheHit(): void
    {
        $environments = ['dev', 'test', 'prod'];
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn($environments);

        $this->cachePool
            ->method('getItem')
            ->with('nowo_performance_environments')
            ->willReturn($item);

        $result = $this->service->getCachedEnvironments();

        $this->assertSame($environments, $result);
    }

    public function testCacheEnvironmentsWithNullCachePool(): void
    {
        $service = new PerformanceCacheService(null);

        $result = $service->cacheEnvironments(['dev', 'test']);

        $this->assertFalse($result);
    }

    public function testCacheEnvironmentsWithCustomTtl(): void
    {
        $environments = ['dev', 'test', 'prod'];
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())
            ->method('expiresAfter')
            ->with(1800);

        $this->cachePool
            ->method('getItem')
            ->willReturn($item);

        $this->cachePool
            ->method('save')
            ->willReturn(true);

        $result = $this->service->cacheEnvironments($environments, 1800);

        $this->assertTrue($result);
    }

    public function testInvalidateStatisticsWithNullCachePool(): void
    {
        $service = new PerformanceCacheService(null);

        $result = $service->invalidateStatistics('dev');

        $this->assertFalse($result);
    }

    public function testInvalidateStatisticsWithSuccess(): void
    {
        $this->cachePool
            ->method('deleteItem')
            ->with('nowo_performance_stats_dev')
            ->willReturn(true);

        $result = $this->service->invalidateStatistics('dev');

        $this->assertTrue($result);
    }

    public function testInvalidateStatisticsWithFailure(): void
    {
        $this->cachePool
            ->method('deleteItem')
            ->willReturn(false);

        $result = $this->service->invalidateStatistics('dev');

        $this->assertFalse($result);
    }

    public function testInvalidateEnvironmentsWithNullCachePool(): void
    {
        $service = new PerformanceCacheService(null);

        $result = $service->invalidateEnvironments();

        $this->assertFalse($result);
    }

    public function testInvalidateEnvironmentsWithSuccess(): void
    {
        $this->cachePool
            ->method('deleteItem')
            ->with('nowo_performance_environments')
            ->willReturn(true);

        $result = $this->service->invalidateEnvironments();

        $this->assertTrue($result);
    }

    public function testInvalidateAllWithNullCachePool(): void
    {
        $service = new PerformanceCacheService(null);

        $result = $service->invalidateAll();

        $this->assertFalse($result);
    }

    public function testInvalidateAllWithSuccess(): void
    {
        $this->cachePool
            ->method('deleteItem')
            ->with('nowo_performance_environments')
            ->willReturn(true);

        $result = $this->service->invalidateAll();

        $this->assertTrue($result);
    }

    public function testClearStatisticsCallsInvalidateStatistics(): void
    {
        $this->cachePool
            ->method('deleteItem')
            ->with('nowo_performance_stats_dev')
            ->willReturn(true);

        $result = $this->service->clearStatistics('dev');

        $this->assertTrue($result);
    }

    public function testClearEnvironmentsCallsInvalidateEnvironments(): void
    {
        $this->cachePool
            ->method('deleteItem')
            ->with('nowo_performance_environments')
            ->willReturn(true);

        $result = $this->service->clearEnvironments();

        $this->assertTrue($result);
    }

    public function testGetCachedValueWithNullCachePool(): void
    {
        $service = new PerformanceCacheService(null);

        $result = $service->getCachedValue('test_key');

        $this->assertNull($result);
    }

    public function testGetCachedValueWithCacheMiss(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);

        $this->cachePool
            ->method('getItem')
            ->with('nowo_performance_test_key')
            ->willReturn($item);

        $result = $this->service->getCachedValue('test_key');

        $this->assertNull($result);
    }

    public function testGetCachedValueWithCacheHit(): void
    {
        $value = ['data' => 'test'];
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn($value);

        $this->cachePool
            ->method('getItem')
            ->with('nowo_performance_test_key')
            ->willReturn($item);

        $result = $this->service->getCachedValue('test_key');

        $this->assertSame($value, $result);
    }

    public function testCacheValueWithNullCachePool(): void
    {
        $service = new PerformanceCacheService(null);

        $result = $service->cacheValue('test_key', 'test_value');

        $this->assertFalse($result);
    }

    public function testCacheValueWithCustomTtl(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())
            ->method('set')
            ->with('test_value');
        $item->expects($this->once())
            ->method('expiresAfter')
            ->with(600);

        $this->cachePool
            ->method('getItem')
            ->with('nowo_performance_test_key')
            ->willReturn($item);

        $this->cachePool
            ->method('save')
            ->willReturn(true);

        $result = $this->service->cacheValue('test_key', 'test_value', 600);

        $this->assertTrue($result);
    }

    public function testCacheValueWithDefaultTtl(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())
            ->method('expiresAfter')
            ->with(3600); // DEFAULT_TTL

        $this->cachePool
            ->method('getItem')
            ->willReturn($item);

        $this->cachePool
            ->method('save')
            ->willReturn(true);

        $result = $this->service->cacheValue('test_key', 'test_value');

        $this->assertTrue($result);
    }

    public function testCacheValueWithDifferentTypes(): void
    {
        $item = $this->createMock(CacheItemInterface::class);

        $this->cachePool
            ->method('getItem')
            ->willReturn($item);

        $this->cachePool
            ->method('save')
            ->willReturn(true);

        // Test with string
        $item->expects($this->at(0))
            ->method('set')
            ->with('string_value');
        $this->service->cacheValue('key1', 'string_value');

        // Test with array
        $item->expects($this->at(1))
            ->method('set')
            ->with(['array' => 'value']);
        $this->service->cacheValue('key2', ['array' => 'value']);

        // Test with integer
        $item->expects($this->at(2))
            ->method('set')
            ->with(123);
        $this->service->cacheValue('key3', 123);

        // Test with boolean
        $item->expects($this->at(3))
            ->method('set')
            ->with(true);
        $this->service->cacheValue('key4', true);
    }

    public function testInvalidateValueWithNullCachePool(): void
    {
        $service = new PerformanceCacheService(null);

        $result = $service->invalidateValue('test_key');

        $this->assertFalse($result);
    }

    public function testInvalidateValueWithSuccess(): void
    {
        $this->cachePool
            ->method('deleteItem')
            ->with('nowo_performance_test_key')
            ->willReturn(true);

        $result = $this->service->invalidateValue('test_key');

        $this->assertTrue($result);
    }

    public function testInvalidateValueWithFailure(): void
    {
        $this->cachePool
            ->method('deleteItem')
            ->willReturn(false);

        $result = $this->service->invalidateValue('test_key');

        $this->assertFalse($result);
    }

    public function testConstructorWithStringCachePool(): void
    {
        // When cache service is not available, a string might be passed
        $service = new PerformanceCacheService('cache.app');

        $result = $service->getCachedStatistics('dev');

        $this->assertNull($result);
    }

    public function testCacheStatisticsWithEmptyArray(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())
            ->method('set')
            ->with([]);

        $this->cachePool
            ->method('getItem')
            ->willReturn($item);

        $this->cachePool
            ->method('save')
            ->willReturn(true);

        $result = $this->service->cacheStatistics('dev', []);

        $this->assertTrue($result);
    }

    public function testCacheEnvironmentsWithEmptyArray(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())
            ->method('set')
            ->with([]);

        $this->cachePool
            ->method('getItem')
            ->willReturn($item);

        $this->cachePool
            ->method('save')
            ->willReturn(true);

        $result = $this->service->cacheEnvironments([]);

        $this->assertTrue($result);
    }

    public function testCacheValueWithNullValue(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())
            ->method('set')
            ->with(null);

        $this->cachePool
            ->method('getItem')
            ->willReturn($item);

        $this->cachePool
            ->method('save')
            ->willReturn(true);

        $result = $this->service->cacheValue('test_key', null);

        $this->assertTrue($result);
    }

    public function testGetCachedValueWithComplexData(): void
    {
        $complexData = [
            'routes' => [
                ['name' => 'app_home', 'time' => 0.5],
                ['name' => 'app_about', 'time' => 0.3],
            ],
            'stats' => [
                'total' => 2,
                'avg' => 0.4,
            ],
        ];

        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn($complexData);

        $this->cachePool
            ->method('getItem')
            ->with('nowo_performance_complex_key')
            ->willReturn($item);

        $result = $this->service->getCachedValue('complex_key');

        $this->assertSame($complexData, $result);
    }
}
