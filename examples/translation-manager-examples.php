<?php

/**
 * Practical Examples: Managing Translations from Files and Database
 * 
 * This file shows real-world usage of the TranslationManager
 */

use MonkeysLegion\I18n\Management\TranslationManager;
use MonkeysLegion\I18n\TranslatorFactory;

// =============================================================================
// Setup
// =============================================================================

// Create translator with both file and database support
$config = [
    'locale' => 'en',
    'fallback' => 'en',
    'path' => __DIR__ . '/resources/lang',
    'pdo' => $pdo,
    'cache' => $cache,
    'supported_locales' => ['en', 'es', 'fr'],
];

$system = TranslatorFactory::createSystem($config);
$translator = $system['translator'];

// Create translation manager
$manager = new TranslationManager(
    $pdo,
    $translator,
    __DIR__ . '/resources/lang'
);

// =============================================================================
// Example 1: Add Translation to Database
// =============================================================================

// Add a single translation
$manager->set(
    locale: 'en',
    group: 'pages',
    key: 'home.banner.title',
    value: 'Welcome to Our Store!',
    source: 'admin'
);

// Use it
echo $translator->trans('pages.home.banner.title');
// Output: Welcome to Our Store!

// =============================================================================
// Example 2: Bulk Import from Array
// =============================================================================

// Import product descriptions
$productTranslations = [
    'products' => [
        'premium-widget' => [
            'title' => 'Premium Widget',
            'description' => 'The best widget on the market!',
            'features' => [
                'feature1' => 'Durable construction',
                'feature2' => 'Easy to use',
                'feature3' => '5-year warranty',
            ]
        ]
    ]
];

$count = $manager->importArray(
    locale: 'en',
    group: 'products',
    translations: $productTranslations['products'],
    source: 'api_import',
    overwrite: true
);

echo "Imported {$count} translations\n";

// =============================================================================
// Example 3: Import from JSON File to Database
// =============================================================================

// You have resources/lang/en/messages.json
// Import it to database for admin editing

$count = $manager->importFromFile(
    locale: 'en',
    group: 'messages',
    overwrite: false // Don't overwrite existing database entries
);

echo "Imported {$count} translations from file to database\n";

// =============================================================================
// Example 4: Export Database to File
// =============================================================================

// After editing in admin panel, export to file for version control
$filename = $manager->exportToFile(
    locale: 'en',
    group: 'pages',
    format: 'json'
);

echo "Exported to: {$filename}\n";
// Creates: resources/lang/en/pages.json

// =============================================================================
// Example 5: Sync Between File and Database
// =============================================================================

// Sync file to database (for editing)
$count = $manager->sync(
    locale: 'en',
    group: 'messages',
    direction: 'file_to_db',
    overwrite: false
);

// Edit in admin panel...

// Sync database back to file (for deployment)
$manager->sync(
    locale: 'en',
    group: 'messages',
    direction: 'db_to_file'
);

// =============================================================================
// Example 6: CMS Page Management
// =============================================================================

class PageManager
{
    private TranslationManager $manager;
    
    public function createPage(string $slug, array $content)
    {
        foreach ($content as $locale => $data) {
            // Store title
            $this->manager->set(
                $locale,
                'cms',
                "pages.{$slug}.title",
                $data['title'],
                source: 'cms'
            );
            
            // Store content
            $this->manager->set(
                $locale,
                'cms',
                "pages.{$slug}.content",
                $data['content'],
                source: 'cms'
            );
            
            // Store meta
            $this->manager->set(
                $locale,
                'cms',
                "pages.{$slug}.meta_description",
                $data['meta_description'],
                source: 'cms'
            );
        }
    }
    
    public function updatePage(string $slug, string $locale, array $updates)
    {
        foreach ($updates as $field => $value) {
            $this->manager->set(
                $locale,
                'cms',
                "pages.{$slug}.{$field}",
                $value,
                source: 'cms'
            );
        }
    }
}

$pageManager = new PageManager($manager);

// Create a new page
$pageManager->createPage('about-us', [
    'en' => [
        'title' => 'About Us',
        'content' => '<p>We are a company...</p>',
        'meta_description' => 'Learn about our company',
    ],
    'es' => [
        'title' => 'Sobre Nosotros',
        'content' => '<p>Somos una empresa...</p>',
        'meta_description' => 'Conoce nuestra empresa',
    ]
]);

// =============================================================================
// Example 7: E-commerce Product Translations
// =============================================================================

class ProductTranslationService
{
    private TranslationManager $manager;
    
    public function setProductTranslation(
        int $productId,
        string $locale,
        array $data
    ): void {
        $prefix = "products.product_{$productId}";
        
        $this->manager->set($locale, 'products', "{$prefix}.name", $data['name']);
        $this->manager->set($locale, 'products', "{$prefix}.description", $data['description']);
        $this->manager->set($locale, 'products', "{$prefix}.short_description", $data['short_description']);
        
        // Features
        if (isset($data['features'])) {
            foreach ($data['features'] as $index => $feature) {
                $this->manager->set(
                    $locale,
                    'products',
                    "{$prefix}.features.{$index}",
                    $feature
                );
            }
        }
    }
    
    public function getProductTranslation(int $productId, string $locale): array
    {
        $prefix = "products.product_{$productId}";
        
        return [
            'name' => $this->manager->get($locale, 'products', "{$prefix}.name"),
            'description' => $this->manager->get($locale, 'products', "{$prefix}.description"),
            'short_description' => $this->manager->get($locale, 'products', "{$prefix}.short_description"),
        ];
    }
}

$productService = new ProductTranslationService($manager);

// Set translations for a product
$productService->setProductTranslation(123, 'en', [
    'name' => 'Premium Widget Pro',
    'description' => 'The ultimate widget for professionals...',
    'short_description' => 'Professional-grade widget',
    'features' => [
        'Advanced settings',
        'Cloud sync',
        'Priority support'
    ]
]);

// =============================================================================
// Example 8: Multi-tenant Translations
// =============================================================================

class TenantTranslationService
{
    private TranslationManager $manager;
    
    public function setTenantBranding(int $tenantId, string $locale, array $branding)
    {
        $namespace = "tenant_{$tenantId}";
        
        foreach ($branding as $key => $value) {
            $this->manager->set(
                $locale,
                'branding',
                $key,
                $value,
                $namespace,
                'tenant_admin'
            );
        }
    }
}

$tenantService = new TenantTranslationService($manager);

// Set custom branding for tenant
$tenantService->setTenantBranding(42, 'en', [
    'app.name' => 'Client Custom Name',
    'app.tagline' => 'Their Custom Tagline',
    'footer.copyright' => '© 2025 Client Company',
]);

// =============================================================================
// Example 9: Get Merged Translations (File + Database)
// =============================================================================

// Get all translations with database overriding files
$allTranslations = $manager->getAllMerged('en', 'messages');

// This includes:
// - All translations from resources/lang/en/messages.json
// - Plus any database translations that override or add to it

print_r($allTranslations);

// =============================================================================
// Example 10: Find Missing Translations
// =============================================================================

// Find what's in files but not in database
$missing = $manager->findMissing('es', 'messages');

echo "Missing Spanish translations:\n";
foreach ($missing as $key) {
    echo "  - {$key}\n";
}

// =============================================================================
// Example 11: Search Translations
// =============================================================================

// Search for translations containing "welcome"
$results = $manager->search('welcome', 'en');

foreach ($results as $result) {
    echo "{$result['group']}.{$result['key']}: {$result['value']}\n";
}

// =============================================================================
// Example 12: Statistics and Monitoring
// =============================================================================

$stats = $manager->getStats();

echo "Total translations in database: {$stats['total']}\n";
echo "\nBy locale:\n";
foreach ($stats['by_locale'] as $locale => $count) {
    echo "  {$locale}: {$count}\n";
}

echo "\nBy group:\n";
foreach ($stats['by_group'] as $group => $count) {
    echo "  {$group}: {$count}\n";
}

echo "\nBy source:\n";
foreach ($stats['by_source'] as $source => $count) {
    echo "  {$source}: {$count}\n";
}

// =============================================================================
// Example 13: Batch Update (for translations)
// =============================================================================

// Update multiple translations at once
$updates = [
    ['locale' => 'en', 'group' => 'messages', 'key' => 'welcome', 'value' => 'Welcome!'],
    ['locale' => 'en', 'group' => 'messages', 'key' => 'goodbye', 'value' => 'Goodbye!'],
    ['locale' => 'es', 'group' => 'messages', 'key' => 'welcome', 'value' => '¡Bienvenido!'],
];

$count = $manager->batchUpdate($updates);
echo "Updated {$count} translations\n";

// =============================================================================
// Example 14: Admin Panel Integration
// =============================================================================

// In your admin controller
class AdminTranslationController
{
    private TranslationManager $manager;
    private Translator $translator;
    
    public function edit(string $locale, string $group)
    {
        // Get merged translations (file + database)
        $translations = $this->manager->getAllMerged($locale, $group);
        
        return view('admin.translations.edit', [
            'locale' => $locale,
            'group' => $group,
            'translations' => $translations,
        ]);
    }
    
    public function update(string $locale, string $group, array $data)
    {
        // Update in database
        foreach ($data as $key => $value) {
            $this->manager->set($locale, $group, $key, $value, source: 'admin');
        }
        
        // Optionally export to file
        if (isset($_POST['export_to_file'])) {
            $this->manager->exportToFile($locale, $group, 'json');
        }
        
        // Clear cache
        $cache->delete("i18n.{$locale}.{$group}");
        
        return redirect()->back()->with('success', 'Translations updated!');
    }
}

// =============================================================================
// Example 15: API Endpoint for Translation Management
// =============================================================================

class TranslationApiController
{
    private TranslationManager $manager;
    
    public function index(string $locale, string $group)
    {
        $translations = $this->manager->getAllMerged($locale, $group);
        
        return json([
            'locale' => $locale,
            'group' => $group,
            'translations' => $translations,
        ]);
    }
    
    public function update(string $locale, string $group, string $key)
    {
        $value = $_POST['value'] ?? '';
        
        $this->manager->set($locale, $group, $key, $value, source: 'api');
        
        return json([
            'success' => true,
            'message' => 'Translation updated',
        ]);
    }
    
    public function import()
    {
        $locale = $_POST['locale'];
        $group = $_POST['group'];
        $translations = json_decode($_POST['translations'], true);
        
        $count = $this->manager->importArray(
            $locale,
            $group,
            $translations,
            'api_import',
            true
        );
        
        return json([
            'success' => true,
            'imported' => $count,
        ]);
    }
}

// =============================================================================
// Example 16: Workflow - File Development → Database Production
// =============================================================================

// Development: Work with files
// resources/lang/en/messages.json contains base translations

// Pre-production: Import to database
$manager->sync('en', 'messages', 'file_to_db', overwrite: false);
$manager->sync('es', 'messages', 'file_to_db', overwrite: false);

// Production: Edit in admin panel (database)
// Translations are stored in database, can be edited via admin

// Post-production: Export for next deployment
$manager->exportToFile('en', 'messages', 'json');
$manager->exportToFile('es', 'messages', 'json');

// Commit files to git
// git add resources/lang/
// git commit -m "Updated translations from production"
