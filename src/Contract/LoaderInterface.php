<?php

declare(strict_types=1);

/**
 * MonkeysLegion I18n
 *
 * @package   MonkeysLegion\I18n
 * @author    MonkeysCloud <jorge@monkeys.cloud>
 * @license   MIT
 *
 * @requires  PHP 8.4
 */

namespace MonkeysLegion\I18n\Contract;

/**
 * Interface for translation loaders.
 */
interface LoaderInterface
{
    /**
     * Load translations for a given locale and group.
     *
     * @param string      $locale    Locale code (e.g., 'en', 'es')
     * @param string      $group     Translation group (e.g., 'messages', 'validation')
     * @param string|null $namespace Optional namespace (e.g., 'monkeysmail')
     *
     * @return array<string, mixed> Translation messages
     */
    public function load(string $locale, string $group, ?string $namespace = null): array;

    /**
     * Add a namespace path.
     *
     * @param string $namespace Namespace identifier
     * @param string $path      Path to translation files
     */
    public function addNamespace(string $namespace, string $path): void;
}
