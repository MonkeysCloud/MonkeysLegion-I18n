<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Management;

use PDO;
use MonkeysLegion\I18n\Translator;

/**
 * Translation Manager for managing both file and database translations.
 *
 * Makes it easy to:
 * - Add translations to database
 * - Export database translations to files
 * - Import file translations to database
 * - Sync between sources
 *
 * Supports: MySQL, MariaDB, PostgreSQL, SQLite.
 *
 * Security:
 * - Table name validated against regex pattern
 * - All queries use parameterized statements
 * - Transactions for batch operations
 */
final class TranslationManager
{
    // ── Constants ─────────────────────────────────────────────────

    private const string TABLE_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/';

    // ── Properties ────────────────────────────────────────────────

    private readonly PDO $pdo;
    private readonly Translator $translator;
    private readonly string $filePath;
    private readonly string $tableName;
    private readonly string $driver;

    /** @var array<string, string> Column quoting per driver */
    private readonly array $q;

    // ── Constructor ───────────────────────────────────────────────

    public function __construct(
        PDO $pdo,
        Translator $translator,
        string $filePath,
        string $tableName = 'translations',
    ) {
        if (!preg_match(self::TABLE_PATTERN, $tableName)) {
            throw new \InvalidArgumentException("Invalid table name: '{$tableName}'");
        }

        $this->pdo = $pdo;
        $this->translator = $translator;
        $this->filePath = rtrim($filePath, DIRECTORY_SEPARATOR);
        $this->tableName = $tableName;

        // Detect driver for cross-database compatibility
        $this->driver = strtolower((string) ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?? 'mysql'));

        // Column quoting differs per driver
        $this->q = match ($this->driver) {
            'pgsql' => ['q' => '"', 'group' => '"group"', 'key' => '"key"'],
            'sqlite' => ['q' => '"', 'group' => '"group"', 'key' => '"key"'],
            default => ['q' => '`', 'group' => '`group`', 'key' => '`key`'],
        };
    }

    // ── CRUD operations ──────────────────────────────────────────

    /**
     * Add or update a translation in database.
     *
     * Uses UPSERT syntax compatible with MySQL, PostgreSQL, and SQLite.
     */
    public function set(
        string $locale,
        string $group,
        string $key,
        string $value,
        ?string $namespace = null,
        string $source = 'admin',
    ): void {
        $g = $this->q['group'];
        $k = $this->q['key'];

        $sql = match ($this->driver) {
            'pgsql' => "
                INSERT INTO {$this->tableName} (locale, {$g}, namespace, {$k}, value, source)
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT (locale, {$g}, namespace, {$k})
                DO UPDATE SET value = EXCLUDED.value,
                              source = EXCLUDED.source,
                              updated_at = NOW()
            ",
            'sqlite' => "
                INSERT INTO {$this->tableName} (locale, {$g}, namespace, {$k}, value, source)
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT (locale, {$g}, namespace, {$k})
                DO UPDATE SET value = excluded.value,
                              source = excluded.source,
                              updated_at = datetime('now')
            ",
            default => "
                INSERT INTO {$this->tableName} (locale, {$g}, namespace, {$k}, value, source)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    value = VALUES(value),
                    source = VALUES(source),
                    updated_at = CURRENT_TIMESTAMP
            ",
        };

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$locale, $group, $namespace ?? '', $key, $value, $source]);
    }

    /**
     * Get a translation from database.
     */
    public function get(
        string $locale,
        string $group,
        string $key,
        ?string $namespace = null,
    ): ?string {
        $g = $this->q['group'];
        $k = $this->q['key'];

        $sql = "SELECT value FROM {$this->tableName}
                WHERE locale = ? AND {$g} = ? AND {$k} = ?";

        $params = [$locale, $group, $key];

        if ($namespace !== null) {
            $sql .= " AND namespace = ?";
            $params[] = $namespace;
        } else {
            $sql .= " AND (namespace IS NULL OR namespace = '')";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($result) || !array_key_exists('value', $result) || !is_string($result['value'])) {
            return null;
        }

        return $result['value'];
    }

    /**
     * Delete a translation from database.
     */
    public function delete(
        string $locale,
        string $group,
        string $key,
        ?string $namespace = null,
    ): bool {
        $g = $this->q['group'];
        $k = $this->q['key'];

        $sql = "DELETE FROM {$this->tableName}
                WHERE locale = ? AND {$g} = ? AND {$k} = ?";

        $params = [$locale, $group, $key];

        if ($namespace !== null) {
            $sql .= " AND namespace = ?";
            $params[] = $namespace;
        } else {
            $sql .= " AND (namespace IS NULL OR namespace = '')";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    // ── Group operations ─────────────────────────────────────────

    /**
     * Get all translations for a locale/group from database.
     *
     * @return array<string, mixed>
     */
    public function getGroup(
        string $locale,
        string $group,
        ?string $namespace = null,
    ): array {
        $g = $this->q['group'];
        $k = $this->q['key'];

        $sql = "SELECT {$k}, value FROM {$this->tableName}
                WHERE locale = ? AND {$g} = ?";

        $params = [$locale, $group];

        if ($namespace !== null) {
            $sql .= " AND namespace = ?";
            $params[] = $namespace;
        } else {
            $sql .= " AND (namespace IS NULL OR namespace = '')";
        }

        $sql .= " ORDER BY {$k}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $translations = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($row['key'])) {
                continue;
            }

            $value = $row['value'] ?? '';
            $this->setNestedValue($translations, (string) $row['key'], (string) $value);
        }

        return $translations;
    }

    // ── Import / Export ──────────────────────────────────────────

    /**
     * Import translations from file to database.
     */
    public function importFromFile(
        string $locale,
        string $group,
        bool $overwrite = false,
    ): int {
        // Try JSON first
        $jsonFile = $this->filePath . "/{$locale}/{$group}.json";

        if (file_exists($jsonFile)) {
            $contents = file_get_contents($jsonFile);

            if ($contents === false) {
                return 0;
            }

            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                return 0;
            }

            return $this->importArray($locale, $group, $data, 'file_import', $overwrite);
        }

        // Try PHP
        $phpFile = $this->filePath . "/{$locale}/{$group}.php";

        if (file_exists($phpFile)) {
            $data = require $phpFile;

            if (!is_array($data)) {
                return 0;
            }

            return $this->importArray($locale, $group, $data, 'file_import', $overwrite);
        }

        // Try MLC
        $mlcFile = $this->filePath . "/{$locale}/{$group}.mlc";

        if (file_exists($mlcFile)) {
            $loader = new \MonkeysLegion\I18n\Loaders\MlcLoader($this->filePath);
            $data = $loader->load($locale, $group);

            return $this->importArray($locale, $group, $data, 'file_import', $overwrite);
        }

        return 0;
    }

    /**
     * Import array of translations to database.
     *
     * @param array<string, mixed> $translations
     */
    public function importArray(
        string $locale,
        string $group,
        array $translations,
        string $source = 'import',
        bool $overwrite = false,
        ?string $namespace = null,
    ): int {
        $flat = $this->flattenArray($translations);
        $count = 0;

        $this->pdo->beginTransaction();

        try {
            foreach ($flat as $key => $value) {
                if ($overwrite) {
                    $this->set($locale, $group, $key, $value, $namespace, $source);
                    $count++;
                } else {
                    // Check if exists first
                    $existing = $this->get($locale, $group, $key, $namespace);

                    if ($existing === null) {
                        $this->set($locale, $group, $key, $value, $namespace, $source);
                        $count++;
                    }
                }
            }

            $this->pdo->commit();

            return $count;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Export database translations to file.
     */
    public function exportToFile(
        string $locale,
        string $group,
        string $format = 'json',
        ?string $namespace = null,
    ): string {
        $translations = $this->getGroup($locale, $group, $namespace);

        $directory = $this->filePath . "/{$locale}";

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = $directory . "/{$group}.{$format}";

        if ($format === 'json') {
            file_put_contents(
                $filename,
                json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            );
        } elseif ($format === 'php') {
            file_put_contents(
                $filename,
                "<?php\n\nreturn " . var_export($translations, true) . ";\n",
            );
        }

        return $filename;
    }

    // ── Sync ─────────────────────────────────────────────────────

    /**
     * Sync translations between file and database.
     *
     * @param string $direction 'file_to_db' or 'db_to_file'
     */
    public function sync(
        string $locale,
        string $group,
        string $direction = 'file_to_db',
        bool $overwrite = false,
    ): int {
        if ($direction === 'file_to_db') {
            return $this->importFromFile($locale, $group, $overwrite);
        } else {
            $this->exportToFile($locale, $group, 'json');

            return count($this->getGroup($locale, $group));
        }
    }

    // ── Query operations ─────────────────────────────────────────

    /**
     * Get all translations (both file and database).
     * Database translations override file translations.
     *
     * @return array<string, mixed>
     */
    public function getAllMerged(string $locale, string $group): array
    {
        // Get from file
        $fileTranslations = [];

        $jsonFile = $this->filePath . "/{$locale}/{$group}.json";

        if (file_exists($jsonFile)) {
            $contents = file_get_contents($jsonFile);

            if ($contents !== false) {
                $decoded = json_decode($contents, true);
                $fileTranslations = is_array($decoded) ? $decoded : [];
            }
        } else {
            $phpFile = $this->filePath . "/{$locale}/{$group}.php";

            if (file_exists($phpFile)) {
                $data = require $phpFile;
                $fileTranslations = is_array($data) ? $data : [];
            }
        }

        // Get from database
        $dbTranslations = $this->getGroup($locale, $group);

        // Merge (database wins over files for same keys, preserving nested structure)
        return array_replace_recursive($fileTranslations, $dbTranslations);
    }

    /**
     * Find missing translations in database compared to files.
     *
     * @return list<string>
     */
    public function findMissing(string $locale, string $group): array
    {
        $fileTranslations = [];

        $jsonFile = $this->filePath . "/{$locale}/{$group}.json";

        if (file_exists($jsonFile)) {
            $contents = file_get_contents($jsonFile);

            if ($contents !== false) {
                $decoded = json_decode($contents, true);
                $fileTranslations = is_array($decoded) ? $decoded : [];
            }
        }

        $dbTranslations = $this->getGroup($locale, $group);

        $fileKeys = array_keys($this->flattenArray($fileTranslations));
        $dbKeys = array_keys($this->flattenArray($dbTranslations));

        return array_values(array_diff($fileKeys, $dbKeys));
    }

    /**
     * Get statistics.
     *
     * @return array{
     *   total: int,
     *   by_locale: array<string, int>,
     *   by_group: array<string, int>,
     *   by_source: array<string, int>
     * }
     */
    public function getStats(): array
    {
        $g = $this->q['group'];

        $total = (int) $this->pdo->query(
            "SELECT COUNT(*) as cnt FROM {$this->tableName}",
        )->fetch(PDO::FETCH_ASSOC)['cnt'];

        $byLocale = [];
        $stmt = $this->pdo->query(
            "SELECT locale, COUNT(*) as cnt FROM {$this->tableName} GROUP BY locale",
        );

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (is_array($row) && isset($row['locale']) && is_string($row['locale'])) {
                $byLocale[$row['locale']] = (int) $row['cnt'];
            }
        }

        $byGroup = [];
        $stmt = $this->pdo->query(
            "SELECT {$g}, COUNT(*) as cnt FROM {$this->tableName} GROUP BY {$g}",
        );

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (is_array($row) && isset($row['group']) && is_string($row['group'])) {
                $byGroup[$row['group']] = (int) $row['cnt'];
            }
        }

        $bySource = [];
        $stmt = $this->pdo->query(
            "SELECT source, COUNT(*) as cnt FROM {$this->tableName} GROUP BY source",
        );

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (is_array($row) && isset($row['source']) && is_string($row['source'])) {
                $bySource[$row['source']] = (int) $row['cnt'];
            }
        }

        return [
            'total'     => $total,
            'by_locale' => $byLocale,
            'by_group'  => $byGroup,
            'by_source' => $bySource,
        ];
    }

    /**
     * Search translations.
     *
     * @return array<array{locale: string, group: string, key: string, value: string}>
     */
    public function search(string $query, ?string $locale = null): array
    {
        $k = $this->q['key'];
        $g = $this->q['group'];

        $sql = "SELECT locale, {$g}, {$k}, value
                FROM {$this->tableName}
                WHERE ({$k} LIKE ? OR value LIKE ?)";

        $params = ["%{$query}%", "%{$query}%"];

        if ($locale !== null) {
            $sql .= " AND locale = ?";
            $params[] = $locale;
        }

        $sql .= " ORDER BY locale, {$g}, {$k} LIMIT 100";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Batch update translations.
     *
     * @param array<array{locale: string, group: string, key: string, value: string, namespace?: string|null, source?: string}> $translations
     */
    public function batchUpdate(array $translations): int
    {
        $count = 0;

        $this->pdo->beginTransaction();

        try {
            foreach ($translations as $trans) {
                $this->set(
                    $trans['locale'],
                    $trans['group'],
                    $trans['key'],
                    $trans['value'],
                    $trans['namespace'] ?? null,
                    $trans['source'] ?? 'batch',
                );
                $count++;
            }

            $this->pdo->commit();

            return $count;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ── Private methods ──────────────────────────────────────────

    /**
     * Flatten nested array to dot notation.
     *
     * @param array<string, mixed> $array
     * @return array<string, string>
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $key = (string) $key;
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * Set nested value using dot notation.
     *
     * @param array<string, mixed> $array
     */
    private function setNestedValue(array &$array, string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current = $value;
    }
}
