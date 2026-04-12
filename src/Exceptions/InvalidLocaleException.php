<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when locale code fails validation.
 */
final class InvalidLocaleException extends InvalidArgumentException
{
    public readonly string $locale;

    public function __construct(string $locale)
    {
        $this->locale = $locale;
        parent::__construct("Invalid locale code: '{$locale}'. Expected format: 'xx' or 'xx_XX'.");
    }
}
