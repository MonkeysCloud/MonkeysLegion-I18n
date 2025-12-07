<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Loaders;

use MonkeysLegion\I18n\Contracts\LoaderInterface;
use RuntimeException;

/**
 * Loads translations from JSON and PHP files
 * 
 * Structure:
 * - resources/lang/{locale}/{group}.json
 * - resources/lang/{locale}/{group}.php
 * - resources/lang/{namespace}/{locale}/{group}.json
 * - resources/lang/{namespace}/{locale}/{group}.php
 */
final class FileLoader implements LoaderInterface
{
    /** @var array<string, string> */
    private array $namespaces = [];
    
    private string $path;

    public function __construct(string $path)
    {
        $this->path = rtrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * {@inheritdoc}
     */
    public function load(string $locale, string $group, ?string $namespace = null): array
    {
        if ($namespace !== null && isset($this->namespaces[$namespace])) {
            return $this->loadPath($this->namespaces[$namespace], $locale, $group);
        }
        
        return $this->loadPath($this->path, $locale, $group);
    }

    /**
     * {@inheritdoc}
     */
    public function addNamespace(string $namespace, string $path): void
    {
        $this->namespaces[$namespace] = rtrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Load translations from a specific path
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
        
        // Try loading from flat structure: path/{locale}.json (for single-file translations)
        return $this->loadFlat($path, $locale, $group);
    }

    /**
     * Load from nested directory structure
     * 
     * @return array<string, mixed>
     */
    private function loadNested(string $path, string $locale, string $group): array
    {
        $directory = $path . DIRECTORY_SEPARATOR . $locale;
        
        if (!is_dir($directory)) {
            return [];
        }
        
        // Try PHP file first
        $phpFile = $directory . DIRECTORY_SEPARATOR . $group . '.php';
        if (is_file($phpFile)) {
            return $this->loadPhpFile($phpFile);
        }
        
        // Try JSON file
        $jsonFile = $directory . DIRECTORY_SEPARATOR . $group . '.json';
        if (is_file($jsonFile)) {
            return $this->loadJsonFile($jsonFile);
        }
        
        return [];
    }

    /**
     * Load from flat file structure (single file per locale)
     * 
     * @return array<string, mixed>
     */
    private function loadFlat(string $path, string $locale, string $group): array
    {
        // Try PHP file
        $phpFile = $path . DIRECTORY_SEPARATOR . $locale . '.php';
        if (is_file($phpFile)) {
            $data = $this->loadPhpFile($phpFile);
            $groupData = $data[$group] ?? [];

            return is_array($groupData) ? $groupData : [];
        }

        // Try JSON file
        $jsonFile = $path . DIRECTORY_SEPARATOR . $locale . '.json';
        if (is_file($jsonFile)) {
            $data = $this->loadJsonFile($jsonFile);
            $groupData = $data[$group] ?? [];

            return is_array($groupData) ? $groupData : [];
        }
        
        return [];
    }

    /**
     * Load PHP array file
     * 
     * @return array<string, mixed>
     */
    private function loadPhpFile(string $file): array
    {
        $data = require $file;
        
        if (!is_array($data)) {
            throw new RuntimeException("Translation file must return an array: {$file}");
        }
        
        return $data;
    }

    /**
     * Load JSON file
     * 
     * @return array<string, mixed>
     */
    private function loadJsonFile(string $file): array
    {
        $json = file_get_contents($file);
        
        if ($json === false) {
            throw new RuntimeException("Failed to read translation file: {$file}");
        }
        
        $data = json_decode($json, true);
        
        if (!is_array($data)) {
            throw new RuntimeException("Invalid JSON in translation file: {$file}");
        }
        
        return $data;
    }
}
