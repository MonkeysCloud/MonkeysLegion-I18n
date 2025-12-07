<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Detectors;

use MonkeysLegion\I18n\Contracts\LocaleDetectorInterface;

/**
 * Detects locale from URL path segment
 * Example: /es/products -> 'es'
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

        if (!is_string($path) || $path === '' || $path === '/') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path)));
        
        if (!isset($segments[$this->segment])) {
            return null;
        }
        
        $locale = $segments[$this->segment];
        
        // Validate format (2-3 letter code)
        if (preg_match('/^[a-z]{2,3}$/i', $locale)) {
            return strtolower($locale);
        }
        
        return null;
    }
}
