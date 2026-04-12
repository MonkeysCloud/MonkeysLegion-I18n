<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Loaders;

use MonkeysLegion\I18n\Contract\LoaderInterface;
use MonkeysLegion\I18n\Exceptions\LoaderException;

/**
 * Loads translations from MLC (MonkeysLegion Config) files.
 *
 * MLC files use a simple, INI-inspired key=value format with section headers
 * for grouping. Designed as a lightweight alternative to YAML without any
 * external dependencies.
 *
 * Format example:
 * ```mlc
 * # Comment
 * [messages]
 * welcome = Welcome!
 * greeting = Hello, :name!
 * nested.key = Nested value
 *
 * [validation]
 * required = The :field field is required.
 * ```
 */
final class MlcLoader implements LoaderInterface
{
    // ── Constants ─────────────────────────────────────────────────

    private const int MAX_FILE_SIZE = 2_097_152; // 2 MB

    // ── Properties ────────────────────────────────────────────────

    /** @var array<string, string> */
    private array $namespaces = [];

    private readonly string $path;

    // ── Constructor ───────────────────────────────────────────────

    public function __construct(string $path)
    {
        $this->path = rtrim($path, DIRECTORY_SEPARATOR);
    }

    // ── LoaderInterface ───────────────────────────────────────────

    public function load(string $locale, string $group, ?string $namespace = null): array
    {
        $this->validateSegment($locale, 'locale');
        $this->validateSegment($group, 'group');

        $basePath = ($namespace !== null && isset($this->namespaces[$namespace]))
            ? $this->namespaces[$namespace]
            : $this->path;

        // Try group-specific MLC: {locale}/{group}.mlc
        $groupFile = $basePath . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $group . '.mlc';

        if (is_file($groupFile)) {
            return $this->loadMlcFile($groupFile);
        }

        // Try single-file MLC: {locale}.mlc (with sections)
        $localeFile = $basePath . DIRECTORY_SEPARATOR . $locale . '.mlc';

        if (is_file($localeFile)) {
            $all = $this->loadMlcFileWithSections($localeFile);

            return $all[$group] ?? [];
        }

        return [];
    }

    public function addNamespace(string $namespace, string $path): void
    {
        $this->namespaces[$namespace] = rtrim($path, DIRECTORY_SEPARATOR);
    }

    // ── Parsing ───────────────────────────────────────────────────

    /**
     * Parse a flat MLC file (no sections) into a nested array.
     *
     * @return array<string, mixed>
     */
    private function loadMlcFile(string $file): array
    {
        $this->validateFileSize($file);

        $content = file_get_contents($file);

        if ($content === false) {
            throw new LoaderException("Failed to read MLC file: {$file}");
        }

        return $this->parseFlat($content);
    }

    /**
     * Parse a sectioned MLC file into section → key/value arrays.
     *
     * @return array<string, array<string, mixed>>
     */
    private function loadMlcFileWithSections(string $file): array
    {
        $this->validateFileSize($file);

        $content = file_get_contents($file);

        if ($content === false) {
            throw new LoaderException("Failed to read MLC file: {$file}");
        }

        return $this->parseSectioned($content);
    }

    /**
     * Parse flat key=value pairs (supports dot notation for nesting).
     *
     * @return array<string, mixed>
     */
    private function parseFlat(string $content): array
    {
        $result = [];

        foreach (explode("\n", $content) as $lineContent) {
            $line = trim($lineContent);

            // Skip empty lines and comments
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }

            // Skip section headers in flat mode
            if (str_starts_with($line, '[')) {
                continue;
            }

            // Parse key = value
            $eqPos = strpos($line, '=');

            if ($eqPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));

            // Strip surrounding quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Handle escape sequences in double-quoted strings
            $value = str_replace(['\\n', '\\t', '\\\\'], ["\n", "\t", "\\"], $value);

            // Set nested value using dot notation
            $this->setNestedValue($result, $key, $value);
        }

        return $result;
    }

    /**
     * Parse a file with [section] headers.
     *
     * @return array<string, array<string, mixed>>
     */
    private function parseSectioned(string $content): array
    {
        $sections = [];
        $currentSection = '_default';
        $sectionContent = [];

        foreach (explode("\n", $content) as $lineContent) {
            $line = trim($lineContent);

            // Skip empty lines and comments
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }

            // Section header
            if (preg_match('/^\[([a-zA-Z0-9_.-]+)\]$/', $line, $matches)) {
                // Save previous section
                if (!empty($sectionContent)) {
                    $sections[$currentSection] = $this->parseFlat(implode("\n", $sectionContent));
                }

                $currentSection = $matches[1];
                $sectionContent = [];

                continue;
            }

            $sectionContent[] = $line;
        }

        // Save last section
        if (!empty($sectionContent)) {
            $sections[$currentSection] = $this->parseFlat(implode("\n", $sectionContent));
        }

        return $sections;
    }

    // ── Validation ────────────────────────────────────────────────

    /**
     * Validate a path segment to prevent directory traversal.
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
            throw new LoaderException("Invalid {$label}: '{$segment}' — potential path traversal detected.");
        }
    }

    /**
     * Validate file size to prevent memory exhaustion.
     */
    private function validateFileSize(string $file): void
    {
        $size = filesize($file);

        if ($size !== false && $size > self::MAX_FILE_SIZE) {
            throw new LoaderException(
                "MLC file exceeds maximum size (" . self::MAX_FILE_SIZE . " bytes): {$file}",
            );
        }
    }

    /**
     * Set nested array value using dot notation.
     *
     * @param array<string, mixed> $array
     */
    private function setNestedValue(array &$array, string $key, string $value): void
    {
        if (!str_contains($key, '.')) {
            $array[$key] = $value;

            return;
        }

        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current = $value;
    }
}
