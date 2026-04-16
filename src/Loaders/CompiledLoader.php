<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Loaders;

use MonkeysLegion\I18n\Contract\LoaderInterface;
use MonkeysLegion\I18n\Exceptions\LoaderException;

use RuntimeException;

/**
 * Opcache-friendly compiled translations loader.
 *
 * Compiles all JSON/PHP translations for a locale into a single PHP file
 * that can be cached by opcache — 10-50x faster than JSON decode per request.
 *
 * Usage:
 * ```php
 * $compiled = new CompiledLoader($fileLoader, '/var/cache/i18n');
 * $compiled->compile('en');
 * $messages = $compiled->load('en', 'messages');
 * ```
 */
final class CompiledLoader implements LoaderInterface
{
    // ── Properties ────────────────────────────────────────────────

    private readonly LoaderInterface $sourceLoader;
    private readonly string $compilePath;

    /** @var array<string, string> */
    private array $namespaces = [];

    /** @var array<string, array<string, mixed>> Runtime cache */
    private array $compiledCache = [];

    // ── Constructor ───────────────────────────────────────────────

    public function __construct(
        LoaderInterface $sourceLoader,
        string $compilePath,
    ) {
        $this->sourceLoader = $sourceLoader;
        $this->compilePath = rtrim($compilePath, DIRECTORY_SEPARATOR);
    }

    // ── LoaderInterface ───────────────────────────────────────────

    public function load(string $locale, string $group, ?string $namespace = null): array
    {
        // Prevent path traversal via user-controlled locale / group / namespace inputs
        $this->validateSegment($locale, 'locale');
        $this->validateSegment($group, 'group');
        if ($namespace !== null) {
            $this->validateSegment($namespace, 'namespace');
        }

        $cacheKey = $this->getCacheKey($locale, $namespace);

        // Check runtime cache
        if (isset($this->compiledCache[$cacheKey])) {
            return $this->compiledCache[$cacheKey][$group] ?? [];
        }

        // Try to load compiled file
        $compiledFile = $this->getCompiledPath($locale, $namespace);

        if (is_file($compiledFile)) {
            /** @var array<string, array<string, mixed>> $data */
            $data = require $compiledFile;

            if (is_array($data)) {
                $this->compiledCache[$cacheKey] = $data;
                return $data[$group] ?? [];
            }
        }

        // Fallback to source loader
        return $this->sourceLoader->load($locale, $group, $namespace);
    }

    public function addNamespace(string $namespace, string $path): void
    {
        $this->namespaces[$namespace] = $path;
        $this->sourceLoader->addNamespace($namespace, $path);
    }

    // ── Compilation ───────────────────────────────────────────────

    /**
     * Compile all translation files for a locale into a single PHP file.
     *
     * @param string      $locale    Locale to compile
     * @param string      $sourcePath Path to source translation files
     * @param string|null $namespace Optional namespace
     *
     * @return string Path to the compiled file
     */
    public function compile(string $locale, string $sourcePath, ?string $namespace = null): string
    {
        $all = [];

        $localePath = $sourcePath . DIRECTORY_SEPARATOR . $locale;

        if (is_dir($localePath)) {
            $files = glob($localePath . DIRECTORY_SEPARATOR . '*.{json,php}', GLOB_BRACE);

            if ($files !== false) {
                foreach ($files as $file) {
                    $group = pathinfo($file, PATHINFO_FILENAME);
                    $all[$group] = $this->sourceLoader->load($locale, $group, $namespace);
                }
            }
        }

        // Generate compiled PHP file
        $compiledFile = $this->getCompiledPath($locale, $namespace);
        $this->writeCompiledFile($compiledFile, $all);

        // Invalidate runtime cache
        $cacheKey = $this->getCacheKey($locale, $namespace);
        unset($this->compiledCache[$cacheKey]);

        return $compiledFile;
    }

    /**
     * Check if compiled file exists and is fresh.
     */
    public function isFresh(string $locale, string $sourcePath, ?string $namespace = null): bool
    {
        $compiledFile = $this->getCompiledPath($locale, $namespace);

        if (!is_file($compiledFile)) {
            return false;
        }

        $compiledTime = filemtime($compiledFile);

        if ($compiledTime === false) {
            return false;
        }

        $localePath = $sourcePath . DIRECTORY_SEPARATOR . $locale;

        if (!is_dir($localePath)) {
            return true;
        }

        $files = glob($localePath . DIRECTORY_SEPARATOR . '*.{json,php}', GLOB_BRACE);

        if ($files === false) {
            return true;
        }

        foreach ($files as $file) {
            $sourceTime = filemtime($file);
            if ($sourceTime !== false && $sourceTime > $compiledTime) {
                return false;
            }
        }

        return true;
    }

    /**
     * Invalidate compiled cache for a locale.
     */
    public function invalidate(string $locale, ?string $namespace = null): void
    {
        $compiledFile = $this->getCompiledPath($locale, $namespace);

        if (is_file($compiledFile)) {
            unlink($compiledFile);
        }

        $cacheKey = $this->getCacheKey($locale, $namespace);
        unset($this->compiledCache[$cacheKey]);
    }

    // ── Private methods ───────────────────────────────────────────

    /**
     * Validate a path segment to prevent directory traversal.
     * Does not include the segment in the exception message to avoid
     * leaking untrusted input to callers.
     */
    private function validateSegment(string $segment, string $label): void
    {
        if (
            $segment === ''
            || str_contains($segment, '..')
            || str_contains($segment, '/')
            || str_contains($segment, '\\')
            || str_contains($segment, "\0")
        ) {
            throw new LoaderException("Invalid {$label} — potential path traversal detected.");
        }
    }

    private function getCompiledPath(string $locale, ?string $namespace): string
    {
        $filename = $namespace !== null ? "{$namespace}_{$locale}" : $locale;

        return $this->compilePath . DIRECTORY_SEPARATOR . "{$filename}.compiled.php";
    }

    private function getCacheKey(string $locale, ?string $namespace): string
    {
        return $namespace !== null ? "{$namespace}::{$locale}" : $locale;
    }

    /**
     * Write compiled PHP file atomically.
     *
     * @param array<string, array<string, mixed>> $data
     */
    private function writeCompiledFile(string $path, array $data): void
    {
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = "<?php\n\n// Auto-generated compiled translations — DO NOT EDIT\n// Generated: "
            . gmdate('Y-m-d\\TH:i:s\\Z') . "\n\nreturn "
            . var_export($data, true) . ";\n";

        // Atomic write via temp file + rename
        $tmpFile = $path . '.tmp.' . getmypid();

        if (file_put_contents($tmpFile, $content, LOCK_EX) === false) {
            throw new RuntimeException("Failed to write compiled translation file: {$path}");
        }

        rename($tmpFile, $path);

        // Invalidate opcache if available
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }
    }
}
