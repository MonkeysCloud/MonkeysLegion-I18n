<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Detectors;

use MonkeysLegion\I18n\Contracts\LocaleDetectorInterface;

/**
 * Detects locale from cookie
 */
final class CookieDetector implements LocaleDetectorInterface
{
    private string $name;

    public function __construct(string $name = 'locale')
    {
        $this->name = $name;
    }

    public function detect(): ?string
    {
        $value = $_COOKIE[$this->name] ?? null;

        return is_string($value) ? $value : null;
    }
}
