<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Detectors;

use MonkeysLegion\I18n\Contract\LocaleDetectorInterface;

/**
 * Detects locale from Accept-Language header.
 */
final class HeaderDetector implements LocaleDetectorInterface
{
    public function detect(): ?string
    {
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

        if ($header === '') {
            return null;
        }

        // Parse Accept-Language header (e.g. en-US,en;q=0.9,es;q=0.8)
        preg_match_all('/([a-z]{2,3})(?:-[A-Z]{2})?/i', $header, $matches);

        if (empty($matches[1])) {
            return null;
        }

        return strtolower($matches[1][0]);
    }
}
