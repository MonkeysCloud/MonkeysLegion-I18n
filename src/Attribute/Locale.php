<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Attribute;

use Attribute;

/**
 * Inject the current detected locale into a controller parameter.
 *
 * ```php
 * public function index(#[Locale] string $locale): Response
 * ```
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Locale
{
    public function __construct(
        public bool $validated = true,
    ) {}
}
