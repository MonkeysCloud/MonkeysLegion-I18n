<?php

/**
 * MonkeysLegion I18n — Quick Start Example
 *
 * Basic setup with JSON files, no database required.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MonkeysLegion\I18n\Translator;
use MonkeysLegion\I18n\TranslatorFactory;
use MonkeysLegion\I18n\Loaders\FileLoader;
use MonkeysLegion\I18n\NumberFormatter;
use MonkeysLegion\I18n\DateFormatter;

// ═══════════════════════════════════════════════════════════════════
// 1. Basic Translation
// ═══════════════════════════════════════════════════════════════════

$translator = TranslatorFactory::create([
    'locale'   => 'en',
    'fallback' => 'en',
    'path'     => __DIR__ . '/resources/lang',
]);

echo $translator->trans('messages.welcome');
// → "Welcome!"

echo $translator->trans('messages.greeting', ['name' => 'Yorch']);
// → "Hello, Yorch!"

// ═══════════════════════════════════════════════════════════════════
// 2. Parameter Replacement (case-sensitive)
// ═══════════════════════════════════════════════════════════════════

// :name → exact value
echo $translator->trans('messages.greeting', ['name' => 'Yorch']);
// → "Hello, Yorch!"

// :NAME → UPPERCASED
echo $translator->trans('messages.farewell', ['name' => 'Yorch']);
// → "Goodbye, YORCH!"

// :Name → Capitalized
echo $translator->trans('messages.title', ['name' => 'yorch']);
// → "Welcome Yorch"

// ═══════════════════════════════════════════════════════════════════
// 3. Pluralization
// ═══════════════════════════════════════════════════════════════════

// "{0} No items|{1} One item|[2,*] :count items"
echo $translator->choice('messages.items', 0);   // → "No items"
echo $translator->choice('messages.items', 1);   // → "One item"
echo $translator->choice('messages.items', 42);  // → "42 items"

// ═══════════════════════════════════════════════════════════════════
// 4. Locale Switching
// ═══════════════════════════════════════════════════════════════════

$translator->setLocale('es');
echo $translator->trans('messages.welcome');
// → "¡Bienvenido!"

$translator->setLocale('en');
echo $translator->trans('messages.welcome');
// → "Welcome!"

// ═══════════════════════════════════════════════════════════════════
// 5. Number Formatting
// ═══════════════════════════════════════════════════════════════════

$nf = new NumberFormatter();

echo $nf->decimal(1234567, 'en');     // → "1,234,567"
echo $nf->currency(42.50, 'USD', 'en'); // → "$42.50"
echo $nf->compact(1_500_000);         // → "1.5M"
echo $nf->ordinal(3, 'en');           // → "3rd"
echo $nf->fileSize(1_572_864);        // → "1.50 MB"
echo $nf->percent(0.156, 'en', 1);    // → "15.6%"

// ═══════════════════════════════════════════════════════════════════
// 6. Date Formatting
// ═══════════════════════════════════════════════════════════════════

$df = new DateFormatter();
$date = new DateTimeImmutable('2026-01-15');

echo $df->format($date, 'short');    // → "1/15/26"
echo $df->format($date, 'medium');   // → "Jan 15, 2026"
echo $df->format($date, 'long');     // → "January 15, 2026"
echo $df->format($date, 'full');     // → "Thursday, January 15, 2026"

// Relative time
$now = new DateTimeImmutable();
$past = $now->modify('-2 hours');
echo $df->relative($past, 'en', $now); // → "2 hours ago"
echo $df->relative($past, 'es', $now); // → "hace 2 horas"

// ═══════════════════════════════════════════════════════════════════
// 7. Missing Translation Tracking
// ═══════════════════════════════════════════════════════════════════

$translator->setTrackMissing(true);
$translator->trans('messages.missing_key');

$missing = $translator->getMissingTranslations();
// → ["en.messages.missing_key"]

echo "Missing: " . implode(', ', $missing) . "\n";
