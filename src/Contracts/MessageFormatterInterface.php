<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Contracts;

/**
 * Interface for message formatters
 */
interface MessageFormatterInterface
{
    /**
     * Format a message with replacements
     * 
     * @param string $message Message template
     * @param array<string, mixed> $replace Replacement values
     * @param string $locale Locale for formatting
     * @return string Formatted message
     */
    public function format(string $message, array $replace = [], string $locale = 'en'): string;
}
