<?php

declare(strict_types=1);

/**
 * I18n Configuration
 * 
 * Configure locales, detectors, and translation sources
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | The default locale that will be used when no other locale is detected
    |
    */
    'default_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale is used when a translation key is not found in
    | the current locale. This ensures users always see some content.
    |
    */
    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | List of all locales your application supports. Only these locales
    | will be accepted from detectors.
    |
    */
    'supported_locales' => [
        'en', // English
        'es', // Spanish
        'fr', // French
        'de', // German
        'it', // Italian
        'pt', // Portuguese
        'ru', // Russian
        'ja', // Japanese
        'ko', // Korean
        'zh', // Chinese
    ],

    /*
    |--------------------------------------------------------------------------
    | Translation Path
    |--------------------------------------------------------------------------
    |
    | Path to translation files directory
    |
    */
    'path' => __DIR__ . '/../resources/lang',

    /*
    |--------------------------------------------------------------------------
    | Locale Detectors
    |--------------------------------------------------------------------------
    |
    | Detectors are tried in order. First detector that returns a valid
    | locale wins. Available: url, query, session, cookie, header, subdomain
    |
    */
    'detectors' => [
        'url',      // From URL segment: /es/products
        'query',    // From query param: ?lang=es
        'session',  // From session: $_SESSION['locale']
        'cookie',   // From cookie: $_COOKIE['locale']
        'header',   // From Accept-Language header
    ],

    /*
    |--------------------------------------------------------------------------
    | URL Segment
    |--------------------------------------------------------------------------
    |
    | Which URL segment contains the locale (0-indexed)
    | Example: /es/products -> segment 0 is 'es'
    |
    */
    'url_segment' => 0,

    /*
    |--------------------------------------------------------------------------
    | Query Parameter
    |--------------------------------------------------------------------------
    |
    | Query parameter name for locale detection
    | Example: ?lang=es
    |
    */
    'query_parameter' => 'lang',

    /*
    |--------------------------------------------------------------------------
    | Session Key
    |--------------------------------------------------------------------------
    |
    | Session key for storing locale
    |
    */
    'session_key' => 'locale',

    /*
    |--------------------------------------------------------------------------
    | Cookie Name
    |--------------------------------------------------------------------------
    |
    | Cookie name for storing locale
    |
    */
    'cookie_name' => 'locale',

    /*
    |--------------------------------------------------------------------------
    | Cookie TTL
    |--------------------------------------------------------------------------
    |
    | How long the locale cookie should last (in seconds)
    | Default: 1 year (31536000)
    |
    */
    'cookie_ttl' => 31536000,

    /*
    |--------------------------------------------------------------------------
    | Cache Translations
    |--------------------------------------------------------------------------
    |
    | Whether to cache loaded translations for better performance
    |
    */
    'cache_enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | How long to cache translations (in seconds)
    | Default: 1 hour (3600)
    |
    */
    'cache_ttl' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Cache Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for cache keys to avoid collisions
    |
    */
    'cache_prefix' => 'i18n',

    /*
    |--------------------------------------------------------------------------
    | Database Translations
    |--------------------------------------------------------------------------
    |
    | Whether to load translations from database
    | Requires migrations to be run
    |
    */
    'database_enabled' => false,

    /*
    |--------------------------------------------------------------------------
    | Database Table
    |--------------------------------------------------------------------------
    |
    | Table name for storing translations
    |
    */
    'database_table' => 'translations',

    /*
    |--------------------------------------------------------------------------
    | Track Missing Translations
    |--------------------------------------------------------------------------
    |
    | Enable in development to track missing translation keys
    | Disable in production for performance
    |
    */
    'track_missing' => false,

    /*
    |--------------------------------------------------------------------------
    | Namespaces
    |--------------------------------------------------------------------------
    |
    | Register translation namespaces for packages
    | Format: 'namespace' => '/path/to/lang'
    |
    */
    'namespaces' => [
        // 'monkeysmail' => __DIR__ . '/../packages/monkeysmail/resources/lang',
        // 'monkeysraiser' => __DIR__ . '/../packages/monkeysraiser/resources/lang',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Redirect to Localized URL
    |--------------------------------------------------------------------------
    |
    | Automatically redirect to localized URL if locale not in URL
    | Example: /products -> /en/products
    |
    */
    'auto_redirect' => false,

    /*
    |--------------------------------------------------------------------------
    | Force Default Locale in URL
    |--------------------------------------------------------------------------
    |
    | If true, even default locale appears in URL: /en/products
    | If false, default locale can be omitted: /products
    |
    */
    'force_default_locale' => true,
];
