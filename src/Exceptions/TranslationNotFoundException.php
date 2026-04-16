<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Exceptions;

use RuntimeException;

/**
 * Exception thrown when a translation is not found.
 */
final class TranslationNotFoundException extends RuntimeException
{
    public readonly string $key;
    public readonly string $locale;

    public function __construct(string $key, string $locale)
    {
        $this->key = $key;
        $this->locale = $locale;

        parent::__construct("Translation not found: '{$key}' for locale '{$locale}'");
    }
}
