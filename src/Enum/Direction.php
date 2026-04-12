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

namespace MonkeysLegion\I18n\Enum;

/**
 * Text direction for a locale.
 */
enum Direction: string
{
    case LTR = 'ltr';
    case RTL = 'rtl';

    /**
     * Determine direction from a locale code.
     */
    public static function fromLocale(string $locale): self
    {
        $lang = strtolower(substr($locale, 0, 2));

        return match ($lang) {
            'ar', 'he', 'fa', 'ur', 'ps', 'sd', 'yi', 'ku', 'ug', 'dv' => self::RTL,
            default => self::LTR,
        };
    }

    /**
     * CSS dir attribute value.
     */
    public function cssAttribute(): string
    {
        return "dir=\"{$this->value}\"";
    }

    /**
     * Check if direction is RTL.
     */
    public function isRtl(): bool
    {
        return $this === self::RTL;
    }
}
