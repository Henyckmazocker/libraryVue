-- Migration: 20260910_120000_drop_consumption_dates
-- Author: David
-- Description: las seis columnas de fecha de consumo, vacías desde siempre y
--              sustituidas por `journal_entry`, que además admite repeticiones.
--
-- Se van seis columnas y sus cinco índices:
--   user_book_editions.consumed_at                + INDEX idx_user_editions_consumed
--   user_book_editions.last_session_completed_at  (sin índice)
--   user_movies.consumed_at                       + INDEX idx_user_movies_consumed
--   user_games.completed_at                       + INDEX idx_user_games_completed
--   user_albums.completed_at                      + INDEX idx_user_albums_completed
--   user_videos.watched_at                        + INDEX idx_user_videos_watched
--
-- Cada índice se tira ANTES que su columna: son compuestos `(user_id, <columna>)`
-- y MySQL los reescribiría al vuelo si se hiciera al revés.
--
-- NO se tocan `date_started` / `date_finished` de user_games y user_albums:
-- están a dos líneas de distancia (init.sql:768-770, :949-951), tienen campo en
-- EditItemModal, las rellena el usuario y significan otra cosa —un intervalo, no
-- un consumo—. Sus índices `idx_user_games_date_started` y hermanos se quedan.
--
-- NO se toca `reading_sessions`: `MySqlReadingSessionRepository.php:114,313`
-- selecciona `end_date AS completed_at` y eso alimenta el historial de sesiones
-- de lectura, que es funcionalidad viva. La palabra coincide; la columna no.
--
-- NO se toca `user_book_editions.total_sessions_completed`: solo se va la fecha
-- `last_session_completed_at` que la acompaña.
--
-- Sin `INSERT … SELECT`: las seis columnas están vacías en todas las filas, así
-- que no hay dato ninguno que trasladar al diario.
--
-- Bloques idempotentes por INFORMATION_SCHEMA en vez de `ALTER` a pelo porque
-- MySQL 8.0 no admite `DROP COLUMN IF EXISTS` (migrations/README.md:22-24) —ni
-- tampoco `DROP INDEX IF EXISTS`, comprobado contra MySQL 8.0.44: da error 1064,
-- así que los índices llevan el mismo bloque que las columnas—. El `COUNT(*)`
-- de STATISTICS devuelve una fila POR COLUMNA del índice (dos, en compuestos),
-- por eso la comparación es `> 0` y no `= 1`.

-- ---------------------------------------------------------------------------
-- user_book_editions
-- ---------------------------------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_book_editions' AND INDEX_NAME = 'idx_user_editions_consumed'
);
SET @sql = IF(@idx_exists > 0,
  'ALTER TABLE user_book_editions DROP INDEX idx_user_editions_consumed',
  'SELECT ''idx_user_editions_consumed already dropped'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_book_editions' AND COLUMN_NAME = 'consumed_at'
);
SET @sql = IF(@col_exists > 0,
  'ALTER TABLE user_book_editions DROP COLUMN consumed_at',
  'SELECT ''user_book_editions.consumed_at already dropped'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_book_editions' AND COLUMN_NAME = 'last_session_completed_at'
);
SET @sql = IF(@col_exists > 0,
  'ALTER TABLE user_book_editions DROP COLUMN last_session_completed_at',
  'SELECT ''user_book_editions.last_session_completed_at already dropped'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- user_movies
-- ---------------------------------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_movies' AND INDEX_NAME = 'idx_user_movies_consumed'
);
SET @sql = IF(@idx_exists > 0,
  'ALTER TABLE user_movies DROP INDEX idx_user_movies_consumed',
  'SELECT ''idx_user_movies_consumed already dropped'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_movies' AND COLUMN_NAME = 'consumed_at'
);
SET @sql = IF(@col_exists > 0,
  'ALTER TABLE user_movies DROP COLUMN consumed_at',
  'SELECT ''user_movies.consumed_at already dropped'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- user_games  (OJO: date_started / date_finished y sus índices NO se tocan)
-- ---------------------------------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_games' AND INDEX_NAME = 'idx_user_games_completed'
);
SET @sql = IF(@idx_exists > 0,
  'ALTER TABLE user_games DROP INDEX idx_user_games_completed',
  'SELECT ''idx_user_games_completed already dropped'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_games' AND COLUMN_NAME = 'completed_at'
);
SET @sql = IF(@col_exists > 0,
  'ALTER TABLE user_games DROP COLUMN completed_at',
  'SELECT ''user_games.completed_at already dropped'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- user_albums  (OJO: date_started / date_finished y sus índices NO se tocan)
-- ---------------------------------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_albums' AND INDEX_NAME = 'idx_user_albums_completed'
);
SET @sql = IF(@idx_exists > 0,
  'ALTER TABLE user_albums DROP INDEX idx_user_albums_completed',
  'SELECT ''idx_user_albums_completed already dropped'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_albums' AND COLUMN_NAME = 'completed_at'
);
SET @sql = IF(@col_exists > 0,
  'ALTER TABLE user_albums DROP COLUMN completed_at',
  'SELECT ''user_albums.completed_at already dropped'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- user_videos
-- ---------------------------------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_videos' AND INDEX_NAME = 'idx_user_videos_watched'
);
SET @sql = IF(@idx_exists > 0,
  'ALTER TABLE user_videos DROP INDEX idx_user_videos_watched',
  'SELECT ''idx_user_videos_watched already dropped'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_videos' AND COLUMN_NAME = 'watched_at'
);
SET @sql = IF(@col_exists > 0,
  'ALTER TABLE user_videos DROP COLUMN watched_at',
  'SELECT ''user_videos.watched_at already dropped'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
