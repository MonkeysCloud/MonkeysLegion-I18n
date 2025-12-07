<?php

declare(strict_types=1);

use MonkeysLegion\I18n\Translator;

if (!function_exists('trans')) {
    /**
     * Translate a message
     * 
     * @param string $key Translation key
     * @param array<string, mixed> $replace Replacement values
     * @param string|null $locale Override locale
     */
    function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        static $translator = null;
        
        if ($translator === null) {
            // Get from global container if available
            if (function_exists('app') && app()->has(Translator::class)) {
                $translator = app()->get(Translator::class);
            } else {
                throw new RuntimeException('Translator not initialized. Please set up the translator first.');
            }
        }
        
        return $translator->trans($key, $replace, $locale);
    }
}

if (!function_exists('trans_choice')) {
    /**
     * Translate with pluralization
     * 
     * @param string $key Translation key
     * @param int|float $count Count for pluralization
     * @param array<string, mixed> $replace Additional replacements
     * @param string|null $locale Override locale
     */
    function trans_choice(string $key, int|float $count, array $replace = [], ?string $locale = null): string
    {
        static $translator = null;
        
        if ($translator === null) {
            if (function_exists('app') && app()->has(Translator::class)) {
                $translator = app()->get(Translator::class);
            } else {
                throw new RuntimeException('Translator not initialized. Please set up the translator first.');
            }
        }
        
        return $translator->choice($key, $count, $replace, $locale);
    }
}

if (!function_exists('__')) {
    /**
     * Alias for trans() - shorter syntax
     * 
     * @param string $key Translation key
     * @param array<string, mixed> $replace Replacement values
     * @param string|null $locale Override locale
     */
    function __(string $key, array $replace = [], ?string $locale = null): string
    {
        return trans($key, $replace, $locale);
    }
}

if (!function_exists('lang')) {
    /**
     * Get or set current locale
     * 
     * @param string|null $locale Locale to set (null to get current)
     */
    function lang(?string $locale = null): string
    {
        static $translator = null;
        
        if ($translator === null) {
            if (function_exists('app') && app()->has(Translator::class)) {
                $translator = app()->get(Translator::class);
            } else {
                throw new RuntimeException('Translator not initialized.');
            }
        }
        
        if ($locale !== null) {
            $translator->setLocale($locale);
        }
        
        return $translator->getLocale();
    }
}
