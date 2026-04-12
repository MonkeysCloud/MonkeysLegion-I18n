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
 * Interface for locale detection strategies.
 */
interface LocaleDetectorInterface
{
    /**
     * Detect the locale from the current request context.
     *
     * @return string|null Detected locale, or null if not determinable
     */
    public function detect(): ?string;
}
