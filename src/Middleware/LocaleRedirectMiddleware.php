<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Middleware;

use MonkeysLegion\I18n\LocaleManager;

/**
 * Middleware to redirect to localized URL if locale prefix is missing.
 *
 * Example: /products → 302 redirect to /en/products
 *
 * Returns a redirect response array instead of calling exit().
 */
final class LocaleRedirectMiddleware
{
    // ── Properties ────────────────────────────────────────────────

    private readonly LocaleManager $manager;
    private readonly int $segment;

    // ── Constructor ───────────────────────────────────────────────

    public function __construct(
        LocaleManager $manager,
        int $segment = 0,
    ) {
        $this->manager = $manager;
        $this->segment = $segment;
    }

    // ── Handle ────────────────────────────────────────────────────

    /**
     * @return mixed|array{redirect: string, status: int} Redirect array or next handler result
     */
    public function handle(mixed $request, callable $next): mixed
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';
        $segments = array_values(array_filter(explode('/', is_string($path) ? $path : '')));

        // Check if locale is in URL
        $hasLocale = isset($segments[$this->segment])
            && $this->manager->isSupported(strtolower($segments[$this->segment]));

        if (!$hasLocale) {
            $locale = $this->manager->detectLocale();
            $newPath = '/' . $locale . (is_string($path) ? $path : '/');

            // Return redirect response instead of calling exit()
            if (!headers_sent()) {
                header("Location: {$newPath}", true, 302);
            }

            return ['redirect' => $newPath, 'status' => 302];
        }

        return $next($request);
    }
}
