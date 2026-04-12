-- ============================================================================
-- MonkeysLegion I18n — SQLite Schema
-- ============================================================================
--
-- Usage:
--   sqlite3 database.sqlite < schema/sqlite.sql
--
-- Compatible with: SQLite 3.24+ (UPSERT support)
-- ============================================================================

CREATE TABLE IF NOT EXISTS translations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    locale TEXT NOT NULL,
    "group" TEXT NOT NULL,
    namespace TEXT NOT NULL DEFAULT '',
    "key" TEXT NOT NULL,
    value TEXT NOT NULL,
    source TEXT NOT NULL DEFAULT 'file',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),

    -- Unique constraint for UPSERT (ON CONFLICT)
    UNIQUE (locale, "group", namespace, "key")
);

-- Performance indexes
CREATE INDEX IF NOT EXISTS idx_translation_locale ON translations (locale);
CREATE INDEX IF NOT EXISTS idx_translation_group ON translations (locale, "group");
CREATE INDEX IF NOT EXISTS idx_translation_source ON translations (source);
