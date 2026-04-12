<?php

/**
 * MonkeysLegion I18n — Database Setup & Usage
 *
 * Shows how to set up database translations with MySQL, PostgreSQL, and SQLite.
 * Includes CLI commands, schema creation, and CRUD operations.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MonkeysLegion\I18n\Loaders\DatabaseLoader;
use MonkeysLegion\I18n\Management\TranslationManager;
use MonkeysLegion\I18n\Translator;
use MonkeysLegion\I18n\TranslatorFactory;

// ═══════════════════════════════════════════════════════════════════
// CLI COMMANDS TO INSTALL THE SCHEMA
// ═══════════════════════════════════════════════════════════════════
//
// MySQL / MariaDB:
//   mysql -u root -p your_database < schema/mysql.sql
//
// PostgreSQL:
//   psql -U postgres -d your_database -f schema/pgsql.sql
//
// SQLite:
//   sqlite3 storage/database.sqlite < schema/sqlite.sql
//
// Or via PHP (see below for programmatic creation)
// ═══════════════════════════════════════════════════════════════════

// ═══════════════════════════════════════════════════════════════════
// 1. Connect to Your Database
// ═══════════════════════════════════════════════════════════════════

// --- MySQL / MariaDB ---
$pdo = new PDO(
    dsn: 'mysql:host=127.0.0.1;port=3306;dbname=your_app;charset=utf8mb4',
    username: 'root',
    password: 'secret',
    options: [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
);

// --- PostgreSQL ---
// $pdo = new PDO(
//     dsn: 'pgsql:host=127.0.0.1;port=5432;dbname=your_app',
//     username: 'postgres',
//     password: 'secret',
//     options: [
//         PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
//         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
//     ],
// );

// --- SQLite ---
// $pdo = new PDO(
//     dsn: 'sqlite:' . __DIR__ . '/storage/database.sqlite',
//     options: [
//         PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
//         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
//     ],
// );

// ═══════════════════════════════════════════════════════════════════
// 2. Create Schema Programmatically (alternative to CLI)
// ═══════════════════════════════════════════════════════════════════

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

$schemaFile = match ($driver) {
    'pgsql'  => __DIR__ . '/../schema/pgsql.sql',
    'sqlite' => __DIR__ . '/../schema/sqlite.sql',
    default  => __DIR__ . '/../schema/mysql.sql',
};

// Read and execute schema
$sql = file_get_contents($schemaFile);

if ($sql !== false) {
    $pdo->exec($sql);
    echo "✓ Schema created for {$driver}\n";
}

// ═══════════════════════════════════════════════════════════════════
// 3. Use DatabaseLoader Directly
// ═══════════════════════════════════════════════════════════════════

// The DatabaseLoader reads from the translations table
$dbLoader = new DatabaseLoader($pdo, 'translations');

// Create translator with both file and database sources
$translator = new Translator('en', 'en');
$translator->addLoader(new \MonkeysLegion\I18n\Loaders\FileLoader(__DIR__ . '/resources/lang'));
$translator->addLoader($dbLoader); // DB overrides file values

// ═══════════════════════════════════════════════════════════════════
// 4. Use TranslatorFactory (recommended)
// ═══════════════════════════════════════════════════════════════════

$translator = TranslatorFactory::create([
    'locale'   => 'en',
    'fallback' => 'en',
    'path'     => __DIR__ . '/resources/lang', // JSON/PHP files
    'pdo'      => $pdo,                        // Database (overrides files)
]);

// File translations work
echo $translator->trans('messages.welcome');
// → "Welcome!" (from files)

// Database translations override files
echo $translator->trans('messages.welcome');
// → "Welcome to Our App!" (if overridden in DB)

// ═══════════════════════════════════════════════════════════════════
// 5. TranslationManager: Full CRUD
// ═══════════════════════════════════════════════════════════════════

$manager = new TranslationManager(
    pdo: $pdo,
    translator: $translator,
    filePath: __DIR__ . '/resources/lang',
    tableName: 'translations',
);

// CREATE a translation
$manager->set(
    locale: 'en',
    group: 'messages',
    key: 'new_feature',
    value: 'Check out our new feature!',
    source: 'admin',
);

// READ
$value = $manager->get('en', 'messages', 'new_feature');
echo "Got: {$value}\n"; // → "Check out our new feature!"

// UPDATE (same method, overwrites)
$manager->set(
    locale: 'en',
    group: 'messages',
    key: 'new_feature',
    value: 'Updated: Check out our amazing new feature!',
    source: 'admin',
);

// DELETE
$deleted = $manager->delete('en', 'messages', 'new_feature');
echo "Deleted: " . ($deleted ? 'yes' : 'no') . "\n";

// ═══════════════════════════════════════════════════════════════════
// 6. Import File → Database
// ═══════════════════════════════════════════════════════════════════

// Import all translations from resources/lang/en/messages.json to DB
$count = $manager->importFromFile('en', 'messages', overwrite: false);
echo "Imported {$count} translations\n";

// Import from array
$count = $manager->importArray('es', 'messages', [
    'welcome'  => '¡Bienvenido!',
    'greeting' => '¡Hola, :name!',
    'nested'   => [
        'key' => 'Valor anidado',
    ],
], source: 'manual', overwrite: true);
echo "Imported {$count} Spanish translations\n";

// ═══════════════════════════════════════════════════════════════════
// 7. Export Database → File
// ═══════════════════════════════════════════════════════════════════

// Export to JSON
$file = $manager->exportToFile('en', 'messages', 'json');
echo "Exported to: {$file}\n";
// → resources/lang/en/messages.json

// Export to PHP array
$file = $manager->exportToFile('en', 'messages', 'php');
echo "Exported to: {$file}\n";
// → resources/lang/en/messages.php

// ═══════════════════════════════════════════════════════════════════
// 8. Sync File ↔ Database
// ═══════════════════════════════════════════════════════════════════

// Development workflow:
// 1. Import files to database (for admin editing)
$manager->sync('en', 'messages', 'file_to_db', overwrite: false);

// 2. Admin edits in browser...

// 3. Export database to file (for deployment/git)
$manager->sync('en', 'messages', 'db_to_file');

// ═══════════════════════════════════════════════════════════════════
// 9. Search & Statistics
// ═══════════════════════════════════════════════════════════════════

// Search translations
$results = $manager->search('welcome', 'en');

foreach ($results as $row) {
    echo "{$row['locale']}.{$row['group']}.{$row['key']}: {$row['value']}\n";
}

// Statistics
$stats = $manager->getStats();
echo "Total: {$stats['total']}\n";
echo "Locales: " . implode(', ', array_keys($stats['by_locale'])) . "\n";
echo "Groups: " . implode(', ', array_keys($stats['by_group'])) . "\n";

// ═══════════════════════════════════════════════════════════════════
// 10. Batch Update
// ═══════════════════════════════════════════════════════════════════

$count = $manager->batchUpdate([
    ['locale' => 'en', 'group' => 'messages', 'key' => 'welcome', 'value' => 'Hi!'],
    ['locale' => 'en', 'group' => 'messages', 'key' => 'goodbye', 'value' => 'Bye!'],
    ['locale' => 'es', 'group' => 'messages', 'key' => 'welcome', 'value' => '¡Hola!'],
]);
echo "Batch updated: {$count}\n";

// ═══════════════════════════════════════════════════════════════════
// 11. Find Missing Translations
// ═══════════════════════════════════════════════════════════════════

$missing = $manager->findMissing('es', 'messages');
echo "Missing Spanish translations:\n";

foreach ($missing as $key) {
    echo "  ✗ {$key}\n";
}

// ═══════════════════════════════════════════════════════════════════
// 12. Merged View (File + Database)
// ═══════════════════════════════════════════════════════════════════

// Get all translations with database overriding files
$all = $manager->getAllMerged('en', 'messages');
echo "All translations (" . count($all) . " keys):\n";
print_r($all);
