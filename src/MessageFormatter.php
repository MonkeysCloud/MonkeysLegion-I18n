<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n;

use MonkeysLegion\I18n\Contracts\MessageFormatterInterface;

/**
 * Formats translation messages with parameter replacement and modifiers
 * Supports: :param, :PARAM, :Param, {param}, and format modifiers
 * 
 * Note: ext-intl is optional. If not available, basic formatting fallbacks are used.
 */
final class MessageFormatter implements MessageFormatterInterface
{
    private bool $hasIntl;
    
    public function __construct()
    {
        $this->hasIntl = class_exists('NumberFormatter');
    }
    /**
     * Format a message with replacements
     * 
     * @param string $message Message template
     * @param array<string, mixed> $replace Replacement values
     * @param string $locale Locale for formatting
     */
    public function format(string $message, array $replace = [], string $locale = 'en'): string
    {
        if (empty($replace)) {
            return $message;
        }
        
        // Process replacements
        foreach ($replace as $key => $value) {
            $message = $this->replaceParameter($message, $key, $value, $locale);
        }
        
        return $message;
    }

    /**
     * Replace a parameter in the message
     */
    private function replaceParameter(string $message, string $key, mixed $value, string $locale): string
    {
        // Convert value to string
        $stringValue = $this->convertToString($value, $locale);
        
        // Simple replacements: :key
        $message = str_replace(":{$key}", $stringValue, $message);
        
        // Uppercase: :KEY
        $message = str_replace(':' . strtoupper($key), strtoupper($stringValue), $message);
        
        // Capitalize first: :Key
        $message = str_replace(':' . ucfirst($key), ucfirst($stringValue), $message);
        
        // Braced replacements with modifiers: {key|upper}
        $message = $this->replaceBracedParameter($message, $key, $value, $locale);
        
        return $message;
    }

    /**
     * Replace braced parameters with modifiers
     */
    private function replaceBracedParameter(string $message, string $key, mixed $value, string $locale): string
    {
        // Pattern: {key} or {key|modifier} or {key|modifier:arg}
        $pattern = '/\{' . preg_quote($key, '/') . '(?:\|([a-z_]+)(?::([^}]+))?)?\}/i';
        
        return preg_replace_callback($pattern, function($matches) use ($value, $locale) {
            $modifier = $matches[1] ?? null;
            $argument = $matches[2] ?? null;
            
            if ($modifier === null) {
                return $this->convertToString($value, $locale);
            }
            
            return $this->applyModifier($value, $modifier, $argument, $locale);
        }, $message);
    }

    /**
     * Apply a modifier to a value
     */
    private function applyModifier(mixed $value, string $modifier, ?string $argument, string $locale): string
    {
        $stringValue = $this->convertToString($value, $locale);
        
        return match(strtolower($modifier)) {
            'upper', 'uppercase' => mb_strtoupper($stringValue, 'UTF-8'),
            'lower', 'lowercase' => mb_strtolower($stringValue, 'UTF-8'),
            'title', 'titlecase' => mb_convert_case($stringValue, MB_CASE_TITLE, 'UTF-8'),
            'capitalize', 'ucfirst' => mb_strtoupper(mb_substr($stringValue, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($stringValue, 1, null, 'UTF-8'),
            'number' => $this->formatNumber($value, $locale, (int)($argument ?? 0)),
            'currency' => $this->formatCurrency($value, $argument ?? 'USD', $locale),
            'percent', 'percentage' => $this->formatPercent($value, $locale),
            'date' => $this->formatDate($value, $argument ?? 'medium', $locale),
            'time' => $this->formatTime($value, $argument ?? 'medium', $locale),
            'datetime' => $this->formatDateTime($value, $argument ?? 'medium', $locale),
            'plural' => $this->pluralizeWord($stringValue, (int)$value),
            'truncate' => $this->truncate($stringValue, (int)($argument ?? 50)),
            'default' => $argument ?? $stringValue,
            default => $stringValue
        };
    }

    /**
     * Convert value to string
     */
    private function convertToString(mixed $value, string $locale): string
    {
        if (is_string($value)) {
            return $value;
        }
        
        if (is_numeric($value)) {
            return (string)$value;
        }
        
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_array($value)) {
            return implode(', ', array_map(fn($v) => $this->convertToString($v, $locale), $value));
        }
        
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }
        
        return '';
    }

    /**
     * Format number
     */
    private function formatNumber(mixed $value, string $locale, int $decimals = 0): string
    {
        if (!is_numeric($value)) {
            return (string)$value;
        }
        
        if ($this->hasIntl) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            return $formatter->format((float)$value) ?: (string)$value;
        }
        
        // Fallback without intl
        return number_format((float)$value, $decimals);
    }

    /**
     * Format currency
     */
    private function formatCurrency(mixed $value, string $currency, string $locale): string
    {
        if (!is_numeric($value)) {
            return (string)$value;
        }
        
        if ($this->hasIntl) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            return $formatter->formatCurrency((float)$value, $currency) ?: (string)$value;
        }
        
        // Fallback without intl - simple format
        $symbols = [
            'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥',
            'CAD' => 'C$', 'AUD' => 'A$', 'CHF' => 'CHF', 'CNY' => '¥',
            'MXN' => '$', 'BRL' => 'R$',
        ];
        
        $symbol = $symbols[$currency] ?? $currency;
        return $symbol . number_format((float)$value, 2);
    }

    /**
     * Format percentage
     */
    private function formatPercent(mixed $value, string $locale): string
    {
        if (!is_numeric($value)) {
            return (string)$value;
        }
        
        if ($this->hasIntl) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::PERCENT);
            return $formatter->format((float)$value) ?: (string)$value;
        }
        
        // Fallback without intl
        return number_format((float)$value * 100, 2) . '%';
    }

    /**
     * Format date
     */
    private function formatDate(mixed $value, string $format, string $locale): string
    {
        $timestamp = $this->getTimestamp($value);
        
        if ($timestamp === null) {
            return (string)$value;
        }
        
        return match($format) {
            'short' => date('n/j/y', $timestamp),
            'medium' => date('M j, Y', $timestamp),
            'long' => date('F j, Y', $timestamp),
            'full' => date('l, F j, Y', $timestamp),
            default => date($format, $timestamp)
        };
    }

    /**
     * Format time
     */
    private function formatTime(mixed $value, string $format, string $locale): string
    {
        $timestamp = $this->getTimestamp($value);
        
        if ($timestamp === null) {
            return (string)$value;
        }
        
        return match($format) {
            'short' => date('g:i A', $timestamp),
            'medium' => date('g:i:s A', $timestamp),
            'long' => date('g:i:s A T', $timestamp),
            'full' => date('g:i:s A T', $timestamp),
            default => date($format, $timestamp)
        };
    }

    /**
     * Format datetime
     */
    private function formatDateTime(mixed $value, string $format, string $locale): string
    {
        $timestamp = $this->getTimestamp($value);
        
        if ($timestamp === null) {
            return (string)$value;
        }
        
        return match($format) {
            'short' => date('n/j/y g:i A', $timestamp),
            'medium' => date('M j, Y g:i:s A', $timestamp),
            'long' => date('F j, Y g:i:s A T', $timestamp),
            'full' => date('l, F j, Y g:i:s A T', $timestamp),
            default => date($format, $timestamp)
        };
    }

    /**
     * Get timestamp from value
     */
    private function getTimestamp(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $timestamp = strtotime($value);
            return $timestamp !== false ? $timestamp : null;
        }
        
        if (is_object($value) && method_exists($value, 'getTimestamp')) {
            return $value->getTimestamp();
        }
        
        return null;
    }

    /**
     * Simple pluralization for English words
     */
    private function pluralizeWord(string $word, int $count): string
    {
        if ($count === 1) {
            return $word;
        }
        
        // Simple English pluralization rules
        if (preg_match('/(s|ss|sh|ch|x|z)$/i', $word)) {
            return $word . 'es';
        }
        
        if (preg_match('/[^aeiou]y$/i', $word)) {
            return substr($word, 0, -1) . 'ies';
        }
        
        return $word . 's';
    }

    /**
     * Truncate string
     */
    private function truncate(string $value, int $length, string $suffix = '...'): string
    {
        if (mb_strlen($value, 'UTF-8') <= $length) {
            return $value;
        }
        
        return mb_substr($value, 0, $length, 'UTF-8') . $suffix;
    }
}
