<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Exceptions;

use RuntimeException;

/**
 * Exception thrown when a translation is not found
 */
class TranslationNotFoundException extends RuntimeException
{
    private string $key;
    private string $locale;

    public function __construct(string $key, string $locale)
    {
        $this->key = $key;
        $this->locale = $locale;
        
        $message = "Translation not found: '{$key}' for locale '{$locale}'";
        parent::__construct($message);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
