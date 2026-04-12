<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Loaders;

use MonkeysLegion\I18n\Contract\LoaderInterface;

use Psr\SimpleCache\CacheInterface;

/**
 * Decorator: caches translations loaded from another loader.
 *
 * Features:
 * - TTL jitter to prevent thundering herd
 * - Selective cache invalidation
 * - JSON_THROW_ON_ERROR serialization
 */
final class CacheLoader implements LoaderInterface
{
    // ── Properties ────────────────────────────────────────────────

    private readonly LoaderInterface $loader;
    private readonly CacheInterface $cache;
    private readonly int $ttl;
    private readonly string $prefix;

    // ── Constructor ───────────────────────────────────────────────

    public function __construct(
        LoaderInterface $loader,
        CacheInterface $cache,
        int $ttl = 3600,
        string $prefix = 'i18n',
    ) {
        $this->loader = $loader;
        $this->cache = $cache;
        $this->ttl = $ttl;
        $this->prefix = $prefix;
    }

    // ── LoaderInterface ───────────────────────────────────────────

    public function load(string $locale, string $group, ?string $namespace = null): array
    {
        $cacheKey = $this->getCacheKey($locale, $group, $namespace);

        // Try to get from cache
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        // Load from underlying loader
        $messages = $this->loader->load($locale, $group, $namespace);

        // Cache the result with TTL jitter (±10%)
        if (!empty($messages)) {
            $jitteredTtl = $this->addJitter($this->ttl);
            $this->cache->set($cacheKey, $messages, $jitteredTtl);
        }

        return $messages;
    }

    public function addNamespace(string $namespace, string $path): void
    {
        $this->loader->addNamespace($namespace, $path);
    }

    // ── Cache management ──────────────────────────────────────────

    /**
     * Clear all cached translations.
     */
    public function flush(): void
    {
        $this->cache->clear();
    }

    /**
     * Clear cache for specific locale/group.
     */
    public function forget(string $locale, string $group, ?string $namespace = null): void
    {
        $cacheKey = $this->getCacheKey($locale, $group, $namespace);
        $this->cache->delete($cacheKey);
    }

    // ── Private methods ───────────────────────────────────────────

    /**
     * Generate cache key.
     */
    private function getCacheKey(string $locale, string $group, ?string $namespace): string
    {
        $parts = [$this->prefix, $locale, $group];

        if ($namespace !== null) {
            $parts[] = $namespace;
        }

        return implode('.', $parts);
    }

    /**
     * Add ±10% jitter to TTL to prevent thundering herd.
     */
    private function addJitter(int $ttl): int
    {
        $jitter = (int) ($ttl * 0.1);

        if ($jitter < 1) {
            return $ttl;
        }

        return $ttl + random_int(-$jitter, $jitter);
    }
}
