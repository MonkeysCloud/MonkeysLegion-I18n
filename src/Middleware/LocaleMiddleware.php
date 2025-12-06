<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Middleware;

use MonkeysLegion\I18n\LocaleManager;
use MonkeysLegion\I18n\Translator;

/**
 * Middleware to detect and set locale for each request
 */
final class LocaleMiddleware
{
    private LocaleManager $manager;
    private Translator $translator;
    private bool $setSession;
    private bool $setCookie;
    private int $cookieTtl;

    public function __construct(
        LocaleManager $manager,
        Translator $translator,
        bool $setSession = true,
        bool $setCookie = true,
        int $cookieTtl = 31536000 // 1 year
    ) {
        $this->manager = $manager;
        $this->translator = $translator;
        $this->setSession = $setSession;
        $this->setCookie = $setCookie;
        $this->cookieTtl = $cookieTtl;
    }

    /**
     * Handle the request
     */
    public function handle($request, callable $next)
    {
        // Detect locale
        $locale = $this->manager->detectLocale();
        
        // Set locale in translator
        $this->translator->setLocale($locale);
        
        // Store in session
        if ($this->setSession && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['locale'] = $locale;
        }
        
        // Store in cookie
        if ($this->setCookie) {
            setcookie(
                'locale',
                $locale,
                time() + $this->cookieTtl,
                '/',
                '',
                isset($_SERVER['HTTPS']),
                true
            );
        }
        
        // Continue request
        return $next($request);
    }
}

/**
 * Middleware to force a specific locale from URL
 * Example: /es/products -> forces Spanish
 */
final class LocaleUrlMiddleware
{
    private LocaleManager $manager;
    private Translator $translator;
    private int $segment;

    public function __construct(
        LocaleManager $manager,
        Translator $translator,
        int $segment = 0
    ) {
        $this->manager = $manager;
        $this->translator = $translator;
        $this->segment = $segment;
    }

    /**
     * Handle the request
     */
    public function handle($request, callable $next)
    {
        // Get locale from URL
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

/**
 * Middleware to redirect to localized URL if not present
 * Example: /products -> /en/products
 */
final class LocaleRedirectMiddleware
{
    private LocaleManager $manager;
    private int $segment;
    private bool $forceDefault;

    public function __construct(
        LocaleManager $manager,
        int $segment = 0,
        bool $forceDefault = true
    ) {
        $this->manager = $manager;
        $this->segment = $segment;
        $this->forceDefault = $forceDefault;
    }

    /**
     * Handle the request
     */
    public function handle($request, callable $next)
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';
        $segments = array_values(array_filter(explode('/', is_string($path) ? $path : '')));
        
        // Check if locale is in URL
        $hasLocale = isset($segments[$this->segment]) 
            && $this->manager->isSupported(strtolower($segments[$this->segment]));
        
        if (!$hasLocale) {
            // Detect or use default locale
            $locale = $this->manager->detectLocale();
            
            // Build new URL with locale
            $newPath = '/' . $locale . $path;
            
            // Redirect
            header("Location: {$newPath}", true, 302);
            exit;
        }
        
        return $next($request);
    }
}
