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
 * - Cache tagging for bulk invalidation per locale/namespace
 * - JSON_THROW_ON_ERROR serialization
 *
 * NOTE: Tag-based invalidation (forgetLocale/forgetNamespace) uses an
 * in-memory tag→keys map. Tags are NOT persisted to the cache store,
 * so tag invalidation only works within the same process lifetime.
 * For cross-request invalidation, use forget() with explicit keys
 * or flush() to clear the entire cache.
 */
final class CacheLoader implements LoaderInterface
{
    // ── Properties ────────────────────────────────────────────────

    private readonly LoaderInterface $loader;
    private readonly CacheInterface $cache;
    private readonly int $ttl;
    private readonly string $prefix;

    /** @var array<string, list<string>> Tag → cache keys mapping */
    private array $tags = [];

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

            // Track tags for bulk invalidation
            $this->addTag("locale:{$locale}", $cacheKey);

            if ($namespace !== null) {
                $this->addTag("namespace:{$namespace}", $cacheKey);
            }
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
        $this->tags = [];
    }

    /**
     * Clear cache for specific locale/group.
     */
    public function forget(string $locale, string $group, ?string $namespace = null): void
    {
        $cacheKey = $this->getCacheKey($locale, $group, $namespace);
        $this->cache->delete($cacheKey);
    }

    /**
     * Clear all cached translations for a specific locale (tag-based).
     */
    public function forgetLocale(string $locale): void
    {
        $this->forgetByTag("locale:{$locale}");
    }

    /**
     * Clear all cached translations for a specific namespace (tag-based).
     */
    public function forgetNamespace(string $namespace): void
    {
        $this->forgetByTag("namespace:{$namespace}");
    }

    /**
     * Get all tracked tags.
     *
     * @return array<string, list<string>>
     */
    public function getTags(): array
    {
        return $this->tags;
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

    /**
     * Track a cache key under a tag.
     */
    private function addTag(string $tag, string $cacheKey): void
    {
        if (!isset($this->tags[$tag])) {
            $this->tags[$tag] = [];
        }

        if (!in_array($cacheKey, $this->tags[$tag], true)) {
            $this->tags[$tag][] = $cacheKey;
        }
    }

    /**
     * Forget all cache keys tracked under a tag.
     */
    private function forgetByTag(string $tag): void
    {
        if (!isset($this->tags[$tag])) {
            return;
        }

        foreach ($this->tags[$tag] as $cacheKey) {
            $this->cache->delete($cacheKey);
        }

        unset($this->tags[$tag]);
    }
}
