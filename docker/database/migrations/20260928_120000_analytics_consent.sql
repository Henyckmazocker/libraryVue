-- Migration: 20260928_120000_analytics_consent
-- Description: Consentimiento de analítica (Augur) por usuario, en `users`.
--
--   analytics_consent     NULL = sin decidir (el frontend pregunta), 1 = sí, 0 = no.
--   analytics_consent_at  cuándo decidió por última vez (auditoría). NULL mientras no decida.
--
-- Columna propia y no `preferences` (JSON sin contrato) ni `user_privacy_settings` (es la
-- visibilidad social, y su `update_privacy_settings` reescribe los siete campos a la vez).
-- Plan «Consentimiento de Analítica», M1.
--
-- El backend nuevo NO escribe estas columnas en el `UPDATE users` genérico del login
-- (`UserDataMapper::toPersistence`): solo las toca `update_analytics_consent`. Así, entre
-- `prod-deploy.sh --rebuild` y `--migrate` el login sigue funcionando sin ellas.

-- 1. analytics_consent (IF NOT EXISTS vía INFORMATION_SCHEMA — MySQL 8.0 no tiene ADD COLUMN IF NOT EXISTS)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'analytics_consent'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN analytics_consent TINYINT(1) NULL DEFAULT NULL AFTER preferences',
  'SELECT ''analytics_consent column already exists'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. analytics_consent_at
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'analytics_consent_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN analytics_consent_at TIMESTAMP NULL DEFAULT NULL AFTER analytics_consent',
  'SELECT ''analytics_consent_at column already exists'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
