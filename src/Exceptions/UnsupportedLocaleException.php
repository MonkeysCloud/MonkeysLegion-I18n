<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Exceptions;

use RuntimeException;

/**
 * Exception thrown for unsupported locales.
 */
final class UnsupportedLocaleException extends RuntimeException
{
    public readonly string $locale;

    public function __construct(string $locale)
    {
        $this->locale = $locale;
        parent::__construct("Unsupported locale: '{$locale}'");
    }
}
