<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Detectors;

use MonkeysLegion\I18n\Contract\LocaleDetectorInterface;

/**
 * Detects locale from subdomain (e.g. es.example.com).
 */
final class SubdomainDetector implements LocaleDetectorInterface
{
    public function detect(): ?string
    {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';

        if ($host === '') {
            return null;
        }

        // Extract first subdomain segment
        $parts = explode('.', $host);

        if (count($parts) < 3) {
            return null;
        }

        $subdomain = strtolower($parts[0]);

        // Validate looks like a locale
        if (preg_match('/^[a-z]{2,3}$/', $subdomain)) {
            return $subdomain;
        }

        return null;
    }
}
