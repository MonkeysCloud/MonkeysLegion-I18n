<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n;

/**
 * Handles pluralization using ICU-compliant plural rules
 * Supports: zero, one, two, few, many, other
 */
final class Pluralizer
{
    /**
     * Choose the correct plural form based on count and locale
     * 
     * Format: "There is one apple|There are :count apples"
     * Complex: "{0} No apples|{1} One apple|[2,*] :count apples"
     * ICU: "{0} No apples|one: One apple|other: :count apples"
     */
    public function choose(string $message, int|float $count, string $locale): string
    {
        // Parse the plural message
        $forms = $this->parsePlural($message);

        if (empty($forms)) {
            return $message;
        }

        // Get the plural rule for this locale
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
     * Parse plural message into forms
     * 
     * @return array<string>
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
            return (int)$matches[1] === (int)$count;
        }
        return false;
    }

    /**
     * Check if form matches range: [2,5], [6,*]
     */
    private function matchesRange(string $form, int|float $count): bool
    {
        if (preg_match('/^\[(\d+),(\*|\d+)\]\s*(.*)$/', $form, $matches)) {
            $min = (int)$matches[1];
            $max = $matches[2] === '*' ? PHP_INT_MAX : (int)$matches[2];
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
     * Extract message from form (remove prefix)
     */
    private function extractMessage(string $form): string
    {
        // Remove {n}, [n,m], or category: prefix
        $form = (string)preg_replace('/^\{(\d+)\}\s*/', '', $form);
        $form = (string)preg_replace('/^\[(\d+),(\*|\d+)\]\s*/', '', $form);
        $form = (string)preg_replace('/^(zero|one|two|few|many|other):\s*/i', '', $form);
        return trim($form);
    }

    /**
     * Simple choice between singular/plural (fallback)
     */
    /**
     * @param array<int, string> $forms
     */
    private function simpleChoice(array $forms, int|float $count): string
    {
        $index = $count === 1 ? 0 : 1;
        return $forms[$index] ?? $forms[0];
    }

    /**
     * Get plural category for count based on locale rules
     */
    private function getPluralCategory(int|float $count, string $rule): string
    {
        $n = abs($count);
        $i = (int)$n;
        $v = $this->getDecimalPlaces($n);
        $f = $this->getFractionalPart($n);

        return match ($rule) {
            // English, German, Dutch, Swedish, Danish, Norwegian, Finnish
            'one-other' => ($n === 1) ? 'one' : 'other',

            // French, Portuguese (Brazil)
            'one-with-zero-other' => ($n >= 0 && $n < 2) ? 'one' : 'other',

            // Spanish, Italian
            'one-other-strict' => ($n === 1) ? 'one' : 'other',

            // Polish
            'polish' => $this->polishRule($n, $i, $v),

            // Russian, Ukrainian, Serbian, Croatian
            'russian' => $this->russianRule($n, $i, $v),

            // Czech, Slovak
            'czech' => $this->czechRule($n, $i, $v),

            // Romanian
            'romanian' => $this->romanianRule($n, $i, $v),

            // Arabic
            'arabic' => $this->arabicRule($n),

            // Welsh
            'welsh' => $this->welshRule($n),

            // Japanese, Korean, Chinese, Thai, Vietnamese
            'zero' => 'other',

            default => ($n === 1) ? 'one' : 'other'
        };
    }

    /**
     * Polish plural rules
     */
    private function polishRule(float $n, int $i, int $v): string
    {
        if ($v === 0 && $i === 1) {
            return 'one';
        }
        if ($v === 0 && $i % 10 >= 2 && $i % 10 <= 4 && ($i % 100 < 12 || $i % 100 > 14)) {
            return 'few';
        }
        return 'other';
    }

    /**
     * Russian plural rules
     */
    private function russianRule(float $n, int $i, int $v): string
    {
        if ($v === 0 && $i % 10 === 1 && $i % 100 !== 11) {
            return 'one';
        }
        if ($v === 0 && $i % 10 >= 2 && $i % 10 <= 4 && ($i % 100 < 12 || $i % 100 > 14)) {
            return 'few';
        }
        return 'other';
    }

    /**
     * Czech plural rules
     */
    private function czechRule(float $n, int $i, int $v): string
    {
        if ($i === 1 && $v === 0) {
            return 'one';
        }
        if ($i >= 2 && $i <= 4 && $v === 0) {
            return 'few';
        }
        return 'other';
    }

    /**
     * Romanian plural rules
     */
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

    /**
     * Arabic plural rules
     */
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

    /**
     * Welsh plural rules
     */
    private function welshRule(float $n): string
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
        if ($n === 3.0) {
            return 'few';
        }
        if ($n === 6.0) {
            return 'many';
        }
        return 'other';
    }

    /**
     * Get decimal places count
     */
    private function getDecimalPlaces(float $n): int
    {
        $str = (string)$n;
        if (strpos($str, '.') === false) {
            return 0;
        }
        return strlen(substr($str, strpos($str, '.') + 1));
    }

    /**
     * Get fractional part
     */
    private function getFractionalPart(float $n): int
    {
        $str = (string)$n;
        if (strpos($str, '.') === false) {
            return 0;
        }
        return (int)substr($str, strpos($str, '.') + 1);
    }

    /**
     * Replace :count placeholder with actual count
     */
    private function replaceCount(string $message, int|float $count): string
    {
        return str_replace(':count', (string)$count, $message);
    }
    /**
     * Get plural rule for locale
     */
    private function getPluralRule(string $locale): string
    {
        // Extract language code
        $lang = substr($locale, 0, 2);

        return match ($lang) {
            'pl' => 'polish',
            'ru', 'uk', 'be', 'sr', 'hr' => 'russian',
            'cs', 'sk' => 'czech',
            'ro', 'mo' => 'romanian',
            'ar' => 'arabic',
            'cy' => 'welsh',
            'fr', 'pt' => 'one-with-zero-other',
            'ja', 'ko', 'zh', 'th', 'vi', 'id', 'ms', 'lo', 'bo', 'dz', 'km' => 'zero',
            default => 'one-other'
        };
    }
}
