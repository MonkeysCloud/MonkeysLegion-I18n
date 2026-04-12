<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n;

use MonkeysLegion\I18n\Contract\LocaleDetectorInterface;
use MonkeysLegion\I18n\Exceptions\InvalidLocaleException;
use MonkeysLegion\I18n\Support\LocaleInfo;

/**
 * Manages locale detection, validation, and storage.
 *
 * Uses PHP 8.4 property hooks for locale state management.
 */
final class LocaleManager
{
    // ── Constants ─────────────────────────────────────────────────

    private const string LOCALE_PATTERN = '/^[a-z]{2,3}(_[A-Z]{2})?$/';

    // ── Properties ────────────────────────────────────────────────

    public private(set) string $defaultLocale;
    public private(set) string $fallbackLocale;

    /** @var list<string> */
    public private(set) array $supportedLocales;

    /** @var list<LocaleDetectorInterface> */
    private array $detectors = [];

    private ?string $currentLocale = null;

    // ── Constructor ───────────────────────────────────────────────

    /**
     * @param string       $defaultLocale    Default locale code
     * @param list<string> $supportedLocales List of supported locale codes
     * @param string       $fallbackLocale   Fallback locale code
     */
    public function __construct(
        string $defaultLocale = 'en',
        array $supportedLocales = ['en'],
        string $fallbackLocale = 'en',
    ) {
        $this->defaultLocale = $defaultLocale;
        $this->supportedLocales = $supportedLocales;
        $this->fallbackLocale = $fallbackLocale;
    }

    // ── Detector management ───────────────────────────────────────

    /**
     * Add a locale detector.
     */
    public function addDetector(LocaleDetectorInterface $detector): void
    {
        $this->detectors[] = $detector;
    }

    // ── Detection ─────────────────────────────────────────────────

    /**
     * Detect locale from registered detectors.
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

    // ── Locale state ──────────────────────────────────────────────

    /**
     * Get current locale.
     */
    public function getLocale(): string
    {
        return $this->currentLocale ?? $this->detectLocale();
    }

    /**
     * Set current locale.
     *
     * @throws InvalidLocaleException If locale format is invalid
     * @throws \InvalidArgumentException If locale is not in supported list
     */
    public function setLocale(string $locale): void
    {
        if (!preg_match(self::LOCALE_PATTERN, $locale)) {
            throw new InvalidLocaleException($locale);
        }

        if (!$this->isSupported($locale)) {
            throw new \InvalidArgumentException("Unsupported locale: {$locale}");
        }

        $this->currentLocale = $locale;
    }

    /**
     * Get default locale.
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Get fallback locale.
     */
    public function getFallbackLocale(): string
    {
        return $this->fallbackLocale;
    }

    /**
     * Get all supported locales.
     *
     * @return list<string>
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    // ── Validation ────────────────────────────────────────────────

    /**
     * Check if locale is supported.
     */
    public function isSupported(string $locale): bool
    {
        return in_array($locale, $this->supportedLocales, true);
    }

    /**
     * Add supported locale.
     */
    public function addSupportedLocale(string $locale): void
    {
        if (!in_array($locale, $this->supportedLocales, true)) {
            $this->supportedLocales[] = $locale;
        }
    }

    // ── Locale parsing ────────────────────────────────────────────

    /**
     * Parse locale from various formats.
     *
     * Examples: en-US → en, en_US → en, en-GB → en
     */
    public function parseLocale(string $locale): string
    {
        if (preg_match('/^([a-z]{2,3})[_-]/i', $locale, $matches)) {
            return strtolower($matches[1]);
        }

        return strtolower($locale);
    }

    /**
     * Get locale name in native language.
     */
    public function getLocaleName(string $locale): string
    {
        return LocaleInfo::nativeName($locale);
    }

    /**
     * Reset detected locale (force re-detection).
     */
    public function reset(): void
    {
        $this->currentLocale = null;
    }
}
