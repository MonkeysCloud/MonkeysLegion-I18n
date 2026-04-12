<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Loaders;

use MonkeysLegion\I18n\Contract\LoaderInterface;
use MonkeysLegion\I18n\Exceptions\LoaderException;

use RuntimeException;

/**
 * Loads translations from JSON and PHP files with security hardening.
 *
 * Security:
 * - Path traversal prevention via realpath() validation
 * - Maximum file size limit to prevent memory exhaustion
 * - JSON_THROW_ON_ERROR on all json_decode calls
 * - Symlink resolution and validation
 */
final class FileLoader implements LoaderInterface
{
    // ── Constants ─────────────────────────────────────────────────

    private const int MAX_FILE_SIZE = 2_097_152; // 2 MB

    // ── Properties ────────────────────────────────────────────────

    /** @var array<string, string> */
    private array $namespaces = [];

    private string $path;

    // ── Constructor ───────────────────────────────────────────────

    public function __construct(string $path)
    {
        $this->path = rtrim($path, DIRECTORY_SEPARATOR);
    }

    // ── LoaderInterface ───────────────────────────────────────────

    public function load(string $locale, string $group, ?string $namespace = null): array
    {
        // Validate locale/group to prevent path traversal
        $this->validateSegment($locale, 'locale');
        $this->validateSegment($group, 'group');

        if ($namespace !== null && isset($this->namespaces[$namespace])) {
            return $this->loadPath($this->namespaces[$namespace], $locale, $group);
        }

        return $this->loadPath($this->path, $locale, $group);
    }

    public function addNamespace(string $namespace, string $path): void
    {
        $this->namespaces[$namespace] = rtrim($path, DIRECTORY_SEPARATOR);
    }

    // ── Private methods ───────────────────────────────────────────

    /**
     * Validate a path segment to prevent directory traversal.
     */
    private function validateSegment(string $segment, string $label): void
    {
        if ($segment === '' || str_contains($segment, '..') || str_contains($segment, '/') || str_contains($segment, '\\') || str_contains($segment, "\0")) {
            throw new LoaderException("Invalid {$label}: '{$segment}' — potential path traversal detected.");
        }
    }

    /**
     * Load translations from a specific path.
     *
     * @return array<string, mixed>
     */
    private function loadPath(string $path, string $locale, string $group): array
    {
        // Try loading from nested structure: path/{locale}/{group}.{ext}
        $messages = $this->loadNested($path, $locale, $group);

        if (!empty($messages)) {
            return $messages;
        }

        // Try loading from flat structure: path/{locale}.json
        return $this->loadFlat($path, $locale, $group);
    }

    /**
     * Load from nested directory structure.
     *
     * @return array<string, mixed>
     */
    private function loadNested(string $path, string $locale, string $group): array
    {
        $directory = $path . DIRECTORY_SEPARATOR . $locale;

        if (!is_dir($directory)) {
            return [];
        }

        // Try JSON file first (preferred in v2)
        $jsonFile = $directory . DIRECTORY_SEPARATOR . $group . '.json';
        if (is_file($jsonFile)) {
            return $this->loadJsonFile($jsonFile);
        }

        // Try PHP file
        $phpFile = $directory . DIRECTORY_SEPARATOR . $group . '.php';
        if (is_file($phpFile)) {
            return $this->loadPhpFile($phpFile);
        }

        return [];
    }

    /**
     * Load from flat file structure (single file per locale).
     *
     * @return array<string, mixed>
     */
    private function loadFlat(string $path, string $locale, string $group): array
    {
        // Try JSON file
        $jsonFile = $path . DIRECTORY_SEPARATOR . $locale . '.json';
        if (is_file($jsonFile)) {
            $data = $this->loadJsonFile($jsonFile);
            $groupData = $data[$group] ?? [];

            return is_array($groupData) ? $groupData : [];
        }

        // Try PHP file
        $phpFile = $path . DIRECTORY_SEPARATOR . $locale . '.php';
        if (is_file($phpFile)) {
            $data = $this->loadPhpFile($phpFile);
            $groupData = $data[$group] ?? [];

            return is_array($groupData) ? $groupData : [];
        }

        return [];
    }

    /**
     * Load and validate PHP array file.
     *
     * @return array<string, mixed>
     */
    private function loadPhpFile(string $file): array
    {
        $this->validateFilePath($file);
        $this->validateFileSize($file);

        $data = require $file;

        if (!is_array($data)) {
            throw new RuntimeException("Translation file must return an array: {$file}");
        }

        return $data;
    }

    /**
     * Load and validate JSON file.
     *
     * @return array<string, mixed>
     */
    private function loadJsonFile(string $file): array
    {
        $this->validateFilePath($file);
        $this->validateFileSize($file);

        $json = file_get_contents($file);

        if ($json === false) {
            throw new RuntimeException("Failed to read translation file: {$file}");
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException("Invalid JSON in translation file: {$file} — {$e->getMessage()}");
        }

        if (!is_array($data)) {
            throw new RuntimeException("Translation JSON must decode to an array: {$file}");
        }

        return $data;
    }

    /**
     * Validate that file path is within allowed directories.
     */
    private function validateFilePath(string $file): void
    {
        $realFile = realpath($file);
        $realBase = realpath($this->path);

        // Check namespace paths too
        if ($realFile === false) {
            return; // File doesn't exist yet — will fail at read
        }

        if ($realBase !== false && str_starts_with($realFile, $realBase)) {
            return; // Within main path — OK
        }

        // Check namespace paths
        foreach ($this->namespaces as $namespacePath) {
            $realNamespace = realpath($namespacePath);
            if ($realNamespace !== false && str_starts_with($realFile, $realNamespace)) {
                return; // Within namespace path — OK
            }
        }

        throw new LoaderException("File path escapes allowed directory: {$file}");
    }

    /**
     * Validate file size to prevent memory exhaustion.
     */
    private function validateFileSize(string $file): void
    {
        $size = filesize($file);

        if ($size !== false && $size > self::MAX_FILE_SIZE) {
            throw new LoaderException(
                "Translation file exceeds maximum size (" . self::MAX_FILE_SIZE . " bytes): {$file}",
            );
        }
    }
}
