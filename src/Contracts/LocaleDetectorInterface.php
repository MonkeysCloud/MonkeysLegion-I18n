<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Contracts;

/**
 * Interface for locale detectors
 */
interface LocaleDetectorInterface
{
    /**
     * Detect locale from the current context
     * 
     * @return string|null Detected locale or null if not detected
     */
    public function detect(): ?string;
}
