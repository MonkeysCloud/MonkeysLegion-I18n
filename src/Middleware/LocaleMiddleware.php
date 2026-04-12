<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Middleware;

use MonkeysLegion\I18n\LocaleManager;
use MonkeysLegion\I18n\Translator;

/**
 * Middleware to detect and set locale for each request.
 *
 * Security:
 * - SameSite=Lax on cookies
 * - HttpOnly + Secure flags
 * - Locale validated before use
 */
final class LocaleMiddleware
{
    // ── Properties ────────────────────────────────────────────────

    private readonly LocaleManager $manager;
    private readonly Translator $translator;
    private readonly bool $setSession;
    private readonly bool $setCookie;
    private readonly int $cookieTtl;

    // ── Constructor ───────────────────────────────────────────────

    public function __construct(
        LocaleManager $manager,
        Translator $translator,
        bool $setSession = true,
        bool $setCookie = true,
        int $cookieTtl = 31536000,
    ) {
        $this->manager = $manager;
        $this->translator = $translator;
        $this->setSession = $setSession;
        $this->setCookie = $setCookie;
        $this->cookieTtl = $cookieTtl;
    }

    // ── Handle ────────────────────────────────────────────────────

    /**
     * Handle the request.
     */
    public function handle(mixed $request, callable $next): mixed
    {
        // Detect locale
        $locale = $this->manager->detectLocale();

        // Set locale in translator
        $this->translator->setLocale($locale);

        // Store in session
        if ($this->setSession && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['locale'] = $locale;
        }

        // Store in cookie with security flags
        if ($this->setCookie && !headers_sent()) {
            setcookie(
                'locale',
                $locale,
                [
                    'expires'  => time() + $this->cookieTtl,
                    'path'     => '/',
                    'secure'   => !empty($_SERVER['HTTPS']),
                    'httponly'  => true,
                    'samesite'  => 'Lax',
                ],
            );
        }

        // Continue request
        return $next($request);
    }
}
