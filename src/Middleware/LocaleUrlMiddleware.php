<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Middleware;

use MonkeysLegion\I18n\LocaleManager;
use MonkeysLegion\I18n\Translator;

/**
 * Middleware to force a specific locale from URL segment.
 *
 * Example: /es/products → forces Spanish
 */
final class LocaleUrlMiddleware
{
    // ── Properties ────────────────────────────────────────────────

    private readonly LocaleManager $manager;
    private readonly Translator $translator;
    private readonly int $segment;

    // ── Constructor ───────────────────────────────────────────────

    public function __construct(
        LocaleManager $manager,
        Translator $translator,
        int $segment = 0,
    ) {
        $this->manager = $manager;
        $this->translator = $translator;
        $this->segment = $segment;
    }

    // ── Handle ────────────────────────────────────────────────────

    public function handle(mixed $request, callable $next): mixed
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', is_string($path) ? $path : '')));

        if (isset($segments[$this->segment])) {
            $locale = strtolower($segments[$this->segment]);

            if ($this->manager->isSupported($locale)) {
                $this->manager->setLocale($locale);
                $this->translator->setLocale($locale);
            }
        }

        return $next($request);
    }
}
