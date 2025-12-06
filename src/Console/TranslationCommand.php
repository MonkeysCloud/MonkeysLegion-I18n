<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Console;

use MonkeysLegion\I18n\Translator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * CLI commands for managing translations
 */
final class TranslationCommand
{
    private Translator $translator;
    private string $path;

    public function __construct(Translator $translator, string $path)
    {
        $this->translator = $translator;
        $this->path = $path;
    }

    /**
     * Extract translation keys from PHP/Blade files
     */
    public function extract(string $scanPath, string $outputFile): void
    {
        echo "Scanning for translation keys in: {$scanPath}\n";
        
        $keys = $this->scanFiles($scanPath);
        
        echo "Found " . count($keys) . " unique translation keys\n";
        
        // Generate template
        $template = $this->generateTemplate($keys);
        
        // Save to file
        file_put_contents($outputFile, json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo "Template saved to: {$outputFile}\n";
    }

    /**
     * Find missing translations
     */
    public function missing(string $locale): void
    {
        echo "Checking for missing translations in locale: {$locale}\n\n";
        
        $this->translator->setTrackMissing(true);
        
        // Load all translation files
        $this->loadAllTranslations($locale);
        
        $missing = $this->translator->getMissingTranslations();
        
        if (empty($missing)) {
            echo "✓ No missing translations found!\n";
            return;
        }
        
        echo "✗ Found " . count($missing) . " missing translations:\n\n";
        
        foreach ($missing as $key) {
            echo "  - {$key}\n";
        }
    }

    /**
     * Compare two locales
     */
    public function compare(string $locale1, string $locale2): void
    {
        echo "Comparing {$locale1} with {$locale2}\n\n";
        
        $keys1 = $this->getLocaleKeys($locale1);
        $keys2 = $this->getLocaleKeys($locale2);
        
        // Keys in locale1 but not in locale2
        $missingInLocale2 = array_diff($keys1, $keys2);
        
        // Keys in locale2 but not in locale1
        $missingInLocale1 = array_diff($keys2, $keys1);
        
        if (empty($missingInLocale1) && empty($missingInLocale2)) {
            echo "✓ Both locales have the same keys!\n";
            return;
        }
        
        if (!empty($missingInLocale2)) {
            echo "Missing in {$locale2} (" . count($missingInLocale2) . "):\n";
            foreach (array_slice($missingInLocale2, 0, 10) as $key) {
                echo "  - {$key}\n";
            }
            if (count($missingInLocale2) > 10) {
                echo "  ... and " . (count($missingInLocale2) - 10) . " more\n";
            }
            echo "\n";
        }
        
        if (!empty($missingInLocale1)) {
            echo "Missing in {$locale1} (" . count($missingInLocale1) . "):\n";
            foreach (array_slice($missingInLocale1, 0, 10) as $key) {
                echo "  - {$key}\n";
            }
            if (count($missingInLocale1) > 10) {
                echo "  ... and " . (count($missingInLocale1) - 10) . " more\n";
            }
        }
    }

    /**
     * Export translations to different formats
     */
    public function export(string $locale, string $format, string $outputFile): void
    {
        echo "Exporting {$locale} to {$format} format...\n";
        
        $translations = $this->loadAllTranslations($locale);
        
        switch ($format) {
            case 'json':
                $content = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
                
            case 'php':
                $content = "<?php\n\nreturn " . var_export($translations, true) . ";\n";
                break;
                
            case 'csv':
                $content = $this->exportToCsv($translations);
                break;
                
            default:
                echo "Unsupported format: {$format}\n";
                return;
        }
        
        file_put_contents($outputFile, $content);
        echo "Exported to: {$outputFile}\n";
    }

    /**
     * Scan files for translation keys
     * 
     * @return array<string>
     */
    private function scanFiles(string $path): array
    {
        $keys = [];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );
        
        $phpFiles = new RegexIterator($iterator, '/^.+\.(php|blade\.php)$/i');
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file->getPathname());
            
            // Match trans('key'), __('key'), @lang('key')
            preg_match_all(
                '/(trans|__|trans_choice|@lang|@choice)\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
                $content,
                $matches
            );
            
            if (!empty($matches[2])) {
                $keys = array_merge($keys, $matches[2]);
            }
        }
        
        return array_unique($keys);
    }

    /**
     * Generate template from keys
     * 
     * @param array<string> $keys
     * @return array<string, mixed>
     */
    private function generateTemplate(array $keys): array
    {
        $template = [];
        
        foreach ($keys as $key) {
            $this->setNestedKey($template, $key, $key);
        }
        
        return $template;
    }

    /**
     * Set nested key in array
     * 
     * @param array<string, mixed> $array
     */
    private function setNestedKey(array &$array, string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $current = &$array;
        
        foreach ($parts as $part) {
            if (!isset($current[$part])) {
                $current[$part] = [];
            }
            $current = &$current[$part];
        }
        
        $current = $value;
    }

    /**
     * Load all translations for a locale
     * 
     * @return array<string, mixed>
     */
    private function loadAllTranslations(string $locale): array
    {
        $translations = [];
        $localePath = $this->path . DIRECTORY_SEPARATOR . $locale;
        
        if (!is_dir($localePath)) {
            return $translations;
        }
        
        $files = glob($localePath . '/*.{json,php}', GLOB_BRACE);
        
        foreach ($files as $file) {
            $group = pathinfo($file, PATHINFO_FILENAME);
            
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $data = json_decode(file_get_contents($file), true);
            } else {
                $data = require $file;
            }
            
            $translations[$group] = $data;
        }
        
        return $translations;
    }

    /**
     * Get all translation keys for a locale
     * 
     * @return array<string>
     */
    private function getLocaleKeys(string $locale): array
    {
        $translations = $this->loadAllTranslations($locale);
        return $this->flattenKeys($translations);
    }

    /**
     * Flatten nested array keys
     * 
     * @param array<string, mixed> $array
     * @param string $prefix
     * @return array<string>
     */
    private function flattenKeys(array $array, string $prefix = ''): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;
            
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenKeys($value, $newKey));
            } else {
                $result[] = $newKey;
            }
        }
        
        return $result;
    }

    /**
     * Export translations to CSV
     * 
     * @param array<string, mixed> $translations
     */
    private function exportToCsv(array $translations): string
    {
        $csv = "key,value\n";
        $flat = $this->flattenArray($translations);
        
        foreach ($flat as $key => $value) {
            $csv .= "\"{$key}\",\"" . str_replace('"', '""', (string)$value) . "\"\n";
        }
        
        return $csv;
    }

    /**
     * Flatten nested array
     * 
     * @param array<string, mixed> $array
     * @param string $prefix
     * @return array<string, string>
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;
            
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = (string)$value;
            }
        }
        
        return $result;
    }
}
