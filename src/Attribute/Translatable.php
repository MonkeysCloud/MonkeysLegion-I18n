<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Attribute;

use Attribute;

/**
 * Mark an entity property as translatable.
 *
 * The property value will be resolved via the Translator
 * using the configured locale at access time.
 *
 * ```php
 * #[Translatable(group: 'products', keyPrefix: 'title')]
 * public string $title;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Translatable
{
    public function __construct(
        public string $group = 'messages',
        public string $keyPrefix = '',
        public bool $fallbackToValue = true,
    ) {}
}
