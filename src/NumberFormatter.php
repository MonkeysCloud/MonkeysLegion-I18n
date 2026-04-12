<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n;

/**
 * Locale-aware number formatting.
 *
 * Supports: decimal, currency, percentage, compact notation, ordinals.
 * Falls back gracefully when ext-intl is not available.
 */
final class NumberFormatter
{
    // ── Properties ────────────────────────────────────────────────

    private readonly bool $hasIntl;

    // ── Currency symbols ──────────────────────────────────────────

    private const array CURRENCY_SYMBOLS = [
        'USD' => '$',  'EUR' => '€',  'GBP' => '£',  'JPY' => '¥',
        'CAD' => 'C$', 'AUD' => 'A$', 'CHF' => 'CHF', 'CNY' => '¥',
        'MXN' => '$',  'BRL' => 'R$', 'INR' => '₹',  'KRW' => '₩',
        'RUB' => '₽',  'TRY' => '₺',  'SEK' => 'kr',  'NOK' => 'kr',
        'DKK' => 'kr', 'PLN' => 'zł', 'CZK' => 'Kč',  'HUF' => 'Ft',
        'RON' => 'lei', 'BGN' => 'лв', 'HRK' => 'kn',  'THB' => '฿',
        'PHP' => '₱',  'MYR' => 'RM', 'IDR' => 'Rp',  'VND' => '₫',
        'ZAR' => 'R',  'EGP' => 'E£', 'NGN' => '₦',   'KES' => 'KSh',
        'ARS' => '$',  'CLP' => '$',  'COP' => '$',    'PEN' => 'S/',
    ];

    // ── Constructor ───────────────────────────────────────────────

    public function __construct()
    {
        $this->hasIntl = class_exists(\NumberFormatter::class);
    }

    // ── Formatting methods ────────────────────────────────────────

    /**
     * Format a number with locale-specific thousands separator and decimals.
     */
    public function decimal(float|int $value, string $locale = 'en', int $decimals = 0): string
    {
        if ($this->hasIntl) {
            $f = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            $f->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $f->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

            return $f->format($value) ?: (string) $value;
        }

        return number_format((float) $value, $decimals);
    }

    /**
     * Format as currency.
     */
    public function currency(float|int $value, string $currency = 'USD', string $locale = 'en'): string
    {
        if ($this->hasIntl) {
            $f = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);

            return $f->formatCurrency((float) $value, $currency) ?: (string) $value;
        }

        $symbol = self::CURRENCY_SYMBOLS[$currency] ?? $currency;

        return $symbol . number_format((float) $value, 2);
    }

    /**
     * Format as percentage.
     */
    public function percent(float|int $value, string $locale = 'en', int $decimals = 0): string
    {
        if ($this->hasIntl) {
            $f = new \NumberFormatter($locale, \NumberFormatter::PERCENT);
            $f->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

            return $f->format((float) $value) ?: (string) $value;
        }

        return number_format((float) $value * 100, $decimals) . '%';
    }

    /**
     * Format in compact notation (1.2K, 3.4M, 5.6B).
     */
    public function compact(float|int $value, int $precision = 1): string
    {
        $abs = abs((float) $value);
        $sign = $value < 0 ? '-' : '';

        return match (true) {
            $abs >= 1_000_000_000 => $sign . number_format($abs / 1_000_000_000, $precision) . 'B',
            $abs >= 1_000_000     => $sign . number_format($abs / 1_000_000, $precision) . 'M',
            $abs >= 1_000         => $sign . number_format($abs / 1_000, $precision) . 'K',
            default               => $sign . number_format($abs, 0),
        };
    }

    /**
     * Format as ordinal (1st, 2nd, 3rd — English only for fallback).
     */
    public function ordinal(int $value, string $locale = 'en'): string
    {
        if ($this->hasIntl) {
            $f = new \NumberFormatter($locale, \NumberFormatter::ORDINAL);
            $result = $f->format($value);

            if ($result !== false) {
                return $result;
            }
        }

        // English fallback
        $suffix = match (true) {
            $value % 100 === 11, $value % 100 === 12, $value % 100 === 13 => 'th',
            $value % 10 === 1                                              => 'st',
            $value % 10 === 2                                              => 'nd',
            $value % 10 === 3                                              => 'rd',
            default                                                        => 'th',
        };

        return $value . $suffix;
    }

    /**
     * Format as spelled-out words (e.g. "one hundred twenty-three").
     */
    public function spellOut(int|float $value, string $locale = 'en'): string
    {
        if ($this->hasIntl) {
            $f = new \NumberFormatter($locale, \NumberFormatter::SPELLOUT);

            return $f->format($value) ?: (string) $value;
        }

        return (string) $value;
    }

    /**
     * Format file size in human-readable form.
     */
    public function fileSize(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $abs = abs((float) $bytes);

        if ($abs < 1) {
            return '0 B';
        }

        $power = (int) floor(log($abs, 1024));
        $power = min($power, count($units) - 1);

        return number_format($abs / (1024 ** $power), $precision) . ' ' . $units[$power];
    }
}
