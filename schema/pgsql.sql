-- ============================================================================
-- MonkeysLegion I18n — PostgreSQL Schema
-- ============================================================================
--
-- Usage:
--   psql -U postgres -d your_database -f schema/pgsql.sql
--
-- Compatible with: PostgreSQL 12+
-- ============================================================================

CREATE TABLE IF NOT EXISTS translations (
    id BIGSERIAL PRIMARY KEY,
    locale VARCHAR(10) NOT NULL,
    "group" VARCHAR(50) NOT NULL,
    namespace VARCHAR(50) NOT NULL DEFAULT '',
    "key" VARCHAR(255) NOT NULL,
    value TEXT NOT NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'file',
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),

    -- Unique constraint for UPSERT (ON CONFLICT)
    UNIQUE (locale, "group", namespace, "key")
);

-- Performance indexes
CREATE INDEX IF NOT EXISTS idx_translation_locale ON translations (locale);
CREATE INDEX IF NOT EXISTS idx_translation_group ON translations (locale, "group");
CREATE INDEX IF NOT EXISTS idx_translation_source ON translations (source);

-- Auto-update updated_at trigger
CREATE OR REPLACE FUNCTION update_translations_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_translations_updated ON translations;
CREATE TRIGGER trg_translations_updated
    BEFORE UPDATE ON translations
    FOR EACH ROW
    EXECUTE FUNCTION update_translations_timestamp();
