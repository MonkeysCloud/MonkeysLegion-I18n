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
 * ICU plural categories.
 *
 * Covers all CLDR plural rules for 200+ languages.
 */
enum PluralCategory: string
{
    case Zero  = 'zero';
    case One   = 'one';
    case Two   = 'two';
    case Few   = 'few';
    case Many  = 'many';
    case Other = 'other';

    /**
     * Whether this is the default/fallback category.
     */
    public function isDefault(): bool
    {
        return $this === self::Other;
    }

    /**
     * Return all categories in CLDR precedence order.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::Zero, self::One, self::Two, self::Few, self::Many, self::Other];
    }
}
