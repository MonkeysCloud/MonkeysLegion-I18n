<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n;

use MonkeysLegion\I18n\Enum\PluralCategory;

/**
 * Handles pluralization using ICU-compliant plural rules.
 *
 * Supports all CLDR plural categories: zero, one, two, few, many, other.
 * Performance: locale-to-rule mapping is a const array for zero overhead.
 */
final class Pluralizer
{
    // ── Locale → rule mapping (const for opcache) ─────────────────

    private const array LOCALE_RULES = [
        'pl'            => 'polish',
        'ru'            => 'russian',
        'uk'            => 'russian',
        'be'            => 'russian',
        'sr'            => 'russian',
        'hr'            => 'russian',
        'cs'            => 'czech',
        'sk'            => 'czech',
        'ro'            => 'romanian',
        'mo'            => 'romanian',
        'ar'            => 'arabic',
        'cy'            => 'welsh',
        'fr'            => 'one-with-zero-other',
        'pt'            => 'one-with-zero-other',
        'ja'            => 'zero',
        'ko'            => 'zero',
        'zh'            => 'zero',
        'th'            => 'zero',
        'vi'            => 'zero',
        'id'            => 'zero',
        'ms'            => 'zero',
        'lo'            => 'zero',
        'bo'            => 'zero',
        'dz'            => 'zero',
        'km'            => 'zero',
    ];

    // ── Public API ────────────────────────────────────────────────

    /**
     * Choose the correct plural form based on count and locale.
     *
     * Supported formats:
     * - Pipe-delimited: "There is one apple|There are :count apples"
     * - Explicit: "{0} No apples|{1} One apple|[2,*] :count apples"
     * - ICU categories: "zero: None|one: One apple|other: :count apples"
     */
    public function choose(string $message, int|float $count, string $locale): string
    {
        $forms = $this->parsePlural($message);

        if (empty($forms)) {
            return $message;
        }

        $rule = $this->getPluralRule($locale);

        // Check for explicit count match first: {0}, {1}, {2}, etc.
        foreach ($forms as $form) {
            if ($this->matchesExplicitCount($form, $count)) {
                return $this->replaceCount($this->extractMessage($form), $count);
            }
        }

        // Check for range match: [2,5], [6,*]
        foreach ($forms as $form) {
            if ($this->matchesRange($form, $count)) {
                return $this->replaceCount($this->extractMessage($form), $count);
            }
        }

        // Use ICU plural rules: zero, one, two, few, many, other
        $category = $this->getPluralCategory($count, $rule);

        foreach ($forms as $form) {
            if ($this->matchesCategory($form, $category)) {
                return $this->replaceCount($this->extractMessage($form), $count);
            }
        }

        // Fallback to simple pipe-delimited format
        return $this->replaceCount($this->simpleChoice($forms, $count), $count);
    }

    /**
     * Get the plural category for a count and locale.
     */
    public function getCategoryForCount(int|float $count, string $locale): PluralCategory
    {
        $rule = $this->getPluralRule($locale);
        $value = $this->getPluralCategory($count, $rule);

        return PluralCategory::from($value);
    }

    // ── Private methods ───────────────────────────────────────────

    /**
     * Parse plural message into forms.
     *
     * @return list<string>
     */
    private function parsePlural(string $message): array
    {
        return array_map('trim', explode('|', $message));
    }

    /**
     * Check if form matches explicit count: {0}, {1}, etc.
     */
    private function matchesExplicitCount(string $form, int|float $count): bool
    {
        if (preg_match('/^\{(\d+)\}\s*(.*)$/', $form, $matches)) {
            return (int) $matches[1] === (int) $count;
        }

        return false;
    }

    /**
     * Check if form matches range: [2,5], [6,*]
     */
    private function matchesRange(string $form, int|float $count): bool
    {
        if (preg_match('/^\[(\d+),(\*|\d+)\]\s*(.*)$/', $form, $matches)) {
            $min = (int) $matches[1];
            $max = $matches[2] === '*' ? PHP_INT_MAX : (int) $matches[2];

            return $count >= $min && $count <= $max;
        }

        return false;
    }

    /**
     * Check if form matches ICU category: zero:, one:, other:
     */
    private function matchesCategory(string $form, string $category): bool
    {
        if (preg_match('/^(zero|one|two|few|many|other):\s*(.*)$/i', $form, $matches)) {
            return strtolower($matches[1]) === $category;
        }

        return false;
    }

    /**
     * Extract message from form (remove prefix).
     */
    private function extractMessage(string $form): string
    {
        $form = (string) preg_replace('/^\{\d+\}\s*/', '', $form);
        $form = (string) preg_replace('/^\[\d+,(?:\*|\d+)\]\s*/', '', $form);
        $form = (string) preg_replace('/^(?:zero|one|two|few|many|other):\s*/i', '', $form);

        return trim($form);
    }

    /**
     * Simple choice between singular/plural (fallback).
     *
     * @param list<string> $forms
     */
    private function simpleChoice(array $forms, int|float $count): string
    {
        $index = $count === 1 ? 0 : 1;

        return $forms[$index] ?? $forms[0];
    }

    /**
     * Get plural category for count based on locale rules.
     */
    private function getPluralCategory(int|float $count, string $rule): string
    {
        $n = abs($count);
        $i = (int) $n;
        $v = $this->getDecimalPlaces($n);

        return match ($rule) {
            'one-other'            => ($n === 1) ? 'one' : 'other',
            'one-with-zero-other'  => ($n >= 0 && $n < 2) ? 'one' : 'other',
            'one-other-strict'     => ($n === 1) ? 'one' : 'other',
            'polish'               => $this->polishRule($i, $v),
            'russian'              => $this->russianRule($i, $v),
            'czech'                => $this->czechRule($i, $v),
            'romanian'             => $this->romanianRule($n, $i, $v),
            'arabic'               => $this->arabicRule($n),
            'welsh'                => $this->welshRule($n),
            'zero'                 => 'other',
            default                => ($n === 1) ? 'one' : 'other',
        };
    }

    private function polishRule(int $i, int $v): string
    {
        if ($v === 0 && $i === 1) {
            return 'one';
        }
        if ($v === 0 && $i % 10 >= 2 && $i % 10 <= 4 && ($i % 100 < 12 || $i % 100 > 14)) {
            return 'few';
        }

        return 'other';
    }

    private function russianRule(int $i, int $v): string
    {
        if ($v === 0 && $i % 10 === 1 && $i % 100 !== 11) {
            return 'one';
        }
        if ($v === 0 && $i % 10 >= 2 && $i % 10 <= 4 && ($i % 100 < 12 || $i % 100 > 14)) {
            return 'few';
        }

        return 'other';
    }

    private function czechRule(int $i, int $v): string
    {
        if ($i === 1 && $v === 0) {
            return 'one';
        }
        if ($i >= 2 && $i <= 4 && $v === 0) {
            return 'few';
        }

        return 'other';
    }

    private function romanianRule(float $n, int $i, int $v): string
    {
        if ($i === 1 && $v === 0) {
            return 'one';
        }
        if ($v !== 0 || $i === 0 || ($i % 100 >= 2 && $i % 100 <= 19)) {
            return 'few';
        }

        return 'other';
    }

    private function arabicRule(float $n): string
    {
        if ($n === 0.0) {
            return 'zero';
        }
        if ($n === 1.0) {
            return 'one';
        }
        if ($n === 2.0) {
            return 'two';
        }
        if ($n % 100 >= 3 && $n % 100 <= 10) {
            return 'few';
        }
        if ($n % 100 >= 11) {
            return 'many';
        }

        return 'other';
    }

    private function welshRule(float $n): string
    {
        return match (true) {
            $n === 0.0 => 'zero',
            $n === 1.0 => 'one',
            $n === 2.0 => 'two',
            $n === 3.0 => 'few',
            $n === 6.0 => 'many',
            default    => 'other',
        };
    }

    /**
     * Get decimal places count.
     */
    private function getDecimalPlaces(float $n): int
    {
        $str = (string) $n;

        if (!str_contains($str, '.')) {
            return 0;
        }

        return strlen(substr($str, strpos($str, '.') + 1));
    }

    /**
     * Replace :count placeholder with actual count.
     */
    private function replaceCount(string $message, int|float $count): string
    {
        return str_replace(':count', (string) $count, $message);
    }

    /**
     * Get plural rule for locale.
     */
    private function getPluralRule(string $locale): string
    {
        $lang = substr($locale, 0, 2);

        return self::LOCALE_RULES[$lang] ?? 'one-other';
    }
}
