<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n;

use RuntimeException;

/**
 * Loads locale files and provides translation with fallback.
 * Locale files are JSON under resources/lang/{locale}.json
 */
final class Translator
{
    private string $locale;
    private string $fallback;
    private string $path;
    private array $messages = [];

    /**
     * @param string $locale    Current locale (e.g. 'en')
     * @param string $path      Directory of JSON files
     * @param string $fallback  Fallback locale if key missing
     */
    public function __construct(string $locale, string $path, string $fallback = 'en')
    {
        $this->locale   = $locale;
        $this->fallback = $fallback;
        $this->path     = rtrim($path, DIRECTORY_SEPARATOR);

        // Load fallback first, then override with desired locale
        $this->loadLocale($this->fallback);
        if ($this->locale !== $this->fallback) {
            $this->loadLocale($this->locale);
        }
    }

    /**
     * Load messages from a JSON file for a given locale.
     */
    private function loadLocale(string $locale): void
    {
        $file = $this->path . DIRECTORY_SEPARATOR . $locale . '.json';
        if (!is_file($file)) {
            return;
        }
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException("Invalid translations in $file");
        }
        $this->messages = array_merge($this->messages, $data);
    }

    /**
     * Translate a message key with optional replacements.
     * Placeholders in messages use :key syntax.
     */
    public function trans(string $key, array $replace = []): string
    {
        $msg = $this->messages[$key] ?? $key;
        foreach ($replace as $k => $v) {
            $msg = str_replace(":$k", (string)$v, $msg);
        }
        return $msg;
    }

    /**
     * Change the current locale and reload messages.
     */
    public function setLocale(string $locale): void
    {
        $this->locale   = $locale;
        $this->messages = [];
        $this->loadLocale($this->fallback);
        if ($this->locale !== $this->fallback) {
            $this->loadLocale($this->locale);
        }
    }

    /**
     * Get the currently active locale.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }
}