<?php

declare(strict_types=1);

/**
 * MonkeysLegion I18n
 *
 * @package   MonkeysLegion\I18n
 * @author    MonkeysCloud <jorge@monkeys.cloud>
 * @license   MIT
 *
 * @requires  PHP 8.4
 */

namespace MonkeysLegion\I18n\Contract;

/**
 * Interface for message formatting with parameter replacement.
 */
interface MessageFormatterInterface
{
    /**
     * Format a message with replacement values.
     *
     * @param string               $message Message template
     * @param array<string, mixed> $replace Replacement values
     * @param string               $locale  Locale for locale-specific formatting
     */
    public function format(string $message, array $replace = [], string $locale = 'en'): string;
}
