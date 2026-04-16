<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Event;

/**
 * Dispatched when the active locale changes.
 */
final readonly class LocaleChangedEvent
{
    public function __construct(
        public string $previousLocale,
        public string $newLocale,
    ) {}
}
