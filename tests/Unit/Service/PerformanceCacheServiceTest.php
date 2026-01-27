<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class PerformanceCacheServiceTest extends TestCase
{
    private CacheItemPoolInterface|MockObject $cachePool;
    private CacheItemInterface|MockObject $cacheItem;

    protected function setUp(): void
    {
        $this->cachePool = $this->createMock(CacheItemPoolInterface::class);
        $this->cacheItem = $this->createMock(CacheItemInterface::class);
    }

    public function testGetCachedStatisticsReturnsNullWhenCachePoolIsNull(): void
    {
        $service = new PerformanceCacheService(null);
        
        $this->assertNull($service->getCachedStatistics('dev'));
    }

    public function testGetCachedStatisticsReturnsNullWhenNotCached(): void
    {
        $this->cacheItem->method('isHit')->willReturn(false);
        $this->cachePool->method('getItem')->willReturn($this->cacheItem);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertNull($service->getCachedStatistics('dev'));
    }

    public function testGetCachedStatisticsReturnsDataWhenCached(): void
    {
        $cachedData = ['total' => 10, 'avg' => 0.5];
        
        $this->cacheItem->method('isHit')->willReturn(true);
        $this->cacheItem->method('get')->willReturn($cachedData);
        $this->cachePool->method('getItem')->willReturn($this->cacheItem);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertSame($cachedData, $service->getCachedStatistics('dev'));
    }

    public function testCacheStatisticsReturnsFalseWhenCachePoolIsNull(): void
    {
        $service = new PerformanceCacheService(null);
        
        $this->assertFalse($service->cacheStatistics('dev', ['total' => 10]));
    }

    public function testCacheStatisticsSavesData(): void
    {
        $statistics = ['total' => 10, 'avg' => 0.5];
        
        $this->cacheItem->expects($this->once())
            ->method('set')
            ->with($statistics);
        $this->cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(3600);
        $this->cachePool->method('getItem')->willReturn($this->cacheItem);
        $this->cachePool->expects($this->once())
            ->method('save')
            ->with($this->cacheItem)
            ->willReturn(true);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertTrue($service->cacheStatistics('dev', $statistics));
    }

    public function testCacheStatisticsUsesCustomTtl(): void
    {
        $statistics = ['total' => 10];
        $customTtl = 7200;
        
        $this->cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with($customTtl);
        $this->cachePool->method('getItem')->willReturn($this->cacheItem);
        $this->cachePool->method('save')->willReturn(true);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertTrue($service->cacheStatistics('dev', $statistics, $customTtl));
    }

    public function testGetCachedEnvironmentsReturnsNullWhenCachePoolIsNull(): void
    {
        $service = new PerformanceCacheService(null);
        
        $this->assertNull($service->getCachedEnvironments());
    }

    public function testGetCachedEnvironmentsReturnsDataWhenCached(): void
    {
        $cachedEnvironments = ['dev', 'test', 'prod'];
        
        $this->cacheItem->method('isHit')->willReturn(true);
        $this->cacheItem->method('get')->willReturn($cachedEnvironments);
        $this->cachePool->method('getItem')->willReturn($this->cacheItem);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertSame($cachedEnvironments, $service->getCachedEnvironments());
    }

    public function testCacheEnvironmentsSavesData(): void
    {
        $environments = ['dev', 'test'];
        
        $this->cacheItem->expects($this->once())
            ->method('set')
            ->with($environments);
        $this->cachePool->method('getItem')->willReturn($this->cacheItem);
        $this->cachePool->expects($this->once())
            ->method('save')
            ->willReturn(true);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertTrue($service->cacheEnvironments($environments));
    }

    public function testInvalidateStatisticsReturnsFalseWhenCachePoolIsNull(): void
    {
        $service = new PerformanceCacheService(null);
        
        $this->assertFalse($service->invalidateStatistics('dev'));
    }

    public function testInvalidateStatisticsDeletesItem(): void
    {
        $this->cachePool->expects($this->once())
            ->method('deleteItem')
            ->with('nowo_performance_stats_dev')
            ->willReturn(true);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertTrue($service->invalidateStatistics('dev'));
    }

    public function testInvalidateEnvironmentsReturnsFalseWhenCachePoolIsNull(): void
    {
        $service = new PerformanceCacheService(null);
        
        $this->assertFalse($service->invalidateEnvironments());
    }

    public function testInvalidateEnvironmentsDeletesItem(): void
    {
        $this->cachePool->expects($this->once())
            ->method('deleteItem')
            ->with('nowo_performance_environments')
            ->willReturn(true);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertTrue($service->invalidateEnvironments());
    }

    public function testInvalidateAllCallsInvalidateEnvironments(): void
    {
        $this->cachePool->expects($this->once())
            ->method('deleteItem')
            ->with('nowo_performance_environments')
            ->willReturn(true);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertTrue($service->invalidateAll());
    }

    public function testClearStatisticsIsAliasForInvalidateStatistics(): void
    {
        $this->cachePool->expects($this->once())
            ->method('deleteItem')
            ->with('nowo_performance_stats_dev')
            ->willReturn(true);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertTrue($service->clearStatistics('dev'));
    }

    public function testClearEnvironmentsIsAliasForInvalidateEnvironments(): void
    {
        $this->cachePool->expects($this->once())
            ->method('deleteItem')
            ->with('nowo_performance_environments')
            ->willReturn(true);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertTrue($service->clearEnvironments());
    }

    public function testConstructorHandlesStringParameter(): void
    {
        // When a string is passed instead of CacheItemPoolInterface, it should be treated as null
        $service = new PerformanceCacheService('cache.app');
        
        $this->assertNull($service->getCachedStatistics('dev'));
        $this->assertFalse($service->cacheStatistics('dev', ['test' => 'data']));
    }

    public function testGetCachedValueReturnsNullWhenCachePoolIsNull(): void
    {
        $service = new PerformanceCacheService(null);
        
        $this->assertNull($service->getCachedValue('test_key'));
    }

    public function testGetCachedValueReturnsNullWhenNotCached(): void
    {
        $this->cacheItem->method('isHit')->willReturn(false);
        $this->cachePool->method('getItem')->willReturn($this->cacheItem);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertNull($service->getCachedValue('test_key'));
    }

    public function testGetCachedValueReturnsDataWhenCached(): void
    {
        $cachedData = ['test' => 'value', 'number' => 42];
        
        $this->cacheItem->method('isHit')->willReturn(true);
        $this->cacheItem->method('get')->willReturn($cachedData);
        $this->cachePool->method('getItem')
            ->with('nowo_performance_test_key')
            ->willReturn($this->cacheItem);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertSame($cachedData, $service->getCachedValue('test_key'));
    }

    public function testGetCachedValueWithCompositeKey(): void
    {
        $cachedData = true;
        
        $this->cacheItem->method('isHit')->willReturn(true);
        $this->cacheItem->method('get')->willReturn($cachedData);
        $this->cachePool->method('getItem')
            ->with('nowo_performance_table_exists_default_routes_data')
            ->willReturn($this->cacheItem);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertTrue($service->getCachedValue('table_exists_default_routes_data'));
    }

    public function testCacheValueReturnsFalseWhenCachePoolIsNull(): void
    {
        $service = new PerformanceCacheService(null);
        
        $this->assertFalse($service->cacheValue('test_key', 'test_value'));
    }

    public function testCacheValueSavesData(): void
    {
        $value = ['data' => 'test'];
        
        $this->cacheItem->expects($this->once())
            ->method('set')
            ->with($value);
        $this->cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(3600);
        $this->cachePool->method('getItem')
            ->with('nowo_performance_test_key')
            ->willReturn($this->cacheItem);
        $this->cachePool->expects($this->once())
            ->method('save')
            ->with($this->cacheItem)
            ->willReturn(true);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertTrue($service->cacheValue('test_key', $value));
    }

    public function testCacheValueUsesCustomTtl(): void
    {
        $value = true;
        $customTtl = 300; // 5 minutes
        
        $this->cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with($customTtl);
        $this->cachePool->method('getItem')
            ->with('nowo_performance_table_exists')
            ->willReturn($this->cacheItem);
        $this->cachePool->method('save')->willReturn(true);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertTrue($service->cacheValue('table_exists', $value, $customTtl));
    }

    public function testCacheValueWithCompositeKey(): void
    {
        $value = false;
        
        $this->cacheItem->expects($this->once())
            ->method('set')
            ->with($value);
        $this->cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(300);
        $this->cachePool->method('getItem')
            ->with('nowo_performance_table_exists_default_routes_data')
            ->willReturn($this->cacheItem);
        $this->cachePool->method('save')->willReturn(true);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertTrue($service->cacheValue('table_exists_default_routes_data', $value, 300));
    }

    public function testInvalidateValueReturnsFalseWhenCachePoolIsNull(): void
    {
        $service = new PerformanceCacheService(null);
        
        $this->assertFalse($service->invalidateValue('test_key'));
    }

    public function testInvalidateValueDeletesItem(): void
    {
        $this->cachePool->expects($this->once())
            ->method('deleteItem')
            ->with('nowo_performance_test_key')
            ->willReturn(true);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertTrue($service->invalidateValue('test_key'));
    }

    public function testInvalidateValueWithCompositeKey(): void
    {
        $this->cachePool->expects($this->once())
            ->method('deleteItem')
            ->with('nowo_performance_table_exists_default_routes_data')
            ->willReturn(true);
        
        $service = new PerformanceCacheService($this->cachePool);
        
        $this->assertTrue($service->invalidateValue('table_exists_default_routes_data'));
    }

    public function testGetCachedValueCachesDifferentTypes(): void
    {
        // Test with boolean
        $this->cacheItem->method('isHit')->willReturn(true);
        $this->cacheItem->method('get')->willReturn(true);
        $this->cachePool->method('getItem')
            ->with('nowo_performance_bool_key')
            ->willReturn($this->cacheItem);
        
        $service = new PerformanceCacheService($this->cachePool);
        $this->assertTrue($service->getCachedValue('bool_key'));
        
        // Test with integer
        $this->cacheItem->method('get')->willReturn(42);
        $this->cachePool->method('getItem')
            ->with('nowo_performance_int_key')
            ->willReturn($this->cacheItem);
        
        $this->assertSame(42, $service->getCachedValue('int_key'));
        
        // Test with string
        $this->cacheItem->method('get')->willReturn('test string');
        $this->cachePool->method('getItem')
            ->with('nowo_performance_string_key')
            ->willReturn($this->cacheItem);
        
        $this->assertSame('test string', $service->getCachedValue('string_key'));
    }
}
