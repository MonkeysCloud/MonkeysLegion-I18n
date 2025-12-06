<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Detectors;

use MonkeysLegion\I18n\Contracts\LocaleDetectorInterface;

/**
 * Detects locale from query parameter
 */
final class QueryDetector implements LocaleDetectorInterface
{
    private string $parameter;

    public function __construct(string $parameter = 'lang')
    {
        $this->parameter = $parameter;
    }

    public function detect(): ?string
    {
        $value = $_GET[$this->parameter] ?? null;

        return is_string($value) ? $value : null;
    }
}
