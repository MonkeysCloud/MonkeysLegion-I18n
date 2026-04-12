-- ============================================================================
-- MonkeysLegion I18n — MySQL / MariaDB Schema
-- ============================================================================
--
-- Usage:
--   mysql -u root -p your_database < schema/mysql.sql
--
-- Compatible with: MySQL 8.0+, MariaDB 10.5+
-- ============================================================================

CREATE TABLE IF NOT EXISTS translations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    locale VARCHAR(10) NOT NULL,
    `group` VARCHAR(50) NOT NULL,
    namespace VARCHAR(50) NOT NULL DEFAULT '',
    `key` VARCHAR(255) NOT NULL,
    value TEXT NOT NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'file',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Unique constraint for UPSERT operations
    UNIQUE INDEX idx_translation_unique (locale, `group`, namespace, `key`),

    -- Performance indexes
    INDEX idx_translation_locale (locale),
    INDEX idx_translation_group (locale, `group`),
    INDEX idx_translation_source (source),
    INDEX idx_translation_search (`key`(100), value(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
