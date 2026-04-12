<?php

/**
 * MonkeysLegion I18n — All Loaders
 *
 * Shows every loader type: JSON, PHP, MLC, Database, Cache, Compiled.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MonkeysLegion\I18n\Loaders\FileLoader;
use MonkeysLegion\I18n\Loaders\MlcLoader;
use MonkeysLegion\I18n\Loaders\DatabaseLoader;
use MonkeysLegion\I18n\Loaders\CacheLoader;
use MonkeysLegion\I18n\Loaders\CompiledLoader;
use MonkeysLegion\I18n\Translator;
use MonkeysLegion\I18n\TranslatorFactory;

// ═══════════════════════════════════════════════════════════════════
// 1. FileLoader — JSON + PHP files
// ═══════════════════════════════════════════════════════════════════
//
// Directory structure:
//   resources/lang/
//   ├── en/
//   │   ├── messages.json      ← { "welcome": "Welcome!" }
//   │   └── validation.php     ← return ['required' => 'Required'];
//   ├── es/
//   │   ├── messages.json
//   │   └── validation.php
//   └── fr/
//       └── messages.json

$fileLoader = new FileLoader(__DIR__ . '/resources/lang');

$messages = $fileLoader->load('en', 'messages');
// → ['welcome' => 'Welcome!', 'greeting' => 'Hello, :name!']

$validation = $fileLoader->load('en', 'validation');
// → ['required' => 'Required', 'email' => 'Invalid email']

// ═══════════════════════════════════════════════════════════════════
// 2. MlcLoader — MonkeysLegion Configuration Format
// ═══════════════════════════════════════════════════════════════════
//
// Directory structure:
//   resources/lang/
//   ├── en/
//   │   └── messages.mlc       ← key = value format
//   └── es/
//       └── messages.mlc
//
// MLC file format (resources/lang/en/messages.mlc):
//
//   # Comments start with # or ;
//   welcome = Welcome!
//   greeting = Hello, :name!
//   farewell = "Goodbye, :NAME!"
//
//   # Dot notation creates nested arrays
//   nav.home = Home
//   nav.about = About Us
//   nav.contact = Contact
//
//   # Double-quoted strings support escape sequences
//   multiline = "Line one\nLine two"

$mlcLoader = new MlcLoader(__DIR__ . '/resources/lang');

$messages = $mlcLoader->load('en', 'messages');
// → ['welcome' => 'Welcome!', 'greeting' => 'Hello, :name!',
//    'nav' => ['home' => 'Home', 'about' => 'About Us', ...]]

// ═══════════════════════════════════════════════════════════════════
// 3. MLC Sectioned Format — Multiple groups per file
// ═══════════════════════════════════════════════════════════════════
//
// Single-file format (resources/lang/es.mlc):
//
//   [messages]
//   welcome = ¡Bienvenido!
//   greeting = ¡Hola, :name!
//
//   [validation]
//   required = Este campo es obligatorio.
//   email = Ingrese un correo válido.
//
//   [errors]
//   not_found = Página no encontrada.

// Loaded automatically — MlcLoader detects [section] headers

// ═══════════════════════════════════════════════════════════════════
// 4. DatabaseLoader — SQL Table
// ═══════════════════════════════════════════════════════════════════

// Works with MySQL, MariaDB, PostgreSQL, SQLite
// $pdo = new PDO('mysql:host=localhost;dbname=app', 'root', 'secret');
// $pdo = new PDO('pgsql:host=localhost;dbname=app', 'postgres', 'secret');
// $pdo = new PDO('sqlite:' . __DIR__ . '/database.sqlite');

// $dbLoader = new DatabaseLoader($pdo, 'translations');
// $messages = $dbLoader->load('en', 'messages');

// ═══════════════════════════════════════════════════════════════════
// 5. CacheLoader — PSR-16 Cache Decorator
// ═══════════════════════════════════════════════════════════════════

// Wrap any loader with PSR-16 cache
// $cache = new YourPsr16Cache(); // Redis, Memcached, APCu, etc.

// $cachedLoader = new CacheLoader(
//     loader: $fileLoader,  // or $dbLoader, $mlcLoader
//     cache: $cache,
//     ttl: 3600,            // 1 hour (±10% jitter)
//     prefix: 'i18n',
// );

// First call: loads from file, stores in cache
// $messages = $cachedLoader->load('en', 'messages');

// Second call: reads from cache (fast!)
// $messages = $cachedLoader->load('en', 'messages');

// Cache management
// $cachedLoader->forget('en', 'messages');    // Forget specific group
// $cachedLoader->forgetLocale('en');           // Forget all English
// $cachedLoader->forgetNamespace('vendor');    // Forget vendor namespace
// $cachedLoader->flush();                      // Clear everything

// ═══════════════════════════════════════════════════════════════════
// 6. CompiledLoader — Opcache-Optimized Production
// ═══════════════════════════════════════════════════════════════════

// 10-50x faster than JSON decode — compiles to native PHP arrays
$compiledLoader = new CompiledLoader(
    source: $fileLoader,
    compilePath: __DIR__ . '/storage/cache/i18n',
);

// First call: compiles JSON → PHP, then loads via require
$messages = $compiledLoader->load('en', 'messages');

// Next calls: loads directly from compiled PHP (opcache-friendly)
$messages = $compiledLoader->load('en', 'messages');

// Check freshness (returns false if source changed)
$fresh = $compiledLoader->isFresh('en', 'messages');

// Recompile after editing source files
$compiledLoader->compile('en', 'messages');

// Invalidate compiled file
$compiledLoader->invalidate('en');

// ═══════════════════════════════════════════════════════════════════
// 7. Stack Multiple Loaders (File + Database)
// ═══════════════════════════════════════════════════════════════════

// Loaders are loaded in order; later loaders override earlier ones
// File: base translations → DB: admin overrides

$translator = new Translator('en', 'en');
$translator->addLoader($fileLoader);     // Base
// $translator->addLoader($dbLoader);    // Override

echo $translator->trans('messages.welcome');
// File has "Welcome!" but DB has "Welcome to Our App!" → "Welcome to Our App!"

// ═══════════════════════════════════════════════════════════════════
// 8. Factory: Complete Setup in One Call
// ═══════════════════════════════════════════════════════════════════

// $translator = TranslatorFactory::create([
//     'locale'        => 'en',
//     'fallback'      => 'en',
//     'path'          => __DIR__ . '/resources/lang',
//     'pdo'           => $pdo,                           // Database (optional)
//     'cache'         => $cache,                         // PSR-16 cache (optional)
//     'cache_ttl'     => 3600,
//     'compiled_path' => __DIR__ . '/storage/cache/i18n', // Compiled (optional)
//     'track_missing' => true,                            // Debug mode
//     'namespaces'    => [
//         'payments' => __DIR__ . '/vendor/payments/lang',
//     ],
// ]);
