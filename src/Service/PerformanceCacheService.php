<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service for caching performance metrics data.
 *
 * Provides caching for statistics calculations and environment lists
 * to improve dashboard performance.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class PerformanceCacheService
{
    /**
     * Cache key prefix for performance bundle cache items.
     */
    private const CACHE_PREFIX = 'nowo_performance_';

    /**
     * Default TTL for cache items (1 hour).
     */
    private const DEFAULT_TTL = 3600;

    /**
     * Cache pool instance.
     */
    private readonly ?CacheItemPoolInterface $cachePool;

    /**
     * Creates a new instance.
     *
     * @param CacheItemPoolInterface|string|null $cachePool The cache pool (optional, uses app cache if not provided)
     */
    public function __construct(
        #[Autowire('?cache.app')]
        CacheItemPoolInterface|string|null $cachePool = null,
    ) {
        // Handle case where cache service might not be available (string passed instead of null)
        // or when the service is not configured
        if ($cachePool instanceof CacheItemPoolInterface) {
            $this->cachePool = $cachePool;
        } else {
            $this->cachePool = null;
        }
    }

    /**
     * Get cached statistics for an environment.
     *
     * @param string $env The environment
     *
     * @return array<string, mixed>|null Cached statistics or null if not cached
     */
    public function getCachedStatistics(string $env): ?array
    {
        if (null === $this->cachePool) {
            return null;
        }

        $key = $this->getStatisticsKey($env);
        $item = $this->cachePool->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        return null;
    }

    /**
     * Cache statistics for an environment.
     *
     * @param string               $env        The environment
     * @param array<string, mixed> $statistics The statistics to cache
     * @param int|null             $ttl        Time to live in seconds (default: 1 hour)
     *
     * @return bool True if cached successfully
     */
    public function cacheStatistics(string $env, array $statistics, ?int $ttl = null): bool
    {
        if (null === $this->cachePool) {
            return false;
        }

        $key = $this->getStatisticsKey($env);
        $item = $this->cachePool->getItem($key);
        $item->set($statistics);
        $item->expiresAfter($ttl ?? self::DEFAULT_TTL);

        return $this->cachePool->save($item);
    }

    /**
     * Get cached environment list.
     *
     * @return string[]|null Cached environment list or null if not cached
     */
    public function getCachedEnvironments(): ?array
    {
        if (null === $this->cachePool) {
            return null;
        }

        $key = $this->getEnvironmentsKey();
        $item = $this->cachePool->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        return null;
    }

    /**
     * Cache environment list.
     *
     * @param string[] $environments The environment list to cache
     * @param int|null $ttl          Time to live in seconds (default: 1 hour)
     *
     * @return bool True if cached successfully
     */
    public function cacheEnvironments(array $environments, ?int $ttl = null): bool
    {
        if (null === $this->cachePool) {
            return false;
        }

        $key = $this->getEnvironmentsKey();
        $item = $this->cachePool->getItem($key);
        $item->set($environments);
        $item->expiresAfter($ttl ?? self::DEFAULT_TTL);

        return $this->cachePool->save($item);
    }

    /**
     * Invalidate statistics cache for an environment.
     *
     * @param string $env The environment
     *
     * @return bool True if invalidated successfully
     */
    public function invalidateStatistics(string $env): bool
    {
        if (null === $this->cachePool) {
            return false;
        }

        $key = $this->getStatisticsKey($env);

        return $this->cachePool->deleteItem($key);
    }

    /**
     * Invalidate environment list cache.
     *
     * @return bool True if invalidated successfully
     */
    public function invalidateEnvironments(): bool
    {
        if (null === $this->cachePool) {
            return false;
        }

        $key = $this->getEnvironmentsKey();

        return $this->cachePool->deleteItem($key);
    }

    /**
     * Invalidate all performance cache.
     *
     * @return bool True if invalidated successfully
     */
    public function invalidateAll(): bool
    {
        if (null === $this->cachePool) {
            return false;
        }

        // Invalidate environments cache (statistics will be invalidated on next update)
        return $this->invalidateEnvironments();
    }

    /**
     * Clear statistics cache for an environment.
     *
     * Alias for invalidateStatistics().
     *
     * @param string $env The environment
     *
     * @return bool True if cleared successfully
     */
    public function clearStatistics(string $env): bool
    {
        return $this->invalidateStatistics($env);
    }

    /**
     * Clear environment list cache.
     *
     * Alias for invalidateEnvironments().
     *
     * @return bool True if cleared successfully
     */
    public function clearEnvironments(): bool
    {
        return $this->invalidateEnvironments();
    }

    /**
     * Get cache key for statistics.
     *
     * @param string $env The environment
     *
     * @return string The cache key
     */
    private function getStatisticsKey(string $env): string
    {
        return self::CACHE_PREFIX.'stats_'.$env;
    }

    /**
     * Get cache key for environments list.
     *
     * @return string The cache key
     */
    private function getEnvironmentsKey(): string
    {
        return self::CACHE_PREFIX.'environments';
    }

    /**
     * Get a cached value by key.
     *
     * @param string $key The cache key
     *
     * @return mixed|null The cached value or null if not found
     */
    public function getCachedValue(string $key): mixed
    {
        if (null === $this->cachePool) {
            return null;
        }

        $item = $this->cachePool->getItem(self::CACHE_PREFIX.$key);

        if ($item->isHit()) {
            return $item->get();
        }

        return null;
    }

    /**
     * Cache a value by key.
     *
     * @param string   $key   The cache key
     * @param mixed    $value The value to cache
     * @param int|null $ttl   Time to live in seconds (default: 1 hour)
     *
     * @return bool True if cached successfully
     */
    public function cacheValue(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (null === $this->cachePool) {
            return false;
        }

        $item = $this->cachePool->getItem(self::CACHE_PREFIX.$key);
        $item->set($value);
        $item->expiresAfter($ttl ?? self::DEFAULT_TTL);

        return $this->cachePool->save($item);
    }

    /**
     * Invalidate a cached value by key.
     *
     * @param string $key The cache key
     *
     * @return bool True if invalidated successfully
     */
    public function invalidateValue(string $key): bool
    {
        if (null === $this->cachePool) {
            return false;
        }

        return $this->cachePool->deleteItem(self::CACHE_PREFIX.$key);
    }
}
