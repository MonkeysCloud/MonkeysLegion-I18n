<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Loaders;

use MonkeysLegion\I18n\Contract\LoaderInterface;

use PDO;

/**
 * Loads translations from database with security hardening.
 *
 * Security:
 * - Table name validated against allowlist pattern
 * - All queries use parameterized statements
 */
final class DatabaseLoader implements LoaderInterface
{
    // ── Constants ─────────────────────────────────────────────────

    private const string TABLE_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/';

    // ── Properties ────────────────────────────────────────────────

    private readonly PDO $pdo;
    private readonly string $table;

    /** @var array<string, string> */
    private array $namespaces = [];

    // ── Constructor ───────────────────────────────────────────────

    public function __construct(PDO $pdo, string $table = 'translations')
    {
        if (!preg_match(self::TABLE_PATTERN, $table)) {
            throw new \InvalidArgumentException("Invalid table name: '{$table}'");
        }

        $this->pdo = $pdo;
        $this->table = $table;
    }

    // ── LoaderInterface ───────────────────────────────────────────

    public function load(string $locale, string $group, ?string $namespace = null): array
    {
        $sql = "SELECT `key`, `value` FROM {$this->table} WHERE locale = :locale AND `group` = :group";

        $params = [
            'locale' => $locale,
            'group'  => $group,
        ];

        if ($namespace !== null) {
            $sql .= ' AND namespace = :namespace';
            $params['namespace'] = $namespace;
        } else {
            $sql .= ' AND namespace IS NULL';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $messages = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (is_array($row) && isset($row['key'], $row['value'])) {
                $this->setNestedValue($messages, (string) $row['key'], (string) $row['value']);
            }
        }

        return $messages;
    }

    public function addNamespace(string $namespace, string $path): void
    {
        $this->namespaces[$namespace] = $path;
    }

    // ── Private methods ───────────────────────────────────────────

    /**
     * Set nested array value using dot notation.
     *
     * @param array<string, mixed> $array
     */
    private function setNestedValue(array &$array, string $key, string $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current = $value;
    }
}
