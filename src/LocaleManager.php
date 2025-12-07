<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n;

use MonkeysLegion\I18n\Contracts\LocaleDetectorInterface;

/**
 * Manages locale detection and validation
 */
final class LocaleManager
{
    private string $defaultLocale;
    private string $fallbackLocale;
    
    /** @var array<string> */
    private array $supportedLocales;
    
    /** @var LocaleDetectorInterface[] */
    private array $detectors = [];
    
    private ?string $currentLocale = null;

    /**
     * @param string $defaultLocale Default locale
     * @param array<string> $supportedLocales List of supported locale codes
     * @param string $fallbackLocale Fallback locale
     */
    public function __construct(
        string $defaultLocale = 'en',
        array $supportedLocales = ['en'],
        string $fallbackLocale = 'en'
    ) {
        $this->defaultLocale = $defaultLocale;
        $this->supportedLocales = $supportedLocales;
        $this->fallbackLocale = $fallbackLocale;
    }

    /**
     * Add a locale detector
     */
    public function addDetector(LocaleDetectorInterface $detector): void
    {
        $this->detectors[] = $detector;
    }

    /**
     * Detect locale from registered detectors
     */
    public function detectLocale(): string
    {
        if ($this->currentLocale !== null) {
            return $this->currentLocale;
        }
        
        foreach ($this->detectors as $detector) {
            $locale = $detector->detect();
            
            if ($locale !== null && $this->isSupported($locale)) {
                $this->currentLocale = $locale;
                return $locale;
            }
        }
        
        $this->currentLocale = $this->defaultLocale;
        return $this->defaultLocale;
    }

    /**
     * Get current locale
     */
    public function getLocale(): string
    {
        return $this->currentLocale ?? $this->detectLocale();
    }

    /**
     * Set current locale
     */
    public function setLocale(string $locale): void
    {
        if (!$this->isSupported($locale)) {
            throw new \InvalidArgumentException("Unsupported locale: {$locale}");
        }
        
        $this->currentLocale = $locale;
    }

    /**
     * Get default locale
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Get fallback locale
     */
    public function getFallbackLocale(): string
    {
        return $this->fallbackLocale;
    }

    /**
     * Get all supported locales
     * 
     * @return array<string>
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    /**
     * Check if locale is supported
     */
    public function isSupported(string $locale): bool
    {
        return in_array($locale, $this->supportedLocales, true);
    }

    /**
     * Add supported locale
     */
    public function addSupportedLocale(string $locale): void
    {
        if (!in_array($locale, $this->supportedLocales, true)) {
            $this->supportedLocales[] = $locale;
        }
    }

    /**
     * Parse locale from various formats
     * Examples: en-US -> en, en_US -> en, en-GB -> en
     */
    public function parseLocale(string $locale): string
    {
        // Extract language code (first part before - or _)
        if (preg_match('/^([a-z]{2})[_-]/i', $locale, $matches)) {
            return strtolower($matches[1]);
        }
        
        return strtolower($locale);
    }

    /**
     * Get locale name in native language
     */
    public function getLocaleName(string $locale): string
    {
        $names = [
            'en' => 'English',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'it' => 'Italiano',
            'pt' => 'Português',
            'ru' => 'Русский',
            'ja' => '日本語',
            'ko' => '한국어',
            'zh' => '中文',
            'ar' => 'العربية',
            'hi' => 'हिन्दी',
            'nl' => 'Nederlands',
            'pl' => 'Polski',
            'tr' => 'Türkçe',
        ];
        
        return $names[$locale] ?? $locale;
    }
}
