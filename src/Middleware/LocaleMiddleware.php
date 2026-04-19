<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Middleware;

use MonkeysLegion\I18n\LocaleManager;
use MonkeysLegion\I18n\Translator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware to detect and set locale for each request.
 *
 * Security:
 * - SameSite=Lax on cookies
 * - HttpOnly + Secure flags
 * - Locale validated before use
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class LocaleMiddleware implements MiddlewareInterface
{
    // ── Constructor ───────────────────────────────────────────────

    public function __construct(
        private readonly LocaleManager $manager,
        private readonly Translator $translator,
        private readonly bool $setSession = true,
        private readonly bool $setCookie = true,
        private readonly int $cookieTtl = 31536000,
    ) {}

    // ── PSR-15 MiddlewareInterface ────────────────────────────────

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // Detect locale
        $locale = $this->manager->detectLocale();

        // Set locale in translator
        $this->translator->setLocale($locale);

        // Store in session
        if ($this->setSession && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['locale'] = $locale;
        }

        // Process request through the pipeline
        $response = $handler->handle($request);

        // Store locale in cookie with security flags
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

        return $response;
    }
}
