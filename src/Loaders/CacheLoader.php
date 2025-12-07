<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Loaders;

use MonkeysLegion\I18n\Contracts\LoaderInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Caches translations loaded from another loader
 * Integrates with MonkeysLegion-Cache
 */
final class CacheLoader implements LoaderInterface
{
    private LoaderInterface $loader;
    private CacheInterface $cache;
    private int $ttl;
    private string $prefix;

    public function __construct(
        LoaderInterface $loader,
        CacheInterface $cache,
        int $ttl = 3600,
        string $prefix = 'i18n'
    ) {
        $this->loader = $loader;
        $this->cache = $cache;
        $this->ttl = $ttl;
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
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
        
        // Cache the result
        if (!empty($messages)) {
            $this->cache->set($cacheKey, $messages, $this->ttl);
        }
        
        return $messages;
    }

    /**
     * {@inheritdoc}
     */
    public function addNamespace(string $namespace, string $path): void
    {
        $this->loader->addNamespace($namespace, $path);
    }

    /**
     * Clear cached translations
     */
    public function flush(): void
    {
        $this->cache->clear();
    }

    /**
     * Clear cache for specific locale/group
     */
    public function forget(string $locale, string $group, ?string $namespace = null): void
    {
        $cacheKey = $this->getCacheKey($locale, $group, $namespace);
        $this->cache->delete($cacheKey);
    }

    /**
     * Generate cache key
     */
    private function getCacheKey(string $locale, string $group, ?string $namespace): string
    {
        $parts = [$this->prefix, $locale, $group];
        
        if ($namespace !== null) {
            $parts[] = $namespace;
        }
        
        return implode('.', $parts);
    }
}
