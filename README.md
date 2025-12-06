# MonkeysLegion I18n

Production-ready internationalization and localization package for the MonkeysLegion PHP framework.

## ✨ Features

- 🌍 **Multiple Translation Sources**: JSON files, PHP arrays, Database, Cache
- 📝 **ICU Pluralization**: Plural rules for 200+ languages
- 🎯 **Auto Locale Detection**: URL, Session, Headers, Cookies
- 🚀 **High Performance**: Built-in caching support
- 🔄 **Fallback Chain**: Locale → Fallback → Default
- 📦 **Namespacing**: Package-level translations (`vendor::file.key`)
- 📊 **Missing Translation Tracking**: Development mode tracking
- 💾 **Hybrid System**: Use JSON files AND database simultaneously

## Installation

```bash
composer require monkeyscloud/monkeyslegion-i18n
```

**Note**: The `php-intl` extension is optional but recommended for advanced number/date formatting.

## Quick Start

### Basic Usage with JSON Files

**Step 1: Create translation files**

```bash
# Create directory structure
mkdir -p resources/lang/en
mkdir -p resources/lang/es
```

**`resources/lang/en/messages.json`**

```json
{
  "welcome": "Welcome!",
  "greeting": "Hello, :name!",
  "items": "{0} No items|{1} One item|[2,*] :count items"
}
```

**`resources/lang/es/messages.json`**

```json
{
  "welcome": "¡Bienvenido!",
  "greeting": "¡Hola, :name!",
  "items": "{0} Sin artículos|{1} Un artículo|[2,*] :count artículos"
}
```

**Step 2: Use the translator**

```php
<?php

use MonkeysLegion\I18n\TranslatorFactory;

// Create translator
$translator = TranslatorFactory::create([
    'locale' => 'es',
    'fallback' => 'en',
    'path' => __DIR__ . '/resources/lang'
]);

// Basic translation
echo $translator->trans('messages.welcome');
// Output: ¡Bienvenido!

// With parameters
echo $translator->trans('messages.greeting', ['name' => 'Yorch']);
// Output: ¡Hola, Yorch!

// Pluralization
echo $translator->choice('messages.items', 0);  // Sin artículos
echo $translator->choice('messages.items', 1);  // Un artículo
echo $translator->choice('messages.items', 5);  // 5 artículos
```

## Complete Examples

### Example 1: JSON Files Only (Simple Application)

Perfect for small to medium applications where all translations are static.

**Directory structure:**

```
resources/lang/
├── en/
│   ├── messages.json
│   └── validation.json
└── es/
    ├── messages.json
    └── validation.json
```

**`resources/lang/en/validation.json`**

```json
{
  "required": "The :field field is required.",
  "email": "Please enter a valid email address.",
  "min": {
    "string": "Must be at least :min characters."
  },
  "max": {
    "string": "Must not exceed :max characters."
  }
}
```

**Usage:**

```php
<?php

$translator = TranslatorFactory::create([
    'locale' => 'en',
    'fallback' => 'en',
    'path' => __DIR__ . '/resources/lang'
]);

// Nested key access
echo $translator->trans('validation.min.string', ['min' => 8]);
// Output: Must be at least 8 characters.

// Check if translation exists
if ($translator->has('validation.email')) {
    echo $translator->trans('validation.email');
}

// Switch locale
$translator->setLocale('es');
echo $translator->trans('validation.required', ['field' => 'email']);
```

### Example 2: Database Translations (CMS/Admin Panel)

Perfect for applications where content admins need to edit translations.

**Step 1: Create translations table**

```sql
CREATE TABLE `translations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `locale` VARCHAR(10) NOT NULL,
    `group` VARCHAR(100) NOT NULL,
    `namespace` VARCHAR(100) NULL,
    `key` VARCHAR(255) NOT NULL,
    `value` TEXT NOT NULL,
    `source` VARCHAR(50) DEFAULT 'database',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_translation` (`locale`, `group`, `namespace`, `key`),
    INDEX `idx_locale` (`locale`),
    INDEX `idx_group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Step 2: Configure translator with database**

```php
<?php

use MonkeysLegion\I18n\TranslatorFactory;

// Setup PDO connection
$pdo = new PDO('mysql:host=localhost;dbname=myapp', 'user', 'password');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERR_EXCEPTION);

// Create translator with database support
$translator = TranslatorFactory::create([
    'locale' => 'en',
    'fallback' => 'en',
    'path' => __DIR__ . '/resources/lang',
    'pdo' => $pdo  // Enable database loader
]);

// Use translations
echo $translator->trans('pages.homepage.title');
```

**Step 3: Manage translations via database**

```php
<?php

// Insert/Update a translation
function saveTranslation(PDO $pdo, string $locale, string $group, string $key, string $value): void
{
    $stmt = $pdo->prepare("
        INSERT INTO translations (locale, `group`, `key`, value)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()
    ");

    $stmt->execute([$locale, $group, $key, $value]);
}

// Usage
saveTranslation($pdo, 'en', 'pages', 'homepage.title', 'Welcome to Our Store');
saveTranslation($pdo, 'es', 'pages', 'homepage.title', 'Bienvenido a Nuestra Tienda');
saveTranslation($pdo, 'en', 'pages', 'homepage.subtitle', 'Find the best products here!');

// Now use them
echo $translator->trans('pages.homepage.title');      // From database
echo $translator->trans('pages.homepage.subtitle');   // From database
```

**Bulk import from array:**

```php
<?php

function importTranslationsToDatabase(PDO $pdo, string $locale, string $group, array $translations): int
{
    $count = 0;
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("
            INSERT INTO translations (locale, `group`, `key`, value)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value)
        ");

        foreach (flattenArray($translations) as $key => $value) {
            $stmt->execute([$locale, $group, $key, $value]);
            $count++;
        }

        $pdo->commit();
        return $count;

    } catch (\Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function flattenArray(array $array, string $prefix = ''): array
{
    $result = [];
    foreach ($array as $key => $value) {
        $newKey = $prefix === '' ? $key : $prefix . '.' . $key;
        if (is_array($value)) {
            $result = array_merge($result, flattenArray($value, $newKey));
        } else {
            $result[$newKey] = (string)$value;
        }
    }
    return $result;
}

// Import nested array
$translations = [
    'welcome' => 'Hello!',
    'user' => [
        'login' => 'Log In',
        'logout' => 'Log Out',
        'profile' => 'My Profile'
    ]
];

importTranslationsToDatabase($pdo, 'en', 'auth', $translations);
// Creates: auth.welcome, auth.user.login, auth.user.logout, auth.user.profile
```

### Example 3: Hybrid System (JSON + Database)

The most powerful approach: use JSON files for static UI text and database for dynamic content.

**Best practice:** Files for code-level translations, database for CMS-managed content.

```php
<?php

use MonkeysLegion\I18n\TranslatorFactory;

$pdo = new PDO('mysql:host=localhost;dbname=myapp', 'user', 'password');

$translator = TranslatorFactory::create([
    'locale' => 'en',
    'fallback' => 'en',
    'path' => __DIR__ . '/resources/lang',
    'pdo' => $pdo  // Automatically uses BOTH sources
]);

// Translation lookup order:
// 1. Database (if found, use it)
// 2. JSON files (fallback)
// 3. Return key if not found

// Example: Static UI from JSON
echo $translator->trans('common.save');      // From: resources/lang/en/common.json
echo $translator->trans('common.cancel');    // From: resources/lang/en/common.json

// Example: Dynamic content from database
echo $translator->trans('pages.about.content');   // From: database
echo $translator->trans('products.123.title');    // From: database

// Database overrides JSON
// If "common.save" exists in BOTH, database version wins
```

**Real-world scenario:**

```php
<?php

class ProductController
{
    private $translator;
    private $pdo;

    public function show(int $productId)
    {
        $product = $this->getProduct($productId);

        // UI labels from JSON files (static, version-controlled)
        $addToCart = $this->translator->trans('products.add_to_cart');
        $outOfStock = $this->translator->trans('products.out_of_stock');
        $inStock = $this->translator->trans('products.in_stock');

        // Product content from database (dynamic, admin-editable)
        $title = $this->translator->trans("products.{$productId}.title");
        $description = $this->translator->trans("products.{$productId}.description");

        return view('products.show', compact(
            'product', 'title', 'description',
            'addToCart', 'outOfStock', 'inStock'
        ));
    }
}

// Admin can update product translations in database:
saveTranslation($pdo, 'en', 'products', '123.title', 'Premium Widget');
saveTranslation($pdo, 'en', 'products', '123.description', 'The best widget money can buy!');
saveTranslation($pdo, 'es', 'products', '123.title', 'Widget Premium');
saveTranslation($pdo, 'es', 'products', '123.description', '¡El mejor widget que el dinero puede comprar!');
```

## Advanced Features

### Locale Detection

Auto-detect user's preferred language from various sources:

```php
<?php

use MonkeysLegion\I18n\TranslatorFactory;

// Create system with auto-detection
$system = TranslatorFactory::createSystem([
    'default' => 'en',
    'fallback' => 'en',
    'supported' => ['en', 'es', 'fr', 'de'],
    'detectors' => ['url', 'session', 'cookie', 'header'],  // Priority order
    'path' => __DIR__ . '/resources/lang'
]);

$translator = $system['translator'];
$localeManager = $system['manager'];

// Auto-detect from:
// 1. URL: /es/products
// 2. Session: $_SESSION['locale']
// 3. Cookie: $_COOKIE['locale']
// 4. Accept-Language header

$detectedLocale = $localeManager->detectLocale();
$translator->setLocale($detectedLocale);
```

### Pluralization Rules

Supports ICU-compliant plural rules for all languages:

```php
<?php

// English (one/other)
$message = 'one: You have one message|other: You have :count messages';
echo $translator->choice($message, 1);  // You have one message
echo $translator->choice($message, 5);  // You have 5 messages

// Russian (one/few/many)
$message = 'one: :count товар|few: :count товара|other: :count товаров';
$translator->setLocale('ru');
echo $translator->choice($message, 1);   // 1 товар
echo $translator->choice($message, 2);   // 2 товара
echo $translator->choice($message, 5);   // 5 товаров

// Explicit numbers
$message = '{0} No items|{1} One item|[2,5] A few items|[6,*] Many items';
echo $translator->choice($message, 0);   // No items
echo $translator->choice($message, 1);   // One item
echo $translator->choice($message, 3);   // A few items
echo $translator->choice($message, 10);  // Many items
```

### Caching with MonkeysLegion-Cache

For high-performance caching, use the [MonkeysLegion-Cache](https://github.com/monkeyscloud/monkeyslegion-cache) package:

```bash
composer require monkeyscloud/monkeyslegion-cache
```

```php
<?php

use MonkeysLegion\I18n\TranslatorFactory;
use MonkeysLegion\Cache\CacheFactory;

// Create cache instance (PSR-16)
$cache = CacheFactory::create(['driver' => 'redis']);

$translator = TranslatorFactory::create([
    'locale' => 'en',
    'path' => __DIR__ . '/resources/lang',
    'cache' => $cache,      // Pass cache instance
    'cache_ttl' => 3600     // Cache time (1 hour)
]);

// Translations from BOTH files and database will be cached!
```

### Missing Translation Tracking

Track missing translations in development:

```php
<?php

$translator->setTrackMissing(true);

// Use translations
$translator->trans('some.missing.key');
$translator->trans('another.missing.key');

// Get missing translations
$missing = $translator->getMissingTranslations();
// ['en.some.missing.key', 'en.another.missing.key']

foreach ($missing as $key) {
    error_log("Missing translation: {$key}");
}
```

## API Reference

### Translator Methods

```php
// Basic translation
$translator->trans(string $key, array $replace = [], ?string $locale = null): string

// Pluralization
$translator->choice(string $key, int|float $count, array $replace = [], ?string $locale = null): string

// Check if translation exists
$translator->has(string $key, ?string $locale = null): bool

// Locale management
$translator->getLocale(): string
$translator->setLocale(string $locale): void
$translator->getFallbackLocale(): string
$translator->setFallbackLocale(string $locale): void

// Missing translations
$translator->setTrackMissing(bool $track): void
$translator->getMissingTranslations(): array
$translator->clearMissingTranslations(): void
```

### Helper Functions

```php
// Short syntax for translations
__('messages.welcome');
__('messages.greeting', ['name' => 'Yorch']);

// Pluralization
trans_choice('cart.items', $count);

// Get/Set locale
lang();          // Get current locale
lang('es');      // Set locale
```

## Configuration

Create a configuration file for easy setup:

**`config/i18n.php`**

```php
<?php

return [
    'locale' => env('LOCALE', 'en'),
    'fallback' => env('FALLBACK_LOCALE', 'en'),
    'path' => __DIR__ . '/../resources/lang',

    // Supported locales
    'supported_locales' => ['en', 'es', 'fr', 'de', 'it', 'pt'],

    // Auto-detection
    'detectors' => ['url', 'session', 'cookie', 'header'],

    // Database support (optional)
    'pdo' => $pdo ?? null,

    // Caching (optional)
    'cache' => $cache ?? null,
    'cache_ttl' => 3600,

    // Development
    'track_missing' => env('APP_DEBUG', false),
];
```

**Usage:**

```php
<?php

$config = require 'config/i18n.php';
$translator = TranslatorFactory::create($config);
```

## Testing

The package includes comprehensive tests:

```bash
# Run all tests
composer test

# Run static analysis
composer phpstan

# Run quality checks (PHPStan + PHPUnit)
composer quality
```

## Documentation

- **[USAGE.md](USAGE.md)** - Detailed usage guide
- **[HYBRID_TRANSLATIONS.md](HYBRID_TRANSLATIONS.md)** - JSON + Database examples
- **[QUICKSTART.md](QUICKSTART.md)** - Get started quickly
- **[TESTING.md](TESTING.md)** - Testing guidelines

## Requirements

- PHP 8.4 or higher
- ext-json
- ext-mbstring
- PDO with MySQL/PostgreSQL driver (optional, for database translations)
- PSR-16 CacheInterface implementation (optional, for caching)

## License

MIT

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Credits

Created by [MonkeysCloud](https://monkeys.cloud)
