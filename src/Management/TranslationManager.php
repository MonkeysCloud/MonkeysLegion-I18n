<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Management;

use PDO;
use MonkeysLegion\I18n\Translator;

/**
 * Translation Manager for managing both file and database translations
 * 
 * Makes it easy to:
 * - Add translations to database
 * - Export database translations to files
 * - Import file translations to database
 * - Sync between sources
 */
final class TranslationManager
{
    private PDO $pdo;
    private Translator $translator;
    private string $filePath;
    private string $tableName;

    public function __construct(
        PDO $pdo,
        Translator $translator,
        string $filePath,
        string $tableName = 'translations'
    ) {
        $this->pdo = $pdo;
        $this->translator = $translator;
        $this->filePath = rtrim($filePath, DIRECTORY_SEPARATOR);
        $this->tableName = $tableName;
    }

    /**
     * Add or update a translation in database
     */
    public function set(
        string $locale,
        string $group,
        string $key,
        string $value,
        ?string $namespace = null,
        string $source = 'admin'
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->tableName} (locale, `group`, namespace, `key`, value, source)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                value = VALUES(value),
                source = VALUES(source),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([$locale, $group, $namespace, $key, $value, $source]);
    }

    /**
     * Get a translation from database
     */
    public function get(
        string $locale,
        string $group,
        string $key,
        ?string $namespace = null
    ): ?string {
        $sql = "SELECT value FROM {$this->tableName} 
                WHERE locale = ? AND `group` = ? AND `key` = ?";
        
        $params = [$locale, $group, $key];
        
        if ($namespace !== null) {
            $sql .= " AND namespace = ?";
            $params[] = $namespace;
        } else {
            $sql .= " AND namespace IS NULL";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['value'] : null;
    }

    /**
     * Delete a translation from database
     */
    public function delete(
        string $locale,
        string $group,
        string $key,
        ?string $namespace = null
    ): bool {
        $sql = "DELETE FROM {$this->tableName} 
                WHERE locale = ? AND `group` = ? AND `key` = ?";
        
        $params = [$locale, $group, $key];
        
        if ($namespace !== null) {
            $sql .= " AND namespace = ?";
            $params[] = $namespace;
        } else {
            $sql .= " AND namespace IS NULL";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Get all translations for a locale/group from database
     * 
     * @return array<string, string>
     */
    public function getGroup(
        string $locale,
        string $group,
        ?string $namespace = null
    ): array {
        $sql = "SELECT `key`, value FROM {$this->tableName} 
                WHERE locale = ? AND `group` = ?";
        
        $params = [$locale, $group];
        
        if ($namespace !== null) {
            $sql .= " AND namespace = ?";
            $params[] = $namespace;
        } else {
            $sql .= " AND namespace IS NULL";
        }
        
        $sql .= " ORDER BY `key`";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $translations = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->setNestedValue($translations, $row['key'], $row['value']);
        }
        
        return $translations;
    }

    /**
     * Import translations from file to database
     */
    public function importFromFile(
        string $locale,
        string $group,
        bool $overwrite = false
    ): int {
        // Try JSON first
        $jsonFile = $this->filePath . "/{$locale}/{$group}.json";
        if (file_exists($jsonFile)) {
            $data = json_decode(file_get_contents($jsonFile), true);
            return $this->importArray($locale, $group, $data, 'file_import', $overwrite);
        }
        
        // Try PHP
        $phpFile = $this->filePath . "/{$locale}/{$group}.php";
        if (file_exists($phpFile)) {
            $data = require $phpFile;
            return $this->importArray($locale, $group, $data, 'file_import', $overwrite);
        }
        
        return 0;
    }

    /**
     * Import array of translations to database
     * 
     * @param array<string, mixed> $translations
     */
    public function importArray(
        string $locale,
        string $group,
        array $translations,
        string $source = 'import',
        bool $overwrite = false,
        ?string $namespace = null
    ): int {
        $flat = $this->flattenArray($translations);
        $count = 0;
        
        $this->pdo->beginTransaction();
        
        try {
            if ($overwrite) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO {$this->tableName} (locale, `group`, namespace, `key`, value, source)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        value = VALUES(value),
                        source = VALUES(source),
                        updated_at = CURRENT_TIMESTAMP
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT IGNORE INTO {$this->tableName} (locale, `group`, namespace, `key`, value, source)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
            }
            
            foreach ($flat as $key => $value) {
                $stmt->execute([$locale, $group, $namespace, $key, $value, $source]);
                if ($stmt->rowCount() > 0) {
                    $count++;
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
     * Export database translations to file
     */
    public function exportToFile(
        string $locale,
        string $group,
        string $format = 'json',
        ?string $namespace = null
    ): string {
        $translations = $this->getGroup($locale, $group, $namespace);
        
        $directory = $this->filePath . "/{$locale}";
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $filename = $directory . "/{$group}." . $format;
        
        if ($format === 'json') {
            file_put_contents(
                $filename,
                json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        } elseif ($format === 'php') {
            file_put_contents(
                $filename,
                "<?php\n\nreturn " . var_export($translations, true) . ";\n"
            );
        }
        
        return $filename;
    }

    /**
     * Sync translations between file and database
     * 
     * @param string $direction 'file_to_db' or 'db_to_file'
     */
    public function sync(
        string $locale,
        string $group,
        string $direction = 'file_to_db',
        bool $overwrite = false
    ): int {
        if ($direction === 'file_to_db') {
            return $this->importFromFile($locale, $group, $overwrite);
        } else {
            $this->exportToFile($locale, $group, 'json');
            return count($this->getGroup($locale, $group));
        }
    }

    /**
     * Get all translations (both file and database)
     * Database translations override file translations
     * 
     * @return array<string, mixed>
     */
    public function getAllMerged(string $locale, string $group): array
    {
        // Get from file
        $fileTranslations = [];
        
        $jsonFile = $this->filePath . "/{$locale}/{$group}.json";
        if (file_exists($jsonFile)) {
            $fileTranslations = json_decode(file_get_contents($jsonFile), true);
        } else {
            $phpFile = $this->filePath . "/{$locale}/{$group}.php";
            if (file_exists($phpFile)) {
                $fileTranslations = require $phpFile;
            }
        }
        
        // Get from database
        $dbTranslations = $this->getGroup($locale, $group);
        
        // Merge (database wins)
        return array_merge($fileTranslations, $dbTranslations);
    }

    /**
     * Find missing translations in database compared to files
     * 
     * @return array<string>
     */
    public function findMissing(string $locale, string $group): array
    {
        $fileTranslations = [];
        
        $jsonFile = $this->filePath . "/{$locale}/{$group}.json";
        if (file_exists($jsonFile)) {
            $fileTranslations = json_decode(file_get_contents($jsonFile), true);
        }
        
        $dbTranslations = $this->getGroup($locale, $group);
        
        $fileKeys = array_keys($this->flattenArray($fileTranslations));
        $dbKeys = array_keys($this->flattenArray($dbTranslations));
        
        return array_diff($fileKeys, $dbKeys);
    }

    /**
     * Get statistics
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
        $total = $this->pdo->query("
            SELECT COUNT(*) as count FROM {$this->tableName}
        ")->fetch(PDO::FETCH_ASSOC)['count'];
        
        $byLocale = [];
        $stmt = $this->pdo->query("
            SELECT locale, COUNT(*) as count 
            FROM {$this->tableName} 
            GROUP BY locale
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $byLocale[$row['locale']] = (int)$row['count'];
        }
        
        $byGroup = [];
        $stmt = $this->pdo->query("
            SELECT `group`, COUNT(*) as count 
            FROM {$this->tableName} 
            GROUP BY `group`
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $byGroup[$row['group']] = (int)$row['count'];
        }
        
        $bySource = [];
        $stmt = $this->pdo->query("
            SELECT source, COUNT(*) as count 
            FROM {$this->tableName} 
            GROUP BY source
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $bySource[$row['source']] = (int)$row['count'];
        }
        
        return [
            'total' => (int)$total,
            'by_locale' => $byLocale,
            'by_group' => $byGroup,
            'by_source' => $bySource,
        ];
    }

    /**
     * Search translations
     * 
     * @return array<array{locale: string, group: string, key: string, value: string}>
     */
    public function search(string $query, ?string $locale = null): array
    {
        $sql = "SELECT locale, `group`, `key`, value 
                FROM {$this->tableName} 
                WHERE (`key` LIKE ? OR value LIKE ?)";
        
        $params = ["%{$query}%", "%{$query}%"];
        
        if ($locale !== null) {
            $sql .= " AND locale = ?";
            $params[] = $locale;
        }
        
        $sql .= " ORDER BY locale, `group`, `key` LIMIT 100";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Batch update translations
     * 
     * @param array<array{locale: string, group: string, key: string, value: string}> $translations
     */
    public function batchUpdate(array $translations): int
    {
        $count = 0;
        
        $this->pdo->beginTransaction();
        
        try {
            $stmt = $this->pdo->prepare("
                UPDATE {$this->tableName}
                SET value = ?, updated_at = CURRENT_TIMESTAMP
                WHERE locale = ? AND `group` = ? AND `key` = ?
            ");
            
            foreach ($translations as $trans) {
                $stmt->execute([
                    $trans['value'],
                    $trans['locale'],
                    $trans['group'],
                    $trans['key']
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $count++;
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
     * Flatten nested array to dot notation
     * 
     * @param array<string, mixed> $array
     * @return array<string, string>
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;
            
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = (string)$value;
            }
        }
        
        return $result;
    }

    /**
     * Set nested value using dot notation
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
