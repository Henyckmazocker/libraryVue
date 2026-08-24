-- ============================================================================
-- mirror_schema.sql — Esquema del mirror local de catálogos (library_mirror)
-- ============================================================================
-- Lo aplica docker-compose.mirror.yml (docker-entrypoint-initdb.d) y lo
-- reaplica `mirror-sync.sh --bootstrap`. NO va en docker/database/migrations/,
-- por dos razones:
--
--   1. run_migrations.sh conecta como library_user, que solo tiene
--      GRANT ALL ON library_db.* — no puede crear una base nueva.
--   2. prod-deploy.sh usa el MISMO runner. Un fichero en migrations/ viajaría
--      a master y fallaría allí por permisos, y como run_migrations.sh hace
--      exit 1 sin registrar la migración, bloquearía todas las posteriores.
--
-- Encaja además con la naturaleza del mirror: es reconstruible y desechable,
-- así que no comparte cadena de migraciones ni backups con la biblioteca del
-- usuario. Su versionado es este fichero más la tabla mirror_import.
--
-- Idempotente de punta a punta: se puede reaplicar sin efectos.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS library_mirror
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aquí NO se crean usuarios ni se reparten permisos. El mirror es un servidor
-- compartido por los stacks de dev y de producción, y sus usuarios llevan
-- contraseña: este fichero está versionado. Los crea el entrypoint de
-- docker-compose.mirror.yml (library_mirror_user) y `mirror-sync.sh --bootstrap`
-- (library_mirror_importer, que necesita el privilegio FILE, y ese es global).

USE library_mirror;

-- ============================================================================
-- ESQUELETO IMDB — lo que se busca
-- ============================================================================

CREATE TABLE IF NOT EXISTS imdb_title (
  tconst          VARCHAR(12)  PRIMARY KEY,
  title_type      VARCHAR(16)  NOT NULL,
  primary_title   VARCHAR(512) NOT NULL,
  original_title  VARCHAR(512) NULL,
  is_adult        TINYINT(1)   NOT NULL DEFAULT 0,
  start_year      SMALLINT UNSIGNED NULL,
  end_year        SMALLINT UNSIGNED NULL,
  runtime_minutes INT UNSIGNED NULL,
  genres          VARCHAR(128) NULL,          -- CSV tal cual viene de IMDb
  average_rating  DECIMAL(3,1) NULL,          -- de title.ratings
  num_votes       INT UNSIGNED NULL,          -- de title.ratings; ordena por popularidad
  FULLTEXT KEY ft_title (primary_title, original_title),
  KEY idx_type_year (title_type, start_year),
  KEY idx_votes (num_votes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Títulos de estreno en España; sin esto no se busca en español
-- ("jungla de cristal" no existe en title.basics, solo en title.akas).
CREATE TABLE IF NOT EXISTS imdb_title_es (
  tconst   VARCHAR(12) NOT NULL,
  ordering SMALLINT UNSIGNED NOT NULL,
  title    VARCHAR(512) NOT NULL,
  PRIMARY KEY (tconst, ordering),
  FULLTEXT KEY ft_title_es (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- primary_title y la nota NO son decorativos: SeriesSeasonTracker.vue:158-167
-- pinta el título y el imdbRating de cada episodio. Sin ellos, la lista sale
-- numerada y muda. Vienen de las filas tvEpisode de title.basics, que no entran
-- en imdb_title, y de title.ratings.
CREATE TABLE IF NOT EXISTS imdb_episode (
  tconst         VARCHAR(12) PRIMARY KEY,
  parent_tconst  VARCHAR(12) NOT NULL,
  season_number  SMALLINT UNSIGNED NULL,
  -- MEDIUMINT y no SMALLINT: el dump real llega a episode_number = 91.334
  -- (medido el 2026-08-20), y SMALLINT UNSIGNED topa en 65.535.
  episode_number MEDIUMINT UNSIGNED NULL,
  primary_title  VARCHAR(512) NULL,
  average_rating DECIMAL(3,1)  NULL,
  num_votes      INT UNSIGNED  NULL,
  KEY idx_parent_season (parent_tconst, season_number, episode_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- ESQUELETO MUSICBRAINZ — los álbumes que se buscan
-- ============================================================================
-- Una fila por release group, desnormalizada a propósito: en MusicBrainz lo que
-- aquí es una fila son nueve JOIN (release_group, release, artist_credit,
-- artist_credit_name, artist, release_group_primary_type, medium,
-- release_country y release_label). El importador paga ese coste una vez, en
-- frío, y la búsqueda queda en un MATCH sobre una sola tabla: 1,8-15,2 ms
-- medidos sobre las 2,88 M de filas reales.
--
-- Solo Album y EP. Los singles y los bootlegs multiplican el volumen y nadie
-- los cataloga en una biblioteca personal.

CREATE TABLE IF NOT EXISTS mb_release_group (
  gid            CHAR(36) PRIMARY KEY,       -- MBID: la identidad de un álbum
  -- La release de la que salen el sello, el barcode y el número de pistas: la
  -- más antigua del grupo. Se guarda su MBID porque es con lo que se le piden
  -- las pistas a la API de MusicBrainz; preguntar por el release group entero
  -- no termina en discos muy reeditados (>2 min en Dark Side of the Moon).
  canonical_release_gid CHAR(36) NULL,
  name           VARCHAR(512) NOT NULL,
  artist_credit  VARCHAR(512) NOT NULL,      -- desnormalizado: "Simon & Garfunkel"
  artist_gid     CHAR(36) NULL,              -- artista principal (posición 0 del crédito)
  primary_type   VARCHAR(32) NULL,           -- 'Album' | 'EP'
  first_release_year SMALLINT UNSIGNED NULL,
  first_release_date VARCHAR(10) NULL,       -- 'YYYY', 'YYYY-MM' o 'YYYY-MM-DD'
  barcode        VARCHAR(20) NULL,           -- el UPC que albums ya tiene
  label          VARCHAR(255) NULL,
  track_count    SMALLINT UNSIGNED NULL,
  has_cover_art  TINYINT(1) NOT NULL DEFAULT 0,
  -- Cuántas releases cuelgan del grupo. NO es decorativa: es la ÚNICA señal de
  -- popularidad que MusicBrainz permite calcular, y search ORDENA por ella,
  -- igual que MySqlMovieCatalog ordena por num_votes. Sin ella "kind of blue"
  -- devuelve "The Quiet Kind of Blue" antes que el disco de Miles Davis
  -- (medido), y la búsqueda local sería peor que la llamada a Spotify.
  release_count  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  FULLTEXT KEY ft_rg (name, artist_credit),
  KEY idx_artist (artist_gid),
  KEY idx_year (first_release_year),
  KEY idx_release_count (release_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- OJO: que el FULLTEXT esté aquí es correcto —esta tabla nace vacía—, pero el
-- importador NO puede crear su gemela _new con un CREATE TABLE ... LIKE, porque
-- copiaría el índice y MySQL lo construiría fila a fila. Medido sobre el
-- volumen real: con el FULLTEXT presente el INSERT tarda 7563 s; sin él, 344 s,
-- y el ALTER que lo añade después cuesta 192 s. MusicBrainzImporter lo crea
-- desnudo y lo indexa al final.

-- ============================================================================
-- ENRIQUECIMIENTO TMDB
-- ============================================================================
-- cached_at NO es decorativo: las condiciones de uso de TMDB prohíben cachear
-- más de 6 meses. mirror-sync.sh --purge (M6) se apoya en su índice.

CREATE TABLE IF NOT EXISTS tmdb_title (
  tconst        VARCHAR(12) PRIMARY KEY,
  tmdb_id       INT UNSIGNED NOT NULL,
  media_type    VARCHAR(10)  NOT NULL,   -- 'movie' | 'tv'
  title_es      VARCHAR(512) NULL,
  overview_es   TEXT         NULL,
  poster_path   VARCHAR(128) NULL,       -- ruta relativa; el host lo pone el front
  -- El director es uno de los 11 campos que lee el frontend y NO está en los
  -- dumps de IMDb (vive en title.crew + name.basics, que no se ingestan). Sin
  -- esta columna, una ficha servida desde esta caché saldría sin director,
  -- peor que la recién traída de TMDB.
  director      VARCHAR(255) NULL,
  total_seasons SMALLINT UNSIGNED NULL,
  cached_at     DATETIME     NOT NULL,
  KEY idx_cached_at (cached_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- PISTAS DE ÁLBUM
-- ============================================================================
-- Los dumps de MusicBrainz traen el CONTEO de pistas, no la lista. La lista se
-- pide a la API por la release canónica y se guarda aquí.
--
-- Sin caducidad, a diferencia de tmdb_title: aquello caduca porque las
-- condiciones de TMDB prohíben cachear más de 6 meses, y MusicBrainz publica
-- bajo CC0 sin imponer nada. fetched_at está para saber de cuándo es el dato y
-- poder refrescarlo a mano, no para que un --purge lo borre: repetir llamadas
-- de 8-45 s sin que ninguna licencia lo exija sería tirar el tiempo.

CREATE TABLE IF NOT EXISTS mb_track (
  release_group_gid CHAR(36) NOT NULL,        -- el ÁLBUM, no la release: así se consulta
  position          SMALLINT UNSIGNED NOT NULL,
  -- 'number' NO es 'position': position es el orden y siempre es entero;
  -- number es lo impreso en el disco, y en un vinilo la primera de la cara B
  -- es 'B1'. Por eso VARCHAR, y por eso la PK va sobre position.
  number            VARCHAR(8)   NULL,
  title             VARCHAR(512) NOT NULL,
  length_ms         INT UNSIGNED NULL,
  recording_gid     CHAR(36)     NULL,
  PRIMARY KEY (release_group_gid, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- El estado del fetch va aparte, y no es una tabla de más: un álbum SIN pistas
-- también necesita fila. Sin ella, «aún no se ha pedido» y «se pidió y no había
-- nada» son indistinguibles, y el backfill entraría en bucle sobre los mismos.
--
-- attempts y last_error son lo que corta ese bucle, igual que en cover_file.
CREATE TABLE IF NOT EXISTS mb_track_fetch (
  release_group_gid CHAR(36) PRIMARY KEY,
  fetched_at        DATETIME NULL,
  track_count       SMALLINT UNSIGNED NULL,
  attempts          TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error        VARCHAR(255) NULL,
  KEY idx_pending (fetched_at, attempts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- PORTADAS LOCALES
-- ============================================================================
-- El registro de qué portada se ha bajado, de dónde y cuándo. Vive aquí y no en
-- library_db porque es caché reconstruible: si se pierde, se rehace con
-- `bin/mirror covers:backfill`. Los ficheros van al volumen covers_data, y esta
-- tabla es lo único que sabe qué hay dentro.
--
-- attempts y last_error son lo que evita el bucle infinito sobre una URL que da
-- 404 para siempre: el backfill ignora las que pasan de 3 intentos.

CREATE TABLE IF NOT EXISTS cover_file (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  media_type   VARCHAR(10)  NOT NULL,      -- 'movie' | 'book' | 'album' | 'game' | 'video'
  entity_key   VARCHAR(64)  NOT NULL,      -- tconst, ISBN, MBID, id de IGDB...
  source_url   VARCHAR(1024) NOT NULL,
  storage_path VARCHAR(128) NULL,          -- 'ab/ab3f9c...e1.jpg'; NULL mientras no se haya bajado
  mime_type    VARCHAR(32)  NULL,
  bytes        INT UNSIGNED NULL,
  fetched_at   DATETIME     NULL,
  attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error   VARCHAR(255) NULL,
  UNIQUE KEY uq_entity (media_type, entity_key),
  KEY idx_pending (storage_path, attempts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TRAZABILIDAD DE IMPORTACIONES
-- ============================================================================
-- Sin esto no se sabe de qué día es el mirror.

CREATE TABLE IF NOT EXISTS mirror_import (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  source         VARCHAR(32) NOT NULL,    -- 'imdb' | 'musicbrainz'
  source_version VARCHAR(64) NULL,        -- Last-Modified del dump
  started_at     DATETIME NOT NULL,
  finished_at    DATETIME NULL,
  rows_loaded    INT UNSIGNED NULL,
  status         VARCHAR(16) NOT NULL     -- 'running' | 'ok' | 'failed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
