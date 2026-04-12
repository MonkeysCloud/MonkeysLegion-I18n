<?php

/**
 * MonkeysLegion I18n — Real-World Patterns
 *
 * Production patterns: CMS, e-commerce, multi-tenant, admin panel.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MonkeysLegion\I18n\Management\TranslationManager;
use MonkeysLegion\I18n\TranslatorFactory;

// Assumes $pdo and $cache are already set up (see 02-database-setup.php)

$translator = TranslatorFactory::create([
    'locale'   => 'en',
    'fallback' => 'en',
    'path'     => __DIR__ . '/resources/lang',
    'pdo'      => $pdo,
    'cache'    => $cache,
]);

$manager = new TranslationManager(
    pdo: $pdo,
    translator: $translator,
    filePath: __DIR__ . '/resources/lang',
);

// ═══════════════════════════════════════════════════════════════════
// 1. CMS Page Management
// ═══════════════════════════════════════════════════════════════════

// Store page content per locale
$manager->set('en', 'cms', 'pages.about.title', 'About Us');
$manager->set('en', 'cms', 'pages.about.content', '<p>We build software...</p>');
$manager->set('en', 'cms', 'pages.about.meta', 'Learn about our company');

$manager->set('es', 'cms', 'pages.about.title', 'Sobre Nosotros');
$manager->set('es', 'cms', 'pages.about.content', '<p>Construimos software...</p>');
$manager->set('es', 'cms', 'pages.about.meta', 'Conoce nuestra empresa');

// Retrieve all CMS content
$enPage = $manager->getGroup('en', 'cms');
$esPage = $manager->getGroup('es', 'cms');

echo "EN title: {$enPage['pages']['about']['title']}\n";
echo "ES title: {$esPage['pages']['about']['title']}\n";

// ═══════════════════════════════════════════════════════════════════
// 2. E-commerce Product Catalog
// ═══════════════════════════════════════════════════════════════════

function translateProduct(TranslationManager $m, int $id, string $locale, array $data): void
{
    $prefix = "product_{$id}";

    $m->set($locale, 'products', "{$prefix}.name", $data['name']);
    $m->set($locale, 'products', "{$prefix}.description", $data['description']);
    $m->set($locale, 'products', "{$prefix}.short", $data['short']);

    if (isset($data['features'])) {
        foreach ($data['features'] as $i => $feature) {
            $m->set($locale, 'products', "{$prefix}.features.{$i}", $feature);
        }
    }
}

// Add product translations
translateProduct($manager, 42, 'en', [
    'name'        => 'Premium Widget Pro',
    'description' => 'The ultimate widget for professionals',
    'short'       => 'Pro-grade widget',
    'features'    => ['Cloud sync', 'AI-powered', '5-year warranty'],
]);

translateProduct($manager, 42, 'es', [
    'name'        => 'Widget Premium Pro',
    'description' => 'El widget definitivo para profesionales',
    'short'       => 'Widget profesional',
    'features'    => ['Sync en la nube', 'Con IA', 'Garantía de 5 años'],
]);

// Get product in current locale
$product = $manager->getGroup('en', 'products');
echo "Product: {$product['product_42']['name']}\n";

// ═══════════════════════════════════════════════════════════════════
// 3. Multi-Tenant Branding
// ═══════════════════════════════════════════════════════════════════

// Each tenant gets custom branding via namespaces
function setTenantBranding(TranslationManager $m, string $tenant, string $locale, array $branding): void
{
    foreach ($branding as $key => $value) {
        $m->set($locale, 'branding', $key, $value, namespace: $tenant);
    }
}

setTenantBranding($manager, 'acme_corp', 'en', [
    'app.name'          => 'Acme Dashboard',
    'app.tagline'       => 'Built for speed',
    'footer.copyright'  => '© 2026 Acme Corp',
    'email.signature'   => 'The Acme Team',
]);

setTenantBranding($manager, 'acme_corp', 'es', [
    'app.name'          => 'Panel Acme',
    'app.tagline'       => 'Construido para velocidad',
    'footer.copyright'  => '© 2026 Acme Corp',
    'email.signature'   => 'El equipo de Acme',
]);

// ═══════════════════════════════════════════════════════════════════
// 4. Admin Panel Controller Pattern
// ═══════════════════════════════════════════════════════════════════

class AdminTranslationController
{
    public function __construct(
        private readonly TranslationManager $manager,
    ) {}

    /** List all translations for a locale/group */
    public function index(string $locale, string $group): array
    {
        return [
            'locale'       => $locale,
            'group'        => $group,
            'translations' => $this->manager->getAllMerged($locale, $group),
            'stats'        => $this->manager->getStats(),
        ];
    }

    /** Update translations from form POST */
    public function update(string $locale, string $group, array $data): int
    {
        $count = 0;

        foreach ($data as $key => $value) {
            $this->manager->set($locale, $group, $key, $value, source: 'admin');
            $count++;
        }

        return $count;
    }

    /** Bulk import from uploaded JSON */
    public function import(string $locale, string $group, string $json): int
    {
        $translations = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $this->manager->importArray(
            $locale,
            $group,
            $translations,
            source: 'admin_import',
            overwrite: true,
        );
    }

    /** Export for download */
    public function export(string $locale, string $group): string
    {
        return $this->manager->exportToFile($locale, $group, 'json');
    }

    /** Search all translations */
    public function search(string $query, ?string $locale = null): array
    {
        return $this->manager->search($query, $locale);
    }
}

// ═══════════════════════════════════════════════════════════════════
// 5. REST API Endpoints
// ═══════════════════════════════════════════════════════════════════

class TranslationApiController
{
    public function __construct(
        private readonly TranslationManager $manager,
    ) {}

    /** GET /api/translations/{locale}/{group} */
    public function index(string $locale, string $group): string
    {
        return json_encode([
            'data' => $this->manager->getAllMerged($locale, $group),
        ], JSON_THROW_ON_ERROR);
    }

    /** PUT /api/translations/{locale}/{group}/{key} */
    public function update(string $locale, string $group, string $key, string $body): string
    {
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $this->manager->set($locale, $group, $key, $data['value'], source: 'api');

        return json_encode(['success' => true], JSON_THROW_ON_ERROR);
    }

    /** POST /api/translations/import */
    public function import(string $body): string
    {
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $count = $this->manager->importArray(
            $data['locale'],
            $data['group'],
            $data['translations'],
            source: 'api',
            overwrite: $data['overwrite'] ?? false,
        );

        return json_encode(['imported' => $count], JSON_THROW_ON_ERROR);
    }
}

// ═══════════════════════════════════════════════════════════════════
// 6. Deployment Workflow: File → DB → File
// ═══════════════════════════════════════════════════════════════════

$locales = ['en', 'es', 'fr'];
$groups = ['messages', 'validation', 'pages'];

// Step 1: Development — work with files
// Edit resources/lang/en/messages.json in your IDE

// Step 2: Deploy — import files to database
echo "Importing to database...\n";

foreach ($locales as $locale) {
    foreach ($groups as $group) {
        $count = $manager->sync($locale, $group, 'file_to_db', overwrite: false);
        echo "  {$locale}/{$group}: {$count} imported\n";
    }
}

// Step 3: Production — admin edits in browser (stored in DB)

// Step 4: Export — save DB changes to files for Git
echo "\nExporting to files...\n";

foreach ($locales as $locale) {
    foreach ($groups as $group) {
        $count = $manager->sync($locale, $group, 'db_to_file');
        echo "  {$locale}/{$group}: {$count} exported\n";
    }
}

// git add resources/lang/
// git commit -m "Updated translations from production"
