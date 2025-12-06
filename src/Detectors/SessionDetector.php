<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Detectors;

use MonkeysLegion\I18n\Contracts\LocaleDetectorInterface;

/**
 * Detects locale from session
 */
final class SessionDetector implements LocaleDetectorInterface
{
    private string $key;

    public function __construct(string $key = 'locale')
    {
        $this->key = $key;
    }

    public function detect(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        
        return $_SESSION[$this->key] ?? null;
    }
}
