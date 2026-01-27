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
}
