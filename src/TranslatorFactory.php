<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n;

use MonkeysLegion\I18n\Loaders\FileLoader;
use MonkeysLegion\I18n\Loaders\CacheLoader;
use MonkeysLegion\I18n\Loaders\CompiledLoader;
use MonkeysLegion\I18n\Loaders\DatabaseLoader;
use MonkeysLegion\I18n\Detectors\UrlDetector;
use MonkeysLegion\I18n\Detectors\SessionDetector;
use MonkeysLegion\I18n\Detectors\CookieDetector;
use MonkeysLegion\I18n\Detectors\HeaderDetector;
use MonkeysLegion\I18n\Detectors\QueryDetector;

use Psr\SimpleCache\CacheInterface;

use PDO;

/**
 * Factory for creating configured Translator instances.
 */
final class TranslatorFactory
{
    /**
     * Create a translator with common configuration.
     *
     * @param array{
     *   locale?: string,
     *   fallback?: string,
     *   path?: string,
     *   cache?: CacheInterface|null,
     *   cache_ttl?: int,
     *   pdo?: PDO|null,
     *   supported_locales?: list<string>,
     *   detectors?: list<string>,
     *   namespaces?: array<string, string>,
     *   track_missing?: bool,
     *   compiled_path?: string|null
     * } $config
     */
    public static function create(array $config = []): Translator
    {
        $locale        = $config['locale'] ?? 'en';
        $fallback      = $config['fallback'] ?? 'en';
        $path          = $config['path'] ?? 'resources/lang';
        $cache         = $config['cache'] ?? null;
        $cacheTtl      = $config['cache_ttl'] ?? 3600;
        $pdo           = $config['pdo'] ?? null;
        $namespaces    = $config['namespaces'] ?? [];
        $trackMissing  = $config['track_missing'] ?? false;
        $compiledPath  = $config['compiled_path'] ?? null;

        // Create translator
        $translator = new Translator($locale, $fallback);

        // Add file loader
        $fileLoader = new FileLoader($path);

        // Add namespaces to file loader
        foreach ($namespaces as $namespace => $namespacePath) {
            $fileLoader->addNamespace($namespace, $namespacePath);
            $translator->addNamespace($namespace, $namespacePath);
        }

        // Use compiled loader for production
        if ($compiledPath !== null) {
            $compiledLoader = new CompiledLoader($fileLoader, $compiledPath);
            $translator->addLoader($compiledLoader);
        } elseif ($cache instanceof CacheInterface) {
            // Wrap with cache
            $cachedFileLoader = new CacheLoader($fileLoader, $cache, $cacheTtl);
            $translator->addLoader($cachedFileLoader);
        } else {
            $translator->addLoader($fileLoader);
        }

        // Add database loader if PDO provided
        if ($pdo instanceof PDO) {
            $dbLoader = new DatabaseLoader($pdo);

            if ($cache instanceof CacheInterface) {
                $dbLoader = new CacheLoader($dbLoader, $cache, $cacheTtl);
            }

            $translator->addLoader($dbLoader);
        }

        // Enable missing translation tracking
        if ($trackMissing) {
            $translator->setTrackMissing(true);
        }

        return $translator;
    }

    /**
     * Create a locale manager with detectors.
     *
     * @param array{
     *   default?: string,
     *   fallback?: string,
     *   supported?: list<string>,
     *   detectors?: list<string>
     * } $config
     */
    public static function createLocaleManager(array $config = []): LocaleManager
    {
        $default   = $config['default'] ?? 'en';
        $fallback  = $config['fallback'] ?? 'en';
        $supported = $config['supported'] ?? ['en'];
        $detectors = $config['detectors'] ?? ['url', 'session', 'cookie', 'header'];

        $manager = new LocaleManager($default, $supported, $fallback);

        foreach ($detectors as $detector) {
            match ($detector) {
                'url'       => $manager->addDetector(new UrlDetector()),
                'session'   => $manager->addDetector(new SessionDetector()),
                'cookie'    => $manager->addDetector(new CookieDetector()),
                'header'    => $manager->addDetector(new HeaderDetector()),
                'query'     => $manager->addDetector(new QueryDetector()),
                default     => null,
            };
        }

        return $manager;
    }

    /**
     * Create a complete I18n system with translator and locale manager.
     *
     * @param array<string, mixed> $config
     *
     * @return array{translator: Translator, manager: LocaleManager}
     */
    public static function createSystem(array $config = []): array
    {
        /** @var array{default?: string, fallback?: string, supported?: list<string>, detectors?: list<string>} $localeConfig */
        $localeConfig = $config;
        $manager = self::createLocaleManager($localeConfig);
        $locale = $manager->detectLocale();

        $config['locale'] = $locale;
        /** @var array{locale?: string, fallback?: string, path?: string, cache?: CacheInterface|null, cache_ttl?: int, pdo?: PDO|null, supported_locales?: list<string>, namespaces?: array<string, string>, track_missing?: bool, compiled_path?: string|null} $translatorConfig */
        $translatorConfig = $config;
        $translator = self::create($translatorConfig);

        return [
            'translator' => $translator,
            'manager'    => $manager,
        ];
    }

    /**
     * Create number formatter.
     */
    public static function createNumberFormatter(): NumberFormatter
    {
        return new NumberFormatter();
    }

    /**
     * Create date formatter.
     */
    public static function createDateFormatter(): DateFormatter
    {
        return new DateFormatter();
    }
}
