<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Detectors;

use MonkeysLegion\I18n\Contract\LocaleDetectorInterface;

/**
 * Detects locale from query string parameter.
 */
final class QueryDetector implements LocaleDetectorInterface
{
    private string $paramName;

    public function __construct(string $paramName = 'lang')
    {
        $this->paramName = $paramName;
    }

    public function detect(): ?string
    {
        $locale = $_GET[$this->paramName] ?? null;

        if (!is_string($locale) || $locale === '') {
            return null;
        }

        // Sanitize
        $locale = preg_replace('/[^a-zA-Z_]/', '', $locale);

        return is_string($locale) && $locale !== '' ? strtolower($locale) : null;
    }
}
