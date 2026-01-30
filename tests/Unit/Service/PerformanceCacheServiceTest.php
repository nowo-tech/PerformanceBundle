<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class PerformanceCacheServiceTest extends TestCase
{
    public function testConstructorWithNullPool(): void
    {
        $service = new PerformanceCacheService(null);

        $this->assertNull($service->getCachedStatistics('dev'));
        $this->assertNull($service->getCachedEnvironments());
        $this->assertFalse($service->cacheStatistics('dev', ['foo' => 1]));
        $this->assertFalse($service->cacheEnvironments(['dev', 'prod']));
        $this->assertFalse($service->invalidateStatistics('dev'));
        $this->assertFalse($service->invalidateEnvironments());
        $this->assertFalse($service->invalidateAll());
        $this->assertFalse($service->clearStatistics('dev'));
        $this->assertFalse($service->clearEnvironments());
        $this->assertNull($service->getCachedValue('key'));
        $this->assertFalse($service->cacheValue('key', 'val'));
        $this->assertFalse($service->invalidateValue('key'));
    }

    public function testConstructorWithStringPoolTreatedAsNull(): void
    {
        $service = new PerformanceCacheService('cache.app');

        $this->assertNull($service->getCachedStatistics('dev'));
        $this->assertFalse($service->cacheStatistics('dev', ['x' => 1]));
    }

    public function testGetCachedStatisticsReturnsNullOnMiss(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);

        $service = new PerformanceCacheService($pool);

        $this->assertNull($service->getCachedStatistics('dev'));
    }

    public function testGetCachedStatisticsReturnsDataOnHit(): void
    {
        $data = ['total_routes' => 10, 'avg_time' => 0.5];
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn($data);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);

        $service = new PerformanceCacheService($pool);

        $this->assertSame($data, $service->getCachedStatistics('dev'));
    }

    public function testCacheStatistics(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('set')->with(['a' => 1]);
        $item->expects($this->once())->method('expiresAfter')->with(3600);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->expects($this->once())->method('save')->with($item)->willReturn(true);

        $service = new PerformanceCacheService($pool);

        $this->assertTrue($service->cacheStatistics('dev', ['a' => 1]));
    }

    public function testCacheStatisticsWithCustomTtl(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('expiresAfter')->with(120);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->method('save')->willReturn(true);

        $service = new PerformanceCacheService($pool);

        $this->assertTrue($service->cacheStatistics('prod', ['x' => 1], 120));
    }

    public function testGetCachedEnvironmentsReturnsNullOnMiss(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);

        $service = new PerformanceCacheService($pool);

        $this->assertNull($service->getCachedEnvironments());
    }

    public function testCacheEnvironmentsAndGetCachedEnvironments(): void
    {
        $envs = ['dev', 'prod'];
        $hitItem = $this->createMock(CacheItemInterface::class);
        $hitItem->method('isHit')->willReturn(true);
        $hitItem->method('get')->willReturn($envs);

        $setItem = $this->createMock(CacheItemInterface::class);
        $setItem->expects($this->once())->method('set')->with($envs);
        $setItem->expects($this->once())->method('expiresAfter')->with(3600);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->exactly(2))->method('getItem')->willReturnOnConsecutiveCalls($setItem, $hitItem);
        $pool->expects($this->once())->method('save')->willReturn(true);

        $service = new PerformanceCacheService($pool);

        $this->assertTrue($service->cacheEnvironments($envs));
        $this->assertSame($envs, $service->getCachedEnvironments());
    }

    public function testInvalidateStatistics(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('deleteItem')->with($this->stringContains('stats_dev'))->willReturn(true);

        $service = new PerformanceCacheService($pool);

        $this->assertTrue($service->invalidateStatistics('dev'));
    }

    public function testClearStatisticsDelegatesToInvalidateStatistics(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('deleteItem')->willReturn(true);

        $service = new PerformanceCacheService($pool);

        $this->assertTrue($service->clearStatistics('prod'));
    }

    public function testInvalidateEnvironments(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('deleteItem')->with($this->stringContains('environments'))->willReturn(true);

        $service = new PerformanceCacheService($pool);

        $this->assertTrue($service->invalidateEnvironments());
    }

    public function testClearEnvironmentsDelegatesToInvalidateEnvironments(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('deleteItem')->willReturn(true);

        $service = new PerformanceCacheService($pool);

        $this->assertTrue($service->clearEnvironments());
    }

    public function testInvalidateAllCallsInvalidateEnvironments(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('deleteItem')->willReturn(true);

        $service = new PerformanceCacheService($pool);

        $this->assertTrue($service->invalidateAll());
    }

    public function testGetCachedValueAndCacheValueAndInvalidateValue(): void
    {
        $missItem = $this->createMock(CacheItemInterface::class);
        $missItem->method('isHit')->willReturn(false);

        $setItem = $this->createMock(CacheItemInterface::class);
        $setItem->expects($this->once())->method('set')->with('v');
        $setItem->expects($this->once())->method('expiresAfter')->with(3600);

        $hitItem = $this->createMock(CacheItemInterface::class);
        $hitItem->method('isHit')->willReturn(true);
        $hitItem->method('get')->willReturn('v');

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->exactly(3))
            ->method('getItem')
            ->with($this->stringContains('mykey'))
            ->willReturnOnConsecutiveCalls($setItem, $hitItem, $missItem);
        $pool->expects($this->once())->method('save')->willReturn(true);
        $pool->expects($this->once())->method('deleteItem')->with($this->stringContains('mykey'))->willReturn(true);

        $service = new PerformanceCacheService($pool);

        $this->assertTrue($service->cacheValue('mykey', 'v'));
        $this->assertSame('v', $service->getCachedValue('mykey'));
        $this->assertTrue($service->invalidateValue('mykey'));
        $this->assertNull($service->getCachedValue('mykey'));
    }

    public function testCacheStatisticsWhenSaveFails(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->method('save')->willReturn(false);

        $service = new PerformanceCacheService($pool);

        $this->assertFalse($service->cacheStatistics('dev', ['x' => 1]));
    }

    public function testCacheValueWhenSaveFails(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->method('save')->willReturn(false);

        $service = new PerformanceCacheService($pool);

        $this->assertFalse($service->cacheValue('mykey', 'value'));
    }

    public function testCacheValueWithCustomTtl(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('set')->with('custom');
        $item->expects($this->once())->method('expiresAfter')->with(600);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->method('save')->willReturn(true);

        $service = new PerformanceCacheService($pool);

        $this->assertTrue($service->cacheValue('custom_key', 'custom', 600));
    }

    public function testCacheEnvironmentsWhenSaveFails(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->method('save')->willReturn(false);

        $service = new PerformanceCacheService($pool);

        $this->assertFalse($service->cacheEnvironments(['dev', 'prod']));
    }

    public function testInvalidateValueReturnsFalseWhenPoolNull(): void
    {
        $service = new PerformanceCacheService(null);

        $this->assertFalse($service->invalidateValue('any_key'));
    }

    public function testGetCachedStatisticsReturnsNullForStageEnvWhenPoolNull(): void
    {
        $service = new PerformanceCacheService(null);

        $this->assertNull($service->getCachedStatistics('stage'));
    }
}
