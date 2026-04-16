<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Detectors;

use MonkeysLegion\I18n\Contract\LocaleDetectorInterface;

/**
 * Detects locale from cookie.
 */
final class CookieDetector implements LocaleDetectorInterface
{
    private string $cookieName;

    public function __construct(string $cookieName = 'locale')
    {
        $this->cookieName = $cookieName;
    }

    public function detect(): ?string
    {
        $locale = $_COOKIE[$this->cookieName] ?? null;

        if (!is_string($locale) || $locale === '') {
            return null;
        }

        // Sanitize cookie value
        $locale = preg_replace('/[^a-zA-Z_]/', '', $locale);

        return is_string($locale) && $locale !== '' ? strtolower($locale) : null;
    }
}
