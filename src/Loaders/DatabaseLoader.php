<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Loaders;

use MonkeysLegion\I18n\Contracts\LoaderInterface;
use PDO;

/**
 * Loads translations from database
 * 
 * Expected table structure:
 * - locale (varchar)
 * - group (varchar)
 * - namespace (varchar, nullable)
 * - key (varchar)
 * - value (text)
 */
final class DatabaseLoader implements LoaderInterface
{
    private PDO $pdo;
    private string $table;
    
    /** @var array<string, string> */
    private array $namespaces = [];

    public function __construct(PDO $pdo, string $table = 'translations')
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    /**
     * {@inheritdoc}
     */
    public function load(string $locale, string $group, ?string $namespace = null): array
    {
        $sql = "SELECT `key`, `value` FROM {$this->table} 
                WHERE locale = :locale 
                AND `group` = :group";
        
        $params = [
            'locale' => $locale,
            'group' => $group,
        ];
        
        if ($namespace !== null) {
            $sql .= " AND namespace = :namespace";
            $params['namespace'] = $namespace;
        } else {
            $sql .= " AND namespace IS NULL";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $messages = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->setNestedValue($messages, $row['key'], $row['value']);
        }
        
        return $messages;
    }

    /**
     * {@inheritdoc}
     */
    public function addNamespace(string $namespace, string $path): void
    {
        // Not used in database loader, but required by interface
        $this->namespaces[$namespace] = $path;
    }

    /**
     * Set nested array value using dot notation
     * 
     * @param array<string, mixed> $array
     */
    private function setNestedValue(array &$array, string $key, string $value): void
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
