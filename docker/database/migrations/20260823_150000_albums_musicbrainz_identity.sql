-- Migration: 20260823_150000_albums_musicbrainz_identity.sql
-- Author: David
-- Description: la identidad de un álbum pasa de spotify_id a un MBID de MusicBrainz.
--
-- Por qué: albums guarda catálogo de Spotify (título, artista, sello, fecha,
-- popularidad, UPC) sin caducidad, y los Spotify Developer Terms prohíben
-- expresamente "store, aggregate or create compilations or databases of Spotify
-- Content". Además la clave natural era spotify_id, así que si Spotify cierra
-- el grifo no queda forma de saber qué álbum era cuál.
--
-- Por qué AHORA: verificado el 2026-08-11 y de nuevo el 2026-08-23, albums
-- tiene 0 filas en dev y 0 en prod. Esto es un ALTER sobre una tabla vacía; con
-- datos dentro sería un plan entero con su backfill.
--
-- spotify_id NO se borra, se hace anulable: es el puente para reconciliar un
-- álbum que llegue por el fallback de Spotify cuando el mirror no lo tenga, y
-- borrar una columna es irreversible en un esquema sin rollback (regla 4 del
-- README de esta carpeta).

-- 1) mb_release_group_gid — el MBID, la identidad nueva
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'albums' AND COLUMN_NAME = 'mb_release_group_gid'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE albums ADD COLUMN mb_release_group_gid CHAR(36) NULL
     COMMENT ''MusicBrainz release group MBID: la identidad del album'' AFTER id',
  'SELECT ''mb_release_group_gid ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) mb_artist_gid — el artista principal, para poder navegar por artista sin Spotify
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'albums' AND COLUMN_NAME = 'mb_artist_gid'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE albums ADD COLUMN mb_artist_gid CHAR(36) NULL
     COMMENT ''MusicBrainz artist MBID del artista principal'' AFTER artist_id',
  'SELECT ''mb_artist_gid ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) spotify_id deja de ser NOT NULL.
--    Ojo con el orden: el UNIQUE original se creó sobre una columna NOT NULL y
--    hay que conservarlo, porque es lo que impide guardar dos veces el mismo
--    álbum llegado por el fallback. En MySQL un UNIQUE admite varios NULL, que
--    es justo lo que hace falta cuando la identidad va por MBID.
SET @is_nullable = (
  SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'albums' AND COLUMN_NAME = 'spotify_id'
);
SET @sql = IF(@is_nullable = 'NO',
  'ALTER TABLE albums MODIFY COLUMN spotify_id VARCHAR(22) NULL
     COMMENT ''Identidad heredada de Spotify; puente de reconciliacion con el fallback''',
  'SELECT ''spotify_id ya es anulable'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) UNIQUE sobre el MBID. Sin esto, dos guardados del mismo álbum crean dos
--    filas: el UNIQUE de spotify_id no cubre nada cuando la identidad es un MBID
--    y spotify_id va a NULL.
SET @idx_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'albums' AND INDEX_NAME = 'uq_albums_mbid'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE albums ADD UNIQUE KEY uq_albums_mbid (mb_release_group_gid)',
  'SELECT ''uq_albums_mbid ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) Índice por artista, para "más de este artista" sin salir a la red.
SET @idx_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'albums' AND INDEX_NAME = 'idx_albums_mb_artist'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE albums ADD INDEX idx_albums_mb_artist (mb_artist_gid)',
  'SELECT ''idx_albums_mb_artist ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
