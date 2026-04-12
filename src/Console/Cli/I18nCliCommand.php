<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Console\Cli;

use MonkeysLegion\I18n\Console\TranslationCommand;
use MonkeysLegion\I18n\Loaders\CompiledLoader;
use MonkeysLegion\I18n\Loaders\FileLoader;
use MonkeysLegion\I18n\Translator;

/**
 * MonkeysLegion CLI adapter for I18n commands.
 *
 * Provides CLI entry points for the MonkeysLegion CLI package (`monkeyslegion-cli`).
 *
 * Usage:
 * ```
 * php ml i18n:extract --scan=src --output=lang/template.json
 * php ml i18n:missing --locale=es
 * php ml i18n:compare --from=en --to=es
 * php ml i18n:export --locale=en --format=csv --output=export.csv
 * php ml i18n:compile --locale=en --source=resources/lang --output=cache/i18n
 * php ml i18n:stats
 * ```
 */
final class I18nCliCommand
{
    // ── Properties ────────────────────────────────────────────────

    private readonly TranslationCommand $command;
    private readonly Translator $translator;
    private readonly string $langPath;

    // ── Constructor ───────────────────────────────────────────────

    public function __construct(
        Translator $translator,
        string $langPath,
    ) {
        $this->translator = $translator;
        $this->langPath = $langPath;
        $this->command = new TranslationCommand($translator, $langPath);
    }

    // ── Commands ──────────────────────────────────────────────────

    /**
     * Get all available commands with descriptions.
     *
     * @return array<string, string>
     */
    public function getCommands(): array
    {
        return [
            'i18n:extract' => 'Extract translation keys from source files',
            'i18n:missing' => 'Find missing translations for a locale',
            'i18n:compare' => 'Compare two locales for coverage',
            'i18n:export'  => 'Export translations to JSON/CSV/PHP',
            'i18n:compile' => 'Compile translations for production (opcache)',
            'i18n:stats'   => 'Show translation statistics',
        ];
    }

    /**
     * Run a command by name.
     *
     * @param string               $name Command name (e.g., 'i18n:extract')
     * @param array<string, mixed> $args Command arguments
     */
    public function run(string $name, array $args = []): int
    {
        return match ($name) {
            'i18n:extract' => $this->extract($args),
            'i18n:missing' => $this->missing($args),
            'i18n:compare' => $this->compare($args),
            'i18n:export'  => $this->export($args),
            'i18n:compile' => $this->compile($args),
            'i18n:stats'   => $this->stats(),
            default        => $this->unknownCommand($name),
        };
    }

    // ── Command implementations ───────────────────────────────────

    /**
     * Extract translation keys from source code.
     *
     * @param array<string, mixed> $args {scan: string, output: string}
     */
    private function extract(array $args): int
    {
        $scanPath = (string) ($args['scan'] ?? 'src');
        $output = (string) ($args['output'] ?? 'lang/template.json');

        $this->command->extract($scanPath, $output);

        return 0;
    }

    /**
     * Find missing translations for a locale.
     *
     * @param array<string, mixed> $args {locale: string}
     */
    private function missing(array $args): int
    {
        $locale = (string) ($args['locale'] ?? 'en');

        $this->command->missing($locale);

        return 0;
    }

    /**
     * Compare two locales.
     *
     * @param array<string, mixed> $args {from: string, to: string}
     */
    private function compare(array $args): int
    {
        $from = (string) ($args['from'] ?? 'en');
        $to = (string) ($args['to'] ?? 'es');

        $this->command->compare($from, $to);

        return 0;
    }

    /**
     * Export translations.
     *
     * @param array<string, mixed> $args {locale: string, format: string, output: string}
     */
    private function export(array $args): int
    {
        $locale = (string) ($args['locale'] ?? 'en');
        $format = (string) ($args['format'] ?? 'json');
        $output = (string) ($args['output'] ?? "export.{$format}");

        $this->command->export($locale, $format, $output);

        return 0;
    }

    /**
     * Compile translations for production.
     *
     * @param array<string, mixed> $args {locale: string, source: string, output: string}
     */
    private function compile(array $args): int
    {
        $locale = (string) ($args['locale'] ?? 'en');
        $sourcePath = (string) ($args['source'] ?? $this->langPath);
        $outputPath = (string) ($args['output'] ?? 'cache/i18n');

        echo "Compiling translations for locale: {$locale}\n";
        echo "Source: {$sourcePath}\n";
        echo "Output: {$outputPath}\n";

        $fileLoader = new FileLoader($sourcePath);
        $compiled = new CompiledLoader($fileLoader, $outputPath);

        $path = $compiled->compile($locale, $sourcePath);

        echo "✓ Compiled to: {$path}\n";

        // Verify freshness
        $fresh = $compiled->isFresh($locale, $sourcePath);
        echo $fresh ? "✓ Cache is fresh\n" : "⚠ Cache may be stale\n";

        return 0;
    }

    /**
     * Show translation statistics.
     */
    private function stats(): int
    {
        echo "Translation Statistics\n";
        echo str_repeat('─', 40) . "\n";

        // Count files per locale
        $locales = [];

        if (is_dir($this->langPath)) {
            $dirs = glob($this->langPath . '/*', GLOB_ONLYDIR);

            if ($dirs !== false) {
                foreach ($dirs as $dir) {
                    $locale = basename($dir);
                    $files = glob($dir . '/*.{json,php,mlc}', GLOB_BRACE);
                    $count = $files !== false ? count($files) : 0;
                    $locales[$locale] = $count;
                }
            }
        }

        if (empty($locales)) {
            echo "No translation files found in: {$this->langPath}\n";

            return 0;
        }

        echo sprintf("%-10s %s\n", 'Locale', 'Files');
        echo str_repeat('─', 20) . "\n";

        foreach ($locales as $locale => $count) {
            echo sprintf("%-10s %d\n", $locale, $count);
        }

        echo str_repeat('─', 20) . "\n";
        echo sprintf("%-10s %d\n", 'Total', array_sum($locales));

        return 0;
    }

    /**
     * Handle unknown command.
     */
    private function unknownCommand(string $name): int
    {
        echo "Unknown command: {$name}\n\n";
        echo "Available commands:\n";

        foreach ($this->getCommands() as $cmd => $desc) {
            echo sprintf("  %-20s %s\n", $cmd, $desc);
        }

        return 1;
    }
}
