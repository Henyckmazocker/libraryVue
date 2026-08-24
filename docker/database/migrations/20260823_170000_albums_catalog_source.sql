-- Migration: 20260823_170000_albums_catalog_source.sql
-- Author: David
-- Description: marcar de dónde salió el catálogo de cada álbum, para poder caducar el de Spotify.
--
-- Por qué: el mirror de MusicBrainz sirve la inmensa mayoría de los álbumes,
-- pero FallbackAlbumCatalog cae a Spotify cuando el mirror no tiene nada, y
-- guardar ese resultado metía contenido de Spotify en albums sin caducidad
-- alguna — la misma infracción que el mirror vino a resolver, más estrecha.
--
-- catalog_cached_at NO es decorativa: mirror-sync.sh --purge la usa para anular
-- el enriquecimiento (sello, popularidad, géneros, UPC, duración) de las filas
-- de Spotify pasados 5 meses. Anular, no borrar la fila: user_albums la
-- referencia y eso es dato del usuario, no catálogo ajeno.
--
-- Las filas que ya existen se marcan según lo que tengan: si llevan MBID son de
-- MusicBrainz, y si no, vinieron de Spotify.

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'albums' AND COLUMN_NAME = 'catalog_source'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE albums ADD COLUMN catalog_source VARCHAR(16) NULL
     COMMENT ''musicbrainz | spotify: de donde salio el catalogo de esta fila'' AFTER upc',
  'SELECT ''catalog_source ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'albums' AND COLUMN_NAME = 'catalog_cached_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE albums ADD COLUMN catalog_cached_at DATETIME NULL
     COMMENT ''Cuando se trajo el catalogo; solo importa si catalog_source = spotify'' AFTER catalog_source',
  'SELECT ''catalog_cached_at ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- El índice que hace barato el --purge.
SET @idx_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'albums' AND INDEX_NAME = 'idx_albums_catalog_cached'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE albums ADD INDEX idx_albums_catalog_cached (catalog_source, catalog_cached_at)',
  'SELECT ''idx_albums_catalog_cached ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Retroactivo, por lo que cada fila tenga hoy.
UPDATE albums
   SET catalog_source = IF(mb_release_group_gid IS NOT NULL, 'musicbrainz', 'spotify'),
       catalog_cached_at = COALESCE(catalog_cached_at, created_at)
 WHERE catalog_source IS NULL;
