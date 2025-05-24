# MonkeysLegion I18n & Localization

A simple, filesystem-driven translation component for MonkeysLegion.  
Load JSON files per locale, translate message keys with placeholders, and use `@lang` in your MLView templates.

---

## 📦 Installation

```bash
composer require monkeyscloud/monkeyslegion-i18n:^1.0@dev
```

Ensure your composer.json autoloads the PSR-4 namespace:

```json
"autoload": {
  "psr-4": {
    "MonkeysLegion\\I18n\\": "src/"
  }
}
```

Then run:

```bash
composer dump-autoload
```

## 🔧 Configuration
Register the translator in your DI container (config/app.php):
```php
use MonkeysLegion\I18n\Translator;

Translator::class => fn($c) => new Translator(
    // get locale from config or request
    $c->get(MonkeysLegion\Mlc\Config::class)->get('app.locale', 'en'),
    base_path('resources/lang'),
    'en' // fallback locale
),
```
## 🗂 Directory Structure
```plaintext
resources/lang/
├─ en.json      # fallback / default locale
├─ fr.json      # French translations
└─ es.json      # Spanish translations
```
Each file is a flat JSON map:
```json
{
  "welcome":          "Welcome to MonkeysLegion!",
  "posts.count":      "There are :count posts",
  "user.greeting":    "Hello, :name!"
}
```

## 🚀 Usage
### In PHP

Fetch the service and call trans():
```php
/** @var Translator $t */
$t = $container->get(Translator::class);

echo $t->trans('welcome');
// → “Welcome to MonkeysLegion!”

echo $t->trans('posts.count', ['count'=>5]);
// → “There are 5 posts”
```
Switch locale at runtime:
```php
$t->setLocale('fr');
echo $t->trans('welcome');
// → “Bienvenue sur MonkeysLegion !”
```
### 🖋 Template Integration
1) Helper

Add a small global helper (e.g. in src/Template/helpers.php):
```php
if (! function_exists('trans')) {
    function trans(string $key, array $replace = []): string {
        return ML_CONTAINER->get(\MonkeysLegion\I18n\Translator::class)
                           ->trans($key, $replace);
    }
}
```
2) Compiler Directive

In your MLView Compiler, add:
```php
// after processing other directives...
$php = preg_replace_callback(
    "/@lang$begin:math:text$['\\"](.+?)['\\"](?:\\s*,\\s*(\\[[^\\]]*\\]))?$end:math:text$/",
    fn($m) => "<?php echo trans('{$m[1]}', {$m[2] ?? '[]'}); ?>",
    $php
);
```
Then in your templates:
```html
<h1>@lang('welcome')</h1>
<p>@lang('posts.count', ['count'=>$count])</p>
```

## ⚙️ Fallback Behavior
- If a key is missing in the current locale, it falls back to the “fallback” locale (default en).
- If still missing, the key itself is returned (so you see "user.greeting" rather than an error).

⸻
