<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Exceptions;

use RuntimeException;

/**
 * Exception thrown when a locale is not supported
 */
class UnsupportedLocaleException extends RuntimeException
{
    private string $locale;
    
    /** @var array<string> */
    private array $supportedLocales;

    /**
     * @param string $locale
     * @param array<string> $supportedLocales
     */
    public function __construct(string $locale, array $supportedLocales = [])
    {
        $this->locale = $locale;
        $this->supportedLocales = $supportedLocales;
        
        $message = "Unsupported locale: '{$locale}'";
        
        if (!empty($supportedLocales)) {
            $message .= '. Supported locales: ' . implode(', ', $supportedLocales);
        }
        
        parent::__construct($message);
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return array<string>
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }
}
