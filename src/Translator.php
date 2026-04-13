<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n;

use MonkeysLegion\I18n\Contract\LoaderInterface;
use MonkeysLegion\I18n\Contract\MessageFormatterInterface;
use MonkeysLegion\I18n\Enum\PluralCategory;
use MonkeysLegion\I18n\Event\LocaleChangedEvent;
use MonkeysLegion\I18n\Exceptions\InvalidLocaleException;

/**
 * Production-ready Translator with caching, pluralization, and namespacing.
 *
 * Uses PHP 8.4 property hooks for locale management with built-in validation.
 */
final class Translator
{
    // ── Locale regex ──────────────────────────────────────────────

    private const string LOCALE_PATTERN = '/^[a-z]{2,3}(_[A-Z]{2})?$/';

    // ── Properties ────────────────────────────────────────────────

    /** @var list<LoaderInterface> */
    private array $loaders = [];

    /** @var array<string, array<string, mixed>> */
    private array $messages = [];

    /** @var array<string, bool> */
    private array $loadedNamespaces = [];

    private MessageFormatterInterface $formatter;
    private Pluralizer $pluralizer;

    /** @var list<string> Missing translation keys for debugging */
    private array $missingTranslations = [];

    private bool $trackMissing = false;

    /** @var array<string, string> Namespace to path mapping */
    private array $namespaces = [];

    /** @var callable|null Event dispatcher callback */
    private $eventDispatcher = null;

    // ── Locale with property hooks ────────────────────────────────

    public string $locale {
        set(string $value) {
            $this->validateLocale($value);
            $old = $this->locale ?? $value;
            $this->locale = $value;
            if (isset($old) && $old !== $value && $this->eventDispatcher !== null) {
                ($this->eventDispatcher)(new LocaleChangedEvent($old, $value));
            }
        }
    }

    public string $fallbackLocale {
        set(string $value) {
            $this->validateLocale($value);
            $this->fallbackLocale = $value;
        }
    }

    // ── Constructor ───────────────────────────────────────────────

    public function __construct(
        string $locale,
        string $fallbackLocale = 'en',
        ?MessageFormatterInterface $formatter = null,
        ?Pluralizer $pluralizer = null,
    ) {
        $this->validateLocale($locale);
        $this->validateLocale($fallbackLocale);
        $this->locale = $locale;
        $this->fallbackLocale = $fallbackLocale;
        $this->formatter = $formatter ?? new MessageFormatter();
        $this->pluralizer = $pluralizer ?? new Pluralizer();
    }

    // ── Public API ────────────────────────────────────────────────

    /**
     * Add a translation loader.
     */
    public function addLoader(LoaderInterface $loader): void
    {
        $this->loaders[] = $loader;
    }

    /**
     * Register a namespace for translations.
     */
    public function addNamespace(string $namespace, string $path): void
    {
        $this->namespaces[$namespace] = $path;
    }

    /**
     * Set an event dispatcher callback.
     *
     * @param callable(LocaleChangedEvent): void $dispatcher
     */
    public function setEventDispatcher(callable $dispatcher): void
    {
        $this->eventDispatcher = $dispatcher;
    }

    /**
     * Translate a key with optional replacements.
     *
     * @param string               $key     Translation key (supports dot notation and namespaces)
     * @param array<string, mixed> $replace Replacement values
     * @param string|null          $locale  Override locale
     */
    public function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;

        // Parse namespace::group.key format
        [$namespace, $group, $item] = $this->parseKey($key);

        // Load the translation group if not loaded
        $this->loadGroup($namespace, $group, $locale);

        // Try to get translation
        $line = $this->getLine($namespace, $group, $item, $locale);

        // Try fallback locale if not found
        if ($line === null && $locale !== $this->fallbackLocale) {
            $this->loadGroup($namespace, $group, $this->fallbackLocale);
            $line = $this->getLine($namespace, $group, $item, $this->fallbackLocale);
        }

        // Return key if translation not found
        if ($line === null) {
            $this->trackMissingTranslation($key, $locale);
            return $key;
        }

        // Format message with replacements
        return $this->formatter->format($line, $replace, $locale);
    }

    /**
     * Get translation with pluralization.
     *
     * @param string               $key     Translation key
     * @param int|float            $count   Count for pluralization
     * @param array<string, mixed> $replace Additional replacements
     * @param string|null          $locale  Override locale
     */
    public function choice(string $key, int|float $count, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;

        // Get the translation line
        $line = $this->trans($key, $replace, $locale);

        // If it's the key itself (not found), return it
        if ($line === $key) {
            return $key;
        }

        // Apply pluralization
        $pluralized = $this->pluralizer->choose($line, $count, $locale);

        // Replace :count placeholder
        $replace['count'] = $count;

        return $this->formatter->format($pluralized, $replace, $locale);
    }

    /**
     * Check if translation exists.
     */
    public function has(string $key, ?string $locale = null): bool
    {
        $locale = $locale ?? $this->locale;
        [$namespace, $group, $item] = $this->parseKey($key);

        $this->loadGroup($namespace, $group, $locale);

        return $this->getLine($namespace, $group, $item, $locale) !== null;
    }

    /**
     * Get current locale.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Set current locale.
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * Get fallback locale.
     */
    public function getFallbackLocale(): string
    {
        return $this->fallbackLocale;
    }

    /**
     * Set fallback locale.
     */
    public function setFallbackLocale(string $locale): void
    {
        $this->fallbackLocale = $locale;
    }

    /**
     * Enable/disable missing translation tracking.
     */
    public function setTrackMissing(bool $track): void
    {
        $this->trackMissing = $track;
    }

    /**
     * Get missing translations.
     *
     * @return list<string>
     */
    public function getMissingTranslations(): array
    {
        return array_values(array_unique($this->missingTranslations));
    }

    /**
     * Clear missing translations log.
     */
    public function clearMissingTranslations(): void
    {
        $this->missingTranslations = [];
    }

    /**
     * Pre-load all groups for a locale (warm-up).
     *
     * @param string               $locale   Locale to warm up
     * @param list<string>         $groups   Groups to pre-load
     * @param list<string|null>    $namespaces Namespaces (null = default)
     */
    public function warmUp(string $locale, array $groups, array $namespaces = [null]): void
    {
        foreach ($namespaces as $namespace) {
            foreach ($groups as $group) {
                $this->loadGroup($namespace, $group, $locale);
            }
        }
    }

    /**
     * Get all loaded groups for debugging.
     *
     * @return list<string>
     */
    public function getLoadedGroups(): array
    {
        return array_keys($this->loadedNamespaces);
    }

    /**
     * Get all available keys for a locale.
     *
     * @return list<string>
     */
    public function allKeys(string $locale, string $group, ?string $namespace = null): array
    {
        $this->loadGroup($namespace, $group, $locale);

        $cacheKey = $this->getCacheKey($namespace, $group, $locale);

        if (!isset($this->messages[$cacheKey])) {
            return [];
        }

        return $this->flattenKeys($this->messages[$cacheKey]);
    }

    /**
     * Get all available keys for a locale.
     *
     * @return list<string>
     */
    public function allKeys(string $locale, string $group, ?string $namespace = null): array
    {
        $this->loadGroup($namespace, $group, $locale);

        $cacheKey = $this->getCacheKey($namespace, $group, $locale);

        if (!isset($this->messages[$cacheKey])) {
            return [];
        }

        return $this->flattenKeys($this->messages[$cacheKey]);
    }

    /**
     * Get all translations for a group as a flat key-value array.
     *
     * @return array<string, string>
     */
    public function getAll(string $locale, string $group, ?string $namespace = null): array
    {
        $this->loadGroup($namespace, $group, $locale);

        $cacheKey = $this->getCacheKey($namespace, $group, $locale);

        if (!isset($this->messages[$cacheKey])) {
            return [];
        }

        return $this->flattenToValues($this->messages[$cacheKey]);
    }

    /**
     * Register translations at runtime without a loader.
     *
     * @param array<string, mixed> $messages
     */
    public function addTranslation(string $locale, string $group, array $messages, ?string $namespace = null): void
    {
        $this->mergeMessages($namespace, $group, $locale, $messages);

        // Mark as loaded so subsequent loadGroup calls won't overwrite
        $cacheKey = $this->getCacheKey($namespace, $group, $locale);
        $this->loadedNamespaces[$cacheKey] = true;
    }

    /**
     * Clear all in-memory loaded groups — forces reload on next access.
     */
    public function clearLoadedGroups(): void
    {
        $this->messages        = [];
        $this->loadedNamespaces = [];
    }

    // ── Private methods ───────────────────────────────────────────

    /**
     * Validate locale code.
     */
    private function validateLocale(string $locale): void
    {
        if (!preg_match(self::LOCALE_PATTERN, $locale)) {
            throw new InvalidLocaleException($locale);
        }
    }

    /**
     * Parse a key into namespace, group, and item.
     *
     * @return array{0: string|null, 1: string, 2: string}
     */
    private function parseKey(string $key): array
    {
        // Check for namespace (vendor::group.item)
        if (str_contains($key, '::')) {
            [$namespace, $rest] = explode('::', $key, 2);
        } else {
            $namespace = null;
            $rest = $key;
        }

        // Split group and item (group.item)
        $segments = explode('.', $rest, 2);
        $group = $segments[0];
        $item = $segments[1] ?? '';

        return [$namespace, $group, $item];
    }

    /**
     * Load a translation group.
     */
    private function loadGroup(?string $namespace, string $group, string $locale): void
    {
        $cacheKey = $this->getCacheKey($namespace, $group, $locale);

        if (isset($this->loadedNamespaces[$cacheKey])) {
            return;
        }

        foreach ($this->loaders as $loader) {
            $messages = $loader->load($locale, $group, $namespace);

            if (!empty($messages)) {
                $this->mergeMessages($namespace, $group, $locale, $messages);
            }
        }

        $this->loadedNamespaces[$cacheKey] = true;
    }

    /**
     * Get a translation line.
     */
    private function getLine(?string $namespace, string $group, string $item, string $locale): ?string
    {
        $cacheKey = $this->getCacheKey($namespace, $group, $locale);

        if (!isset($this->messages[$cacheKey])) {
            return null;
        }

        if ($item === '') {
            return null;
        }

        // Support nested keys with dot notation
        $segments = explode('.', $item);
        $array = $this->messages[$cacheKey];

        foreach ($segments as $segment) {
            if (!is_array($array) || !isset($array[$segment])) {
                return null;
            }
            $array = $array[$segment];
        }

        return is_string($array) ? $array : null;
    }

    /**
     * Merge messages into cache.
     *
     * @param array<string, mixed> $messages
     */
    private function mergeMessages(?string $namespace, string $group, string $locale, array $messages): void
    {
        $cacheKey = $this->getCacheKey($namespace, $group, $locale);

        if (!isset($this->messages[$cacheKey])) {
            $this->messages[$cacheKey] = [];
        }

        $this->messages[$cacheKey] = array_replace_recursive($this->messages[$cacheKey], $messages);
    }

    /**
     * Get cache key for a translation group.
     */
    private function getCacheKey(?string $namespace, string $group, string $locale): string
    {
        return $namespace !== null ? "{$namespace}::{$group}.{$locale}" : "{$group}.{$locale}";
    }

    /**
     * Track missing translation.
     */
    private function trackMissingTranslation(string $key, string $locale): void
    {
        if ($this->trackMissing) {
            $this->missingTranslations[] = "{$locale}.{$key}";
        }
    }

    /**
     * Flatten a nested array into dot-notation keys (iterative — avoids deep recursion).
     *
     * @param array<string, mixed> $array
     * @return list<string>
     */
    private function flattenKeys(array $array, string $prefix = ''): array
    {
        $keys  = [];
        $stack = [[$array, $prefix]];

        while ($stack !== []) {
            /** @var array{0: array<string, mixed>, 1: string} $item */
            [$current, $currentPrefix] = array_pop($stack);

            foreach ($current as $key => $value) {
                $fullKey = $currentPrefix !== '' ? "{$currentPrefix}.{$key}" : (string) $key;

                if (is_array($value)) {
                    $stack[] = [$value, $fullKey];
                } else {
                    $keys[] = $fullKey;
                }
            }
        }

        return $keys;
    }

    /**
     * Flatten a nested array into dot-notation key-value pairs (iterative).
     *
     * @param array<string, mixed> $array
     * @return array<string, string>
     */
    private function flattenToValues(array $array, string $prefix = ''): array
    {
        $result = [];
        $stack  = [[$array, $prefix]];

        while ($stack !== []) {
            /** @var array{0: array<string, mixed>, 1: string} $item */
            [$current, $currentPrefix] = array_pop($stack);

            foreach ($current as $key => $value) {
                $fullKey = $currentPrefix !== '' ? "{$currentPrefix}.{$key}" : (string) $key;

                if (is_array($value)) {
                    $stack[] = [$value, $fullKey];
                } elseif (is_string($value)) {
                    $result[$fullKey] = $value;
                }
            }
        }

        return $result;
    }
}
