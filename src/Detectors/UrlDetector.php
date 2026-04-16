<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Detectors;

use MonkeysLegion\I18n\Contract\LocaleDetectorInterface;

/**
 * Detects locale from URL path segment.
 */
final class UrlDetector implements LocaleDetectorInterface
{
    private int $segment;

    public function __construct(int $segment = 0)
    {
        $this->segment = $segment;
    }

    public function detect(): ?string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', is_string($path) ? $path : '')));

        if (!isset($segments[$this->segment])) {
            return null;
        }

        $locale = strtolower($segments[$this->segment]);

        // Validate looks like a locale code
        if (preg_match('/^[a-z]{2,3}$/', $locale)) {
            return $locale;
        }

        return null;
    }
}
