<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n;

use MonkeysLegion\I18n\Loaders\FileLoader;
use MonkeysLegion\I18n\Loaders\CacheLoader;
use MonkeysLegion\I18n\Loaders\DatabaseLoader;
use MonkeysLegion\I18n\Detectors\UrlDetector;
use MonkeysLegion\I18n\Detectors\SessionDetector;
use MonkeysLegion\I18n\Detectors\CookieDetector;
use MonkeysLegion\I18n\Detectors\HeaderDetector;
use MonkeysLegion\I18n\Detectors\QueryDetector;
use Psr\SimpleCache\CacheInterface;
use PDO;

/**
 * Factory for creating configured Translator instances
 */
final class TranslatorFactory
{
    /**
     * Create a translator with common configuration
     * 
     * @param array{
     *   locale?: string,
     *   fallback?: string,
     *   path?: string,
     *   cache?: CacheInterface|null,
     *   cache_ttl?: int,
     *   pdo?: PDO|null,
     *   supported_locales?: array<string>,
     *   detectors?: array<string>,
     *   namespaces?: array<string, string>,
     *   track_missing?: bool
     * } $config
     */
    public static function create(array $config = []): Translator
    {
        // Extract configuration
        $locale = $config['locale'] ?? 'en';
        $fallback = $config['fallback'] ?? 'en';
        $path = $config['path'] ?? 'resources/lang';
        $cache = $config['cache'] ?? null;
        $cacheTtl = $config['cache_ttl'] ?? 3600;
        $pdo = $config['pdo'] ?? null;
        $namespaces = $config['namespaces'] ?? [];
        $trackMissing = $config['track_missing'] ?? false;
        
        // Create translator
        $translator = new Translator($locale, $fallback);
        
        // Add file loader
        $fileLoader = new FileLoader($path);
        
        // Add namespaces to file loader
        foreach ($namespaces as $namespace => $namespacePath) {
            $fileLoader->addNamespace($namespace, $namespacePath);
            $translator->addNamespace($namespace, $namespacePath);
        }
        
        // Wrap with cache if available
        if ($cache instanceof CacheInterface) {
            $fileLoader = new CacheLoader($fileLoader, $cache, $cacheTtl);
        }
        
        $translator->addLoader($fileLoader);
        
        // Add database loader if PDO provided
        if ($pdo instanceof PDO) {
            $dbLoader = new DatabaseLoader($pdo);
            $translator->addLoader($dbLoader);
        }
        
        // Enable missing translation tracking
        if ($trackMissing) {
            $translator->setTrackMissing(true);
        }
        
        return $translator;
    }

    /**
     * Create a locale manager with detectors
     * 
     * @param array{
     *   default?: string,
     *   fallback?: string,
     *   supported?: array<string>,
     *   detectors?: array<string>
     * } $config
     */
    public static function createLocaleManager(array $config = []): LocaleManager
    {
        $default = $config['default'] ?? 'en';
        $fallback = $config['fallback'] ?? 'en';
        $supported = $config['supported'] ?? ['en'];
        $detectors = $config['detectors'] ?? ['url', 'session', 'cookie', 'header'];
        
        $manager = new LocaleManager($default, $supported, $fallback);
        
        // Add detectors in priority order
        foreach ($detectors as $detector) {
            match($detector) {
                'url' => $manager->addDetector(new UrlDetector()),
                'session' => $manager->addDetector(new SessionDetector()),
                'cookie' => $manager->addDetector(new CookieDetector()),
                'header' => $manager->addDetector(new HeaderDetector()),
                'query' => $manager->addDetector(new QueryDetector()),
                default => null
            };
        }
        
        return $manager;
    }

    /**
     * Create a complete I18n system with translator and locale manager
     * 
     * @return array{translator: Translator, manager: LocaleManager}
     */
    public static function createSystem(array $config = []): array
    {
        $manager = self::createLocaleManager($config);
        $locale = $manager->detectLocale();
        
        $config['locale'] = $locale;
        $translator = self::create($config);
        
        return [
            'translator' => $translator,
            'manager' => $manager,
        ];
    }
}
