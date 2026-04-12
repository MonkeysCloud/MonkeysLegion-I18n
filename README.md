# MonkeysLegion I18n v2

**Production-ready internationalization & localization for the MonkeysLegion PHP framework.**

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-blue.svg)](https://php.net)
[![Tests](https://img.shields.io/badge/Tests-127%20passing-brightgreen.svg)]()
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![v2 Standards](https://img.shields.io/badge/MonkeysLegion-v2%20Standards-orange.svg)]()

Translate, pluralize, format numbers/dates/currencies, and manage locales with security and performance at the core. Built with PHP 8.4 property hooks and asymmetric visibility.

---

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Translator](#translator)
  - [Basic Translation](#basic-translation)
  - [Parameter Replacement](#parameter-replacement)
  - [Pluralization](#pluralization)
  - [Namespaced Translations](#namespaced-translations)
  - [Fallback Locale](#fallback-locale)
  - [Missing Translation Tracking](#missing-translation-tracking)
  - [Warm-Up](#warm-up)
- [MessageFormatter](#messageformatter)
  - [Parameter Modifiers](#parameter-modifiers)
  - [XSS Protection](#xss-protection)
- [NumberFormatter](#numberformatter)
  - [Decimal Formatting](#decimal-formatting)
  - [Currency Formatting](#currency-formatting)
  - [Compact Notation](#compact-notation)
  - [Ordinals](#ordinals)
  - [File Size](#file-size)
- [DateFormatter](#dateformatter)
  - [Named Formats](#named-formats)
  - [Relative Time](#relative-time)
  - [Diff for Humans](#diff-for-humans)
- [LocaleManager](#localemanager)
  - [Detection Chain](#detection-chain)
  - [Supported Locales](#supported-locales)
- [LocaleInfo](#localeinfo)
  - [Native Names](#native-names)
  - [RTL Detection](#rtl-detection)
  - [Flag Emojis](#flag-emojis)
- [Loaders](#loaders)
  - [FileLoader](#fileloader)
  - [DatabaseLoader](#databaseloader)
  - [CacheLoader](#cacheloader)
  - [CompiledLoader](#compiledloader)
- [Middleware](#middleware)
  - [LocaleMiddleware](#localemiddleware)
  - [LocaleUrlMiddleware](#localeurlmiddleware)
  - [LocaleRedirectMiddleware](#localeredirectmiddleware)
- [Enums](#enums)
  - [PluralCategory](#pluralcategory)
  - [Direction](#direction)
- [Attributes](#attributes)
  - [#\[Translatable\]](#translatable)
  - [#\[Locale\]](#locale)
- [Events](#events)
- [Template Directives](#template-directives)
- [Helper Functions](#helper-functions)
- [TranslatorFactory](#translatorfactory)
- [Translation Management](#translation-management)
- [CLI Commands](#cli-commands)
- [Security](#security)
- [Performance](#performance)
- [Migration from v1](#migration-from-v1)
- [Testing](#testing)
- [License](#license)

---

## Installation

```bash
composer require monkeyscloud/monkeyslegion-i18n
```

### Requirements

- **PHP 8.4+** (property hooks, asymmetric visibility)
- **ext-json** (JSON translation files)
- **ext-mbstring** (Unicode support)

### Optional Extensions

```bash
# Advanced number/currency/date formatting
ext-intl

# For database translations
monkeyscloud/monkeyslegion-database

# For cache-backed loading
monkeyscloud/monkeyslegion-cache
```

---

## Quick Start

```php
use MonkeysLegion\I18n\TranslatorFactory;

// One-line setup
$translator = TranslatorFactory::create([
    'locale'   => 'en',
    'fallback' => 'en',
    'path'     => __DIR__ . '/resources/lang',
]);

echo $translator->trans('messages.welcome');
// → "Welcome!"

echo $translator->trans('messages.greeting', ['name' => 'Yorch']);
// → "Hello, Yorch!"

echo $translator->choice('messages.items', 5);
// → "5 items"
```

### Translation File Structure

```
resources/lang/
├── en/
│   ├── messages.json
│   └── validation.json
├── es/
│   ├── messages.json
│   └── validation.json
└── fr/
    └── messages.json
```

### Example `messages.json`

```json
{
  "welcome": "Welcome!",
  "greeting": "Hello, :name!",
  "farewell": "Goodbye, :NAME!",
  "items": "{0} No items|{1} One item|[2,*] :count items",
  "nested": {
    "key": "Nested value",
    "deep": {
      "value": "Deep nested value"
    }
  }
}
```

---

## Translator

### Basic Translation

```php
use MonkeysLegion\I18n\Translator;
use MonkeysLegion\I18n\Loaders\FileLoader;

$translator = new Translator('en', 'en');
$translator->addLoader(new FileLoader('/path/to/lang'));

// Simple key
echo $translator->trans('messages.welcome');
// → "Welcome!"

// Nested key
echo $translator->trans('messages.nested.deep.value');
// → "Deep nested value"

// Check if translation exists
if ($translator->has('messages.welcome')) {
    // Key exists
}

// Returns the key itself when not found
echo $translator->trans('messages.nonexistent');
// → "messages.nonexistent"
```

### Parameter Replacement

```php
// Lowercase :name → exact value
echo $translator->trans('messages.greeting', ['name' => 'Yorch']);
// → "Hello, Yorch!"

// Uppercase :NAME → UPPERCASED
echo $translator->trans('messages.farewell', ['name' => 'Yorch']);
// → "Goodbye, YORCH!"

// Ucfirst :Name → Capitalized
// Message: "Welcome :Name"
echo $translator->trans('messages.title', ['name' => 'yorch']);
// → "Welcome Yorch"

// Multiple parameters
echo $translator->trans('order.summary', [
    'product' => 'Widget',
    'count'   => 3,
    'total'   => '29.97',
]);
// → "3x Widget — Total: $29.97"
```

### Pluralization

Supports ICU plural categories for 200+ languages.

```php
// Explicit count forms
// "{0} No items|{1} One item|[2,*] :count items"
echo $translator->choice('messages.items', 0);  // → "No items"
echo $translator->choice('messages.items', 1);  // → "One item"
echo $translator->choice('messages.items', 42); // → "42 items"

// Range forms
// "[0,3] A few|[4,10] Several|[11,*] Many"
echo $translator->choice('messages.range', 2);  // → "A few"
echo $translator->choice('messages.range', 7);  // → "Several"
echo $translator->choice('messages.range', 50); // → "Many"

// Simple pipe-delimited (singular|plural)
// "apple|apples"
echo $translator->choice('messages.fruit', 1); // → "apple"
echo $translator->choice('messages.fruit', 5); // → "apples"

// With additional replacements
echo $translator->choice('messages.items', 3, ['color' => 'red']);
// "{0} No :color items|{1} One :color item|[2,*] :count :color items"
// → "3 red items"
```

### Namespaced Translations

```php
// Register a namespace
$translator->addNamespace('billing', '/path/to/billing/lang');
$translator->addLoader(new FileLoader('/path/to/billing/lang'));

// Use namespace::group.key format
echo $translator->trans('billing::invoices.title');
// → Loads from /path/to/billing/lang/{locale}/invoices.json
```

### Fallback Locale

```php
$translator = new Translator('fr', 'en');

// If 'fr' translation missing, falls back to 'en'
echo $translator->trans('messages.welcome');
// French translation exists → "Bienvenue!"

echo $translator->trans('messages.rare_key');
// French missing, English fallback → "Rare English Value"
```

### Missing Translation Tracking

```php
$translator->setTrackMissing(true);

$translator->trans('messages.missing1');
$translator->trans('messages.missing2');

$missing = $translator->getMissingTranslations();
// ["en.messages.missing1", "en.messages.missing2"]

$translator->clearMissingTranslations();
```

### Warm-Up

Pre-load translation groups for production performance:

```php
$translator->warmUp('en', ['messages', 'validation', 'auth']);

// Check what's loaded
$groups = $translator->getLoadedGroups();
// ["messages.en", "validation.en", "auth.en"]
```

### Property Hooks (PHP 8.4)

```php
$translator = new Translator('en');

// Use property directly
echo $translator->locale; // → "en"

// Setter triggers validation + event dispatch
$translator->locale = 'es';

// Invalid locale throws InvalidLocaleException
$translator->locale = '../etc/passwd'; // ❌ InvalidLocaleException
```

### Locale Changed Events

```php
$translator->setEventDispatcher(function (LocaleChangedEvent $event): void {
    log("Locale changed: {$event->previousLocale} → {$event->newLocale}");
});

$translator->setLocale('fr'); // Event fires automatically
```

---

## MessageFormatter

### Parameter Modifiers

Use braced syntax with modifiers for advanced formatting:

```php
use MonkeysLegion\I18n\MessageFormatter;

$formatter = new MessageFormatter();

// Uppercase
echo $formatter->format('Name: {name|upper}', ['name' => 'yorch']);
// → "Name: YORCH"

// Lowercase
echo $formatter->format('Email: {email|lower}', ['email' => 'USER@EXAMPLE.COM']);
// → "Email: user@example.com"

// Title case
echo $formatter->format('Title: {title|title}', ['title' => 'hello world']);
// → "Title: Hello World"

// Capitalize first
echo $formatter->format('{msg|ucfirst}', ['msg' => 'hello']);
// → "Hello"

// Truncate
echo $formatter->format('{desc|truncate:20}', ['desc' => 'Very long description text']);
// → "Very long descriptio..."

// Number formatting
echo $formatter->format('Total: {amount|number:2}', ['amount' => 1234.5]);
// → "Total: 1,234.50"

// Currency
echo $formatter->format('Price: {price|currency:EUR}', ['price' => 42.50]);
// → "Price: €42.50"

// Percentage
echo $formatter->format('Rate: {rate|percent}', ['rate' => 0.156]);
// → "Rate: 15.60%"

// Date formatting
echo $formatter->format('Date: {date|date:medium}', ['date' => '2026-01-15']);
// → "Date: Jan 15, 2026"

// Default fallback
echo $formatter->format('Name: {name|default:Anonymous}', ['name' => '']);
// → "Name: Anonymous"
```

### XSS Protection

```php
// Auto-escape disabled by default for backward compatibility
$formatter = new MessageFormatter();
echo $formatter->format('Hello :name', ['name' => '<script>alert(1)</script>']);
// → "Hello <script>alert(1)</script>"

// Enable auto-escape for user-facing output
$safe = new MessageFormatter(autoEscape: true);
echo $safe->format('Hello :name', ['name' => '<script>alert(1)</script>']);
// → "Hello &lt;script&gt;alert(1)&lt;/script&gt;"

// Custom sanitizer
$custom = new MessageFormatter(
    autoEscape: true,
    sanitizer: new MyCustomSanitizer(),
);
```

---

## NumberFormatter

Locale-aware number formatting with graceful ext-intl fallback.

```php
use MonkeysLegion\I18n\NumberFormatter;

$nf = new NumberFormatter();
```

### Decimal Formatting

```php
echo $nf->decimal(1234567, 'en');       // → "1,234,567"
echo $nf->decimal(1234567, 'de');       // → "1.234.567" (with ext-intl)
echo $nf->decimal(3.14159, 'en', 2);    // → "3.14"
```

### Currency Formatting

```php
echo $nf->currency(42.50, 'USD', 'en');  // → "$42.50"
echo $nf->currency(42.50, 'EUR', 'de');  // → "42,50 €" (with ext-intl)
echo $nf->currency(42.50, 'GBP', 'en');  // → "£42.50"
echo $nf->currency(42.50, 'JPY', 'ja');  // → "¥42.50"
echo $nf->currency(42.50, 'BRL', 'pt');  // → "R$42.50"

// 32 built-in currency symbols
// USD, EUR, GBP, JPY, CAD, AUD, CHF, CNY, MXN, BRL,
// INR, KRW, RUB, TRY, SEK, NOK, DKK, PLN, CZK, HUF,
// RON, BGN, HRK, THB, PHP, MYR, IDR, VND, ZAR, EGP,
// NGN, KES, ARS, CLP, COP, PEN
```

### Compact Notation

```php
echo $nf->compact(500);           // → "500"
echo $nf->compact(1_234);         // → "1.2K"
echo $nf->compact(1_500_000);     // → "1.5M"
echo $nf->compact(2_345_000_000); // → "2.3B"
echo $nf->compact(-1_234);        // → "-1.2K"
```

### Ordinals

```php
echo $nf->ordinal(1);   // → "1st"
echo $nf->ordinal(2);   // → "2nd"
echo $nf->ordinal(3);   // → "3rd"
echo $nf->ordinal(11);  // → "11th"
echo $nf->ordinal(21);  // → "21st"
echo $nf->ordinal(112); // → "112th"
```

### Percentage

```php
echo $nf->percent(0.156, 'en', 1); // → "15.6%"
echo $nf->percent(0.5, 'en');      // → "50%"
```

### File Size

```php
echo $nf->fileSize(0);         // → "0 B"
echo $nf->fileSize(1024);      // → "1.00 KB"
echo $nf->fileSize(1_572_864); // → "1.50 MB"
echo $nf->fileSize(5e9);       // → "4.66 GB"
```

### Spell Out

```php
echo $nf->spellOut(123, 'en');
// → "one hundred twenty-three" (requires ext-intl)

echo $nf->spellOut(42, 'es');
// → "cuarenta y dos" (requires ext-intl)
```

---

## DateFormatter

Locale-aware date/time formatting with relative time support.

```php
use MonkeysLegion\I18n\DateFormatter;

$df = new DateFormatter();
```

### Named Formats

```php
$date = new DateTimeImmutable('2026-01-15');

echo $df->format($date, 'short');    // → "1/15/26"
echo $df->format($date, 'medium');   // → "Jan 15, 2026"
echo $df->format($date, 'long');     // → "January 15, 2026"
echo $df->format($date, 'full');     // → "Thursday, January 15, 2026"
echo $df->format($date, 'iso');      // → "2026-01-15"
echo $df->format($date, 'time');     // → "12:00 AM"
echo $df->format($date, 'datetime'); // → "Jan 15, 2026 12:00 AM"

// Custom format
echo $df->format($date, 'Y/m/d');    // → "2026/01/15"

// From timestamp
echo $df->format(1705276800, 'medium'); // → "Jan 15, 2024"

// From string
echo $df->format('2026-01-15', 'long'); // → "January 15, 2026"

// With timezone
echo $df->format($date, 'datetime', 'en', 'America/New_York');
```

### Relative Time

```php
$now = new DateTimeImmutable('2026-01-15 12:00:00');

// Past — English
echo $df->relative('2026-01-15 11:58:00', 'en', $now);
// → "2 minutes ago"

echo $df->relative('2026-01-15 10:00:00', 'en', $now);
// → "2 hours ago"

echo $df->relative('2026-01-14 12:00:00', 'en', $now);
// → "1 day ago"

echo $df->relative('2025-11-15 12:00:00', 'en', $now);
// → "2 months ago"

// Past — Spanish
echo $df->relative('2026-01-15 10:00:00', 'es', $now);
// → "hace 2 horas"

echo $df->relative('2026-01-14 12:00:00', 'es', $now);
// → "hace 1 día"

// Future
echo $df->relative('2026-01-15 14:00:00', 'en', $now);
// → "in 2 hours"

// Just now (< 10 seconds)
echo $df->relative('2026-01-15 11:59:55', 'en', $now);
// → "just now"
```

### Diff for Humans

```php
echo $df->diffForHumans(
    '2026-01-15 10:00:00',
    '2026-01-15 12:30:00',
    'en',
);
// → "2 hours ago"
```

### Day and Month Names

```php
$date = new DateTimeImmutable('2026-01-15');

echo $df->dayOfWeek($date);             // → "Thursday"
echo $df->dayOfWeek($date, short: true); // → "Thu"

echo $df->monthName($date);             // → "January"
echo $df->monthName($date, short: true); // → "Jan"
```

### ISO 8601

```php
echo $df->iso('2026-01-15 12:00:00');
// → "2026-01-15T12:00:00+00:00"
```

---

## LocaleManager

Manages locale detection, validation, and state.

```php
use MonkeysLegion\I18n\LocaleManager;

$manager = new LocaleManager(
    defaultLocale: 'en',
    supportedLocales: ['en', 'es', 'fr', 'de', 'ja'],
    fallbackLocale: 'en',
);
```

### Detection Chain

```php
use MonkeysLegion\I18n\Detectors\{
    UrlDetector,
    QueryDetector,
    SessionDetector,
    CookieDetector,
    HeaderDetector,
    SubdomainDetector,
};

// Priority order (first match wins)
$manager->addDetector(new UrlDetector(segment: 0));      // /es/products
$manager->addDetector(new QueryDetector(paramName: 'lang')); // ?lang=es
$manager->addDetector(new SessionDetector(key: 'locale'));
$manager->addDetector(new CookieDetector(cookieName: 'locale'));
$manager->addDetector(new HeaderDetector());              // Accept-Language
$manager->addDetector(new SubdomainDetector());           // es.example.com

$locale = $manager->detectLocale();
// Tries each detector in order, returns first supported match
```

### Supported Locales

```php
$manager->isSupported('es');     // → true
$manager->isSupported('xx');     // → false

$manager->addSupportedLocale('pt');
$manager->isSupported('pt');     // → true

$manager->setLocale('es');       // ✅ Switches locale
$manager->setLocale('xx');       // ❌ throws InvalidArgumentException

// Asymmetric visibility (PHP 8.4)
echo $manager->defaultLocale;    // → "en" (read-only from outside)
echo $manager->fallbackLocale;   // → "en"
print_r($manager->supportedLocales); // → ["en", "es", "fr", "de", "ja"]
```

### Locale Parsing

```php
echo $manager->parseLocale('en-US');  // → "en"
echo $manager->parseLocale('en_US');  // → "en"
echo $manager->parseLocale('pt-BR');  // → "pt"
echo $manager->parseLocale('es');     // → "es"
```

---

## LocaleInfo

Static metadata for 50+ locales.

```php
use MonkeysLegion\I18n\Support\LocaleInfo;
```

### Native Names

```php
echo LocaleInfo::name('es');       // → "Spanish"
echo LocaleInfo::nativeName('es'); // → "Español"
echo LocaleInfo::nativeName('ja'); // → "日本語"
echo LocaleInfo::nativeName('ar'); // → "العربية"
echo LocaleInfo::nativeName('ru'); // → "Русский"
echo LocaleInfo::nativeName('hi'); // → "हिन्दी"
echo LocaleInfo::nativeName('ko'); // → "한국어"
echo LocaleInfo::nativeName('zh'); // → "中文"
```

### RTL Detection

```php
echo LocaleInfo::isRtl('ar'); // → true (Arabic)
echo LocaleInfo::isRtl('he'); // → true (Hebrew)
echo LocaleInfo::isRtl('fa'); // → true (Persian)
echo LocaleInfo::isRtl('ur'); // → true (Urdu)
echo LocaleInfo::isRtl('en'); // → false

echo LocaleInfo::direction('ar'); // → Direction::RTL
echo LocaleInfo::direction('en'); // → Direction::LTR
```

### Flag Emojis

```php
echo LocaleInfo::flag('en'); // → 🇺🇸
echo LocaleInfo::flag('es'); // → 🇪🇸
echo LocaleInfo::flag('fr'); // → 🇫🇷
echo LocaleInfo::flag('jp'); // → 🇯🇵
echo LocaleInfo::flag('br'); // → 🇧🇷
```

### Script & Knowledge

```php
echo LocaleInfo::script('en'); // → "Latn"
echo LocaleInfo::script('ar'); // → "Arab"
echo LocaleInfo::script('ru'); // → "Cyrl"
echo LocaleInfo::script('ja'); // → "Jpan"
echo LocaleInfo::script('ko'); // → "Kore"

echo LocaleInfo::isKnown('en'); // → true
echo LocaleInfo::isKnown('xx'); // → false

$allCodes = LocaleInfo::allCodes(); // 50+ locale codes
```

---

## Loaders

### FileLoader

Loads from JSON and PHP files with security hardening.

```php
use MonkeysLegion\I18n\Loaders\FileLoader;

$loader = new FileLoader('/path/to/lang');

// Loads from /path/to/lang/{locale}/{group}.json
$messages = $loader->load('en', 'messages');

// Add namespace path
$loader->addNamespace('billing', '/path/to/billing/lang');

// Security features:
// ✅ Path traversal prevention (realpath validation)
// ✅ Null byte injection prevention
// ✅ Max file size limit (2MB)
// ✅ JSON_THROW_ON_ERROR on all json_decode
// ✅ Symlink resolution and validation
```

### DatabaseLoader

```php
use MonkeysLegion\I18n\Loaders\DatabaseLoader;

$loader = new DatabaseLoader($pdo, 'translations');

// CREATE TABLE translations (
//   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
//   locale VARCHAR(10) NOT NULL,
//   `group` VARCHAR(50) NOT NULL,
//   namespace VARCHAR(50) NULL,
//   `key` VARCHAR(255) NOT NULL,
//   value TEXT NOT NULL,
//   source VARCHAR(50) DEFAULT 'admin',
//   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//   updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
//   UNIQUE INDEX idx_translation_key (locale, `group`, namespace, `key`)
// );

// Security features:
// ✅ Table name validated against regex pattern
// ✅ All queries use parameterized statements
// ✅ Readonly PDO property
```

### CacheLoader

Decorator that caches translations from any other loader.

```php
use MonkeysLegion\I18n\Loaders\CacheLoader;

$cached = new CacheLoader(
    loader: $fileLoader,
    cache: $psr16Cache,
    ttl: 3600,
    prefix: 'i18n',
);

// Features:
// ✅ TTL jitter (±10%) to prevent thundering herd
// ✅ Selective cache invalidation
// ✅ Flush all cached translations

$cached->forget('en', 'messages');     // Clear specific group
$cached->flush();                       // Clear all
```

### CompiledLoader

Opcache-friendly compiled PHP files — 10-50x faster than JSON decode.

```php
use MonkeysLegion\I18n\Loaders\CompiledLoader;

$compiled = new CompiledLoader(
    sourceLoader: $fileLoader,
    compilePath: '/var/cache/i18n',
);

// Compile all translations for a locale
$compiled->compile('en', '/path/to/lang');
// → Creates /var/cache/i18n/en.compiled.php

// Check if compiled cache is fresh
if (!$compiled->isFresh('en', '/path/to/lang')) {
    $compiled->compile('en', '/path/to/lang');
}

// Invalidate
$compiled->invalidate('en');

// Atomic writes via temp file + rename
// Auto opcache invalidation
```

---

## Middleware

### LocaleMiddleware

Auto-detect and set locale for each request.

```php
use MonkeysLegion\I18n\Middleware\LocaleMiddleware;

$middleware = new LocaleMiddleware(
    manager: $localeManager,
    translator: $translator,
    setSession: true,
    setCookie: true,
    cookieTtl: 31536000, // 1 year
);

// Security features:
// ✅ SameSite=Lax on cookies
// ✅ HttpOnly flag
// ✅ Secure flag when HTTPS
// ✅ headers_sent() guard
```

### LocaleUrlMiddleware

Extract locale from URL path segment.

```php
use MonkeysLegion\I18n\Middleware\LocaleUrlMiddleware;

// /es/products → sets locale to "es"
$middleware = new LocaleUrlMiddleware($manager, $translator, segment: 0);
```

### LocaleRedirectMiddleware

Auto-redirect to localized URL if locale prefix is missing.

```php
use MonkeysLegion\I18n\Middleware\LocaleRedirectMiddleware;

// /products → 302 → /en/products
$middleware = new LocaleRedirectMiddleware($manager, segment: 0);

// No exit() call — returns redirect response array
// ✅ headers_sent() guard
```

---

## Enums

### PluralCategory

```php
use MonkeysLegion\I18n\Enum\PluralCategory;

// ICU plural categories
PluralCategory::Zero;   // "zero"
PluralCategory::One;    // "one"
PluralCategory::Two;    // "two"
PluralCategory::Few;    // "few"
PluralCategory::Many;   // "many"
PluralCategory::Other;  // "other"

PluralCategory::Other->isDefault(); // → true
PluralCategory::ordered();          // Ordered list

// Get plural category for a count in a locale
$pluralizer = new Pluralizer();
$cat = $pluralizer->getCategoryForCount(5, 'en');
// → PluralCategory::Other

$cat = $pluralizer->getCategoryForCount(2, 'ar');
// → PluralCategory::Two
```

### Direction

```php
use MonkeysLegion\I18n\Enum\Direction;

Direction::LTR; // "ltr"
Direction::RTL; // "rtl"

Direction::fromLocale('en'); // → Direction::LTR
Direction::fromLocale('ar'); // → Direction::RTL
Direction::fromLocale('he'); // → Direction::RTL
Direction::fromLocale('fa'); // → Direction::RTL

Direction::RTL->cssAttribute(); // → 'dir="rtl"'
```

---

## Attributes

### #[Translatable]

Mark entity properties for automatic translation:

```php
use MonkeysLegion\I18n\Attribute\Translatable;

class Product
{
    #[Translatable(group: 'products', keyPrefix: 'title')]
    public string $title;

    #[Translatable(group: 'products', keyPrefix: 'description', fallbackToValue: true)]
    public string $description;
}
```

### #[Locale]

Auto-inject the detected locale into controller parameters:

```php
use MonkeysLegion\I18n\Attribute\Locale;

class ProductController
{
    public function index(#[Locale] string $locale): Response
    {
        // $locale is auto-populated from the detected locale
    }
}
```

---

## Events

```php
use MonkeysLegion\I18n\Event\LocaleChangedEvent;

// Immutable event (readonly class)
$translator->setEventDispatcher(function (LocaleChangedEvent $event): void {
    echo $event->previousLocale; // → "en"
    echo $event->newLocale;      // → "es"

    // Update user preferences, reconfigure formatters, etc.
});
```

---

## Template Directives

For use with MonkeysLegion-Template engine:

```php
use MonkeysLegion\I18n\Template\I18nDirectives;

$directives = new I18nDirectives($translator);

// Register all directives
foreach ($directives->getDirectives() as $name => $handler) {
    $engine->directive($name, $handler);
}
```

In templates:

```blade
{{-- Translation --}}
@lang('welcome.message')
@lang('welcome.user', ['name' => $user->name])

{{-- Pluralization --}}
@choice('messages.count', $count)

{{-- Current locale --}}
@locale

{{-- Formatting --}}
@date($order->created_at, 'long')
@currency($product->price, 'USD')
@number($total, 2)
```

---

## Helper Functions

Global helper functions for convenience:

```php
// Translation
echo trans('messages.welcome');
echo trans('messages.greeting', ['name' => 'Yorch']);

// Shorthand alias (__)
echo __('messages.welcome');
echo __('messages.greeting', ['name' => 'Yorch']);

// Pluralization
echo trans_choice('messages.items', 5);
echo trans_choice('messages.items', 1, ['color' => 'red']);

// Get/set locale
echo lang();        // → "en"
lang('es');          // Sets locale to "es"
echo lang();        // → "es"
```

---

## TranslatorFactory

One-line creation with all features:

```php
use MonkeysLegion\I18n\TranslatorFactory;

// Basic
$translator = TranslatorFactory::create([
    'locale'   => 'en',
    'fallback' => 'en',
    'path'     => '/path/to/lang',
]);

// With caching
$translator = TranslatorFactory::create([
    'locale'    => 'en',
    'path'      => '/path/to/lang',
    'cache'     => $psr16Cache,
    'cache_ttl' => 3600,
]);

// With compiled loader (production)
$translator = TranslatorFactory::create([
    'locale'        => 'en',
    'path'          => '/path/to/lang',
    'compiled_path' => '/var/cache/i18n',
]);

// With database
$translator = TranslatorFactory::create([
    'locale'    => 'en',
    'path'      => '/path/to/lang',
    'pdo'       => $pdo,
    'cache'     => $cache,
]);

// With namespaces
$translator = TranslatorFactory::create([
    'locale'     => 'en',
    'path'       => '/path/to/lang',
    'namespaces' => [
        'billing' => '/path/to/billing/lang',
        'email'   => '/path/to/email/lang',
    ],
]);

// Full system (translator + manager)
['translator' => $t, 'manager' => $m] = TranslatorFactory::createSystem([
    'default'   => 'en',
    'supported' => ['en', 'es', 'fr'],
    'path'      => '/path/to/lang',
    'detectors' => ['url', 'session', 'cookie', 'header'],
]);

// Number & Date formatters
$nf = TranslatorFactory::createNumberFormatter();
$df = TranslatorFactory::createDateFormatter();
```

---

## Translation Management

Full CRUD management for file and database translations:

```php
use MonkeysLegion\I18n\Management\TranslationManager;

$manager = new TranslationManager($pdo, $translator, '/path/to/lang');

// CRUD
$manager->set('en', 'messages', 'welcome', 'Hello!');
echo $manager->get('en', 'messages', 'welcome'); // → "Hello!"
$manager->delete('en', 'messages', 'welcome');

// Import/Export
$manager->importFromFile('en', 'messages');
$manager->exportToFile('en', 'messages', 'json');
$manager->importArray('en', 'messages', ['key' => 'value'], overwrite: true);

// Sync
$manager->sync('en', 'messages', 'file_to_db');
$manager->sync('en', 'messages', 'db_to_file');

// Merged (DB overrides file)
$all = $manager->getAllMerged('en', 'messages');

// Search
$results = $manager->search('welcome', locale: 'en');

// Statistics
$stats = $manager->getStats();
// ['total' => 150, 'by_locale' => [...], 'by_group' => [...], 'by_source' => [...]]

// Batch update
$manager->batchUpdate([
    ['locale' => 'en', 'group' => 'messages', 'key' => 'welcome', 'value' => 'Hi!'],
    ['locale' => 'en', 'group' => 'messages', 'key' => 'goodbye', 'value' => 'Bye!'],
]);

// Find missing (file keys not in DB)
$missing = $manager->findMissing('en', 'messages');
```

---

## CLI Commands

```php
use MonkeysLegion\I18n\Console\TranslationCommand;

$cmd = new TranslationCommand($translator, '/path/to/lang');

// Extract translation keys from source code
$cmd->extract('/path/to/src', '/path/to/output.json');
// Scans for trans(), __(), @lang(), @choice() calls

// Find missing translations
$cmd->missing('es');
// ✗ Found 5 missing translations:
//   - messages.new_feature
//   - validation.custom_rule

// Compare two locales
$cmd->compare('en', 'es');
// Missing in es (3):
//   - messages.new_key
//   - validation.rule

// Export translations
$cmd->export('en', 'json', '/path/to/export.json');
$cmd->export('en', 'csv', '/path/to/export.csv');
$cmd->export('en', 'php', '/path/to/export.php');
```

---

## Security

### Path Traversal Prevention
```php
// FileLoader validates all path segments
$loader->load('../etc', 'passwd');     // ❌ LoaderException
$loader->load("en\0", 'messages');     // ❌ LoaderException
$loader->load('en/../../', 'msg');     // ❌ LoaderException
```

### Locale Injection Prevention
```php
// Translator validates locale format: /^[a-z]{2,3}(_[A-Z]{2})?$/
new Translator('../etc/passwd');        // ❌ InvalidLocaleException
new Translator("en\0");                 // ❌ InvalidLocaleException
$translator->locale = '<script>';       // ❌ InvalidLocaleException
```

### SQL Injection Prevention
```php
// DatabaseLoader validates table names
new DatabaseLoader($pdo, 'DROP TABLE users; --'); // ❌ InvalidArgumentException

// All queries use parameterized statements
```

### XSS Protection
```php
// Enable auto-escaping in MessageFormatter
$formatter = new MessageFormatter(autoEscape: true);
// All :param replacements are HTML-escaped via htmlspecialchars()
```

### Cookie Security
```php
// LocaleMiddleware sets secure cookie flags
// SameSite=Lax, HttpOnly, Secure (when HTTPS)
```

---

## Performance

### Compiled Loader (Production)

```php
// 10-50x faster than JSON decode per request
$compiled = new CompiledLoader($fileLoader, '/var/cache/i18n');
$compiled->compile('en', '/path/to/lang');

// Uses PHP's opcache for near-zero overhead
// Atomic writes (temp file + rename)
// Auto mtime-based freshness checks
```

### Cache with Jitter

```php
// TTL jitter prevents thundering herd (cache stampede)
// ±10% variation: TTL 3600 → random 3240-3960
$cached = new CacheLoader($loader, $cache, ttl: 3600);
```

### Warm-Up

```php
// Pre-load all groups at boot time
$translator->warmUp('en', ['messages', 'validation', 'auth', 'errors']);
```

### Const Array Pluralizer

```php
// Locale-to-rule mapping is a const array (PHP 8.4)
// Zero overhead — compiled into opcache
private const array LOCALE_RULES = [
    'pl' => 'polish',
    'ru' => 'russian',
    // ...
];
```

---

## Migration from v1

### Namespace Changes

```diff
- use MonkeysLegion\I18n\Contracts\LoaderInterface;
+ use MonkeysLegion\I18n\Contract\LoaderInterface;
```

> **Note**: Backward-compatible aliases exist at `src/Contracts/aliases.php`.

### Property Hooks

```diff
- $translator->getLocale();
+ $translator->locale;         // Read via property
+ $translator->getLocale();    // Still works (BC)

- $translator->setLocale('es');
+ $translator->locale = 'es';  // Write via property hook
+ $translator->setLocale('es'); // Still works (BC)
```

### Locale Validation

```diff
// v1: No validation
$translator = new Translator('../etc/passwd');

// v2: Strict validation
+ $translator = new Translator('../etc/passwd');
+ // ❌ InvalidLocaleException
```

### Middleware Split

```diff
// v1: All middleware in single file
- // src/Middleware/LocaleMiddleware.php contained 3 classes

// v2: One class per file
+ src/Middleware/LocaleMiddleware.php
+ src/Middleware/LocaleUrlMiddleware.php
+ src/Middleware/LocaleRedirectMiddleware.php
```

---

## Testing

```bash
# Run all tests
composer test

# Run with testdox output
vendor/bin/phpunit --testdox

# Run specific test file
vendor/bin/phpunit tests/Unit/I18nV2Test.php

# Run specific test
vendor/bin/phpunit --filter="translator_translates_basic_key"
```

### Test Coverage

- **127 tests**, **222 assertions**
- Covers: Translator, Pluralizer, MessageFormatter, NumberFormatter, DateFormatter, LocaleManager, LocaleInfo, FileLoader, CompiledLoader, Enums, Attributes, Events, Factory

---

## License

MIT License. See [LICENSE](LICENSE) for details.

**Built with ❤️ by [MonkeysCloud](https://monkeys.cloud)**
