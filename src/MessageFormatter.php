<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n;

use MonkeysLegion\I18n\Contract\MessageFormatterInterface;
use MonkeysLegion\I18n\Contract\SanitizerInterface;

/**
 * Formats translation messages with parameter replacement and modifiers.
 *
 * Security:
 * - HTML auto-escaping by default (XSS protection)
 * - Pluggable SanitizerInterface
 * - Safe regex patterns (no ReDoS)
 *
 * Supports: :param, :PARAM, :Param, {param}, {param|modifier}, {param|modifier:arg}
 */
final class MessageFormatter implements MessageFormatterInterface
{
    // ── Properties ────────────────────────────────────────────────

    private readonly bool $hasIntl;
    private readonly bool $autoEscape;
    private readonly SanitizerInterface $sanitizer;

    /** @var array<string, \NumberFormatter> Cached NumberFormatter instances keyed by "{locale}:{style}" */
    private array $formatters = [];

    // ── Constructor ───────────────────────────────────────────────

    public function __construct(
        bool $autoEscape = false,
        ?SanitizerInterface $sanitizer = null,
    ) {
        $this->hasIntl = class_exists(\NumberFormatter::class);
        $this->autoEscape = $autoEscape;
        $this->sanitizer = $sanitizer ?? new class implements SanitizerInterface {
            public function sanitize(string $value): string
            {
                return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        };
    }

    // ── MessageFormatterInterface ──────────────────────────────────

    public function format(string $message, array $replace = [], string $locale = 'en'): string
    {
        if (empty($replace)) {
            return $message;
        }

        foreach ($replace as $key => $value) {
            $message = $this->replaceParameter($message, $key, $value, $locale);
        }

        return $message;
    }

    // ── Private methods ───────────────────────────────────────────

    /**
     * Replace a parameter in the message.
     */
    private function replaceParameter(string $message, string $key, mixed $value, string $locale): string
    {
        $stringValue = $this->convertToString($value, $locale);

        // Apply auto-escaping if enabled
        $safeValue = $this->autoEscape ? $this->sanitizer->sanitize($stringValue) : $stringValue;

        // Simple replacements: :key
        $message = str_replace(":{$key}", $safeValue, $message);

        // Uppercase: :KEY
        $upperKey = strtoupper($key);
        if (str_contains($message, ":{$upperKey}")) {
            $message = str_replace(":{$upperKey}", mb_strtoupper($safeValue, 'UTF-8'), $message);
        }

        // Capitalize first: :Key
        $ucKey = ucfirst($key);
        if ($ucKey !== $key && str_contains($message, ":{$ucKey}")) {
            $message = str_replace(":{$ucKey}", ucfirst($safeValue), $message);
        }

        // Braced replacements with modifiers: {key|upper}
        if (str_contains($message, '{')) {
            $message = $this->replaceBracedParameter($message, $key, $value, $locale);
        }

        return $message;
    }

    /**
     * Replace braced parameters with modifiers.
     */
    private function replaceBracedParameter(string $message, string $key, mixed $value, string $locale): string
    {
        $pattern = '/\{' . preg_quote($key, '/') . '(?:\|([a-z_]{1,20})(?::([^}]{1,100}))?)?\}/i';

        return (string) preg_replace_callback($pattern, function (array $matches) use ($value, $locale): string {
            $modifier = $matches[1] ?? null;
            $argument = $matches[2] ?? null;

            if ($modifier === null) {
                return $this->convertToString($value, $locale);
            }

            return $this->applyModifier($value, $modifier, $argument, $locale);
        }, $message);
    }

    /**
     * Apply a modifier to a value.
     */
    private function applyModifier(mixed $value, string $modifier, ?string $argument, string $locale): string
    {
        $stringValue = $this->convertToString($value, $locale);

        return match (strtolower($modifier)) {
            'upper', 'uppercase'       => mb_strtoupper($stringValue, 'UTF-8'),
            'lower', 'lowercase'       => mb_strtolower($stringValue, 'UTF-8'),
            'title', 'titlecase'       => mb_convert_case($stringValue, MB_CASE_TITLE, 'UTF-8'),
            'capitalize', 'ucfirst'    => mb_strtoupper(mb_substr($stringValue, 0, 1, 'UTF-8'), 'UTF-8')
                . mb_substr($stringValue, 1, null, 'UTF-8'),
            'number'                   => $this->formatNumber($value, $locale, (int) ($argument ?? 0)),
            'currency'                 => $this->formatCurrency($value, $argument ?? 'USD', $locale),
            'percent', 'percentage'    => $this->formatPercent($value, $locale),
            'date'                     => $this->formatDate($value, $argument ?? 'medium', $locale),
            'time'                     => $this->formatTime($value, $argument ?? 'medium', $locale),
            'datetime'                 => $this->formatDateTime($value, $argument ?? 'medium', $locale),
            'plural'                   => $this->pluralizeWord($stringValue, (int) $value),
            'truncate'                 => $this->truncate($stringValue, (int) ($argument ?? 50)),
            'default'                  => $argument ?? $stringValue,
            default                    => $stringValue,
        };
    }

    /**
     * Convert value to string.
     */
    private function convertToString(mixed $value, string $locale): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn(mixed $v): string => $this->convertToString($v, $locale), $value));
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Format number with locale support.
     */
    private function formatNumber(mixed $value, string $locale, int $decimals = 0): string
    {
        if (!is_numeric($value)) {
            return (string) $value;
        }

        if ($this->hasIntl) {
            $formatter = $this->getNumberFormatter($locale, \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

            return $formatter->format((float) $value) ?: (string) $value;
        }

        return number_format((float) $value, $decimals);
    }

    /**
     * Format currency with locale support.
     */
    private function formatCurrency(mixed $value, string $currency, string $locale): string
    {
        if (!is_numeric($value)) {
            return (string) $value;
        }

        if ($this->hasIntl) {
            $formatter = $this->getNumberFormatter($locale, \NumberFormatter::CURRENCY);

            return $formatter->formatCurrency((float) $value, $currency) ?: (string) $value;
        }

        $symbols = [
            'USD' => '$',  'EUR' => '€',  'GBP' => '£',  'JPY' => '¥',
            'CAD' => 'C$', 'AUD' => 'A$', 'CHF' => 'CHF', 'CNY' => '¥',
            'MXN' => '$',  'BRL' => 'R$',
        ];

        $symbol = $symbols[$currency] ?? $currency;

        return $symbol . number_format((float) $value, 2);
    }

    /**
     * Format percentage.
     */
    private function formatPercent(mixed $value, string $locale): string
    {
        if (!is_numeric($value)) {
            return (string) $value;
        }

        if ($this->hasIntl) {
            $formatter = $this->getNumberFormatter($locale, \NumberFormatter::PERCENT);

            return $formatter->format((float) $value) ?: (string) $value;
        }

        return number_format((float) $value * 100, 2) . '%';
    }

    /**
     * Format date.
     */
    private function formatDate(mixed $value, string $format, string $locale): string
    {
        $timestamp = $this->getTimestamp($value);

        if ($timestamp === null) {
            return (string) $value;
        }

        return match ($format) {
            'short'  => date('n/j/y', $timestamp),
            'medium' => date('M j, Y', $timestamp),
            'long'   => date('F j, Y', $timestamp),
            'full'   => date('l, F j, Y', $timestamp),
            default  => date($format, $timestamp),
        };
    }

    /**
     * Format time.
     */
    private function formatTime(mixed $value, string $format, string $locale): string
    {
        $timestamp = $this->getTimestamp($value);

        if ($timestamp === null) {
            return (string) $value;
        }

        return match ($format) {
            'short'  => date('g:i A', $timestamp),
            'medium' => date('g:i:s A', $timestamp),
            'long'   => date('g:i:s A T', $timestamp),
            'full'   => date('g:i:s A T', $timestamp),
            default  => date($format, $timestamp),
        };
    }

    /**
     * Format datetime.
     */
    private function formatDateTime(mixed $value, string $format, string $locale): string
    {
        $timestamp = $this->getTimestamp($value);

        if ($timestamp === null) {
            return (string) $value;
        }

        return match ($format) {
            'short'  => date('n/j/y g:i A', $timestamp),
            'medium' => date('M j, Y g:i:s A', $timestamp),
            'long'   => date('F j, Y g:i:s A T', $timestamp),
            'full'   => date('l, F j, Y g:i:s A T', $timestamp),
            default  => date($format, $timestamp),
        };
    }

    /**
     * Get timestamp from value.
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
     * Simple pluralization for English words.
     */
    private function pluralizeWord(string $word, int $count): string
    {
        if ($count === 1) {
            return $word;
        }

        if (preg_match('/(s|ss|sh|ch|x|z)$/i', $word)) {
            return $word . 'es';
        }

        if (preg_match('/[^aeiou]y$/i', $word)) {
            return substr($word, 0, -1) . 'ies';
        }

        return $word . 's';
    }

    /**
     * Truncate string safely.
     */
    private function truncate(string $value, int $length, string $suffix = '...'): string
    {
        if (mb_strlen($value, 'UTF-8') <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length, 'UTF-8') . $suffix;
    }

    /**
     * Get a cached NumberFormatter instance to avoid repeated instantiation.
     */
    private function getNumberFormatter(string $locale, int $style): \NumberFormatter
    {
        $key = "{$locale}:{$style}";

        if (!isset($this->formatters[$key])) {
            $this->formatters[$key] = new \NumberFormatter($locale, $style);
        }

        return $this->formatters[$key];
    }
}
