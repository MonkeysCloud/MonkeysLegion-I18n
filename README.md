# MonkeysLegion I18n

Production-ready internationalization and localization package for MonkeysLegion framework.

## Features

- 🌍 **Multiple Translation Sources**: JSON files, PHP arrays, Database, Cache
- 📝 **Pluralization**: ICU-compliant plural rules for 200+ languages
- 🎯 **Auto Locale Detection**: URL, Session, Headers, Cookies, Browser
- 🚀 **High Performance**: Built-in caching with MonkeysLegion-Cache integration
- 🔄 **Fallback Chain**: Locale → Fallback → Default
- 📦 **Namespacing**: Package-level translations (vendor::file.key)
- 🎨 **Template Integration**: Custom directives for MonkeysLegion-Template
- 📊 **Missing Translation Tracking**: Development mode tracking
- 🔍 **Parameter Replacement**: Named placeholders with modifiers
- 📅 **Date/Time Localization**: Format dates per locale
- 💰 **Number/Currency Formatting**: Locale-aware formatting

## Installation

```bash
composer require monkeyscloud/monkeyslegion-i18n
```

**Note**: The `php-intl` extension is **optional**. The package works perfectly without it, with fallback implementations for all features. See [INTL_GUIDE.md](INTL_GUIDE.md) for details.

## Quick Start

```php
use MonkeysLegion\I18n\TranslatorFactory;

// Create translator
$translator = TranslatorFactory::create([
    'locale' => 'es',
    'fallback' => 'en',
    'path' => __DIR__ . '/resources/lang'
]);

// Basic translation
echo $translator->trans('welcome.message'); 
// "Bienvenido a MonkeysLegion"

// With replacements
echo $translator->trans('welcome.user', ['name' => 'Yorch']);
// "Bienvenido, Yorch"

// Pluralization
echo $translator->choice('messages.count', 5);
// "Tienes 5 mensajes"
```

## Advanced Usage

### Namespace Translations
```php
$translator->trans('monkeysmail::errors.invalid_email');
```

### Locale Detection Middleware
```php
$router->middleware(LocaleMiddleware::class);
```

### Template Directives
```blade
@lang('welcome.title')
@choice('cart.items', $count)
@date($timestamp, 'long')
@currency($amount, 'USD')
```

## Configuration

See `config/i18n.php` for full configuration options.
