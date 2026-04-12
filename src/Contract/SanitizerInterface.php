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
 * Interface for HTML sanitization/escaping strategy.
 */
interface SanitizerInterface
{
    /**
     * Sanitize a value for safe HTML output.
     */
    public function sanitize(string $value): string;
}
