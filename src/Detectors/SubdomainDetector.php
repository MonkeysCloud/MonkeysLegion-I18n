<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Detectors;

use MonkeysLegion\I18n\Contracts\LocaleDetectorInterface;

/**
 * Detects locale from subdomain
 * Example: es.example.com -> 'es'
 */
final class SubdomainDetector implements LocaleDetectorInterface
{
    public function detect(): ?string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        if ($host === '') {
            return null;
        }
        
        $parts = explode('.', $host);
        
        if (count($parts) < 3) {
            return null;
        }
        
        $subdomain = $parts[0];
        
        // Validate format (2-3 letter code)
        if (preg_match('/^[a-z]{2,3}$/i', $subdomain)) {
            return strtolower($subdomain);
        }
        
        return null;
    }
}
