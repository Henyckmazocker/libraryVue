-- Migration: 20260907_120000_journal_privacy
-- Author: David
-- Description: show_journal — el séptimo interruptor de user_privacy_settings

-- Los seis interruptores que había hasta hoy deciden qué EVENTOS del feed se
-- publican: cada uno se corresponde con un `event_type` de `feed_events`. Este
-- no. `show_journal` decide si `journal_entry` se puede LEER desde el perfil
-- público, y por eso no entra en `PrivacySettings::EVENT_TYPE_MAP` ni añade
-- nada al ENUM de `feed_events.event_type`.
--
-- Nace APAGADO, y es el segundo del proyecto que lo hace —el otro es
-- `show_notes`—: el diario dice qué hiciste y qué día, que es lo más íntimo que
-- guarda la app. Que se encienda a mano.
--
-- Bloque idempotente en vez de un ALTER a pelo porque MySQL 8.0 no admite
-- `ADD COLUMN IF NOT EXISTS` (docker/database/migrations/README.md:22-24).
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_privacy_settings' AND COLUMN_NAME = 'show_journal'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE user_privacy_settings ADD COLUMN show_journal TINYINT(1) NOT NULL DEFAULT 0 AFTER show_achievements',
  'SELECT ''show_journal column already exists'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
