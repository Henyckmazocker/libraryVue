-- Migration: 20260904_120000_journal
-- Author: David
-- Description: Diario de consumo — una entrada por cosa consumida y por día

-- La primera tabla del proyecto con forma de DIARIO. Hasta ahora la app sabía
-- qué tienes y en qué estado está, pero no cuándo lo consumiste: las seis
-- columnas del esquema pensadas para eso (`user_movies.consumed_at`,
-- `user_book_editions.consumed_at`, `user_games.completed_at`,
-- `user_albums.completed_at`, `user_videos.watched_at`,
-- `user_series_seasons.date_viewed`) están vacías en TODAS sus filas, y solo dos
-- tienen dónde teclearse en la interfaz.
--
-- Lo que la distingue de esas columnas es que aquí hay UNA FILA POR VEZ: ver la
-- misma película tres veces son tres entradas, como `reading_sessions` lleva
-- haciendo con los libros desde el principio. Por eso no se rellenaron aquellas
-- columnas: una columna no puede guardar una repetición.
--
-- `uq_journal_source` es toda la lógica de duplicados, y funciona porque MySQL
-- admite NULLs repetidos en un índice único:
--   * `manual`         → source_id NULL      → se puede repetir sin límite
--   * `reading_session`→ id de la sesión     → cerrarla dos veces actualiza, no duplica
--   * `series_season`  → "<imdb>:<temporada>"→ una entrada por temporada
--   * `status`         → "<medio>:<id>:<día>"→ marcar y desmarcar el mismo día no apunta dos veces

CREATE TABLE IF NOT EXISTS journal_entry (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  media        ENUM('book','movie','series','game','album','video') NOT NULL,
  entity_id    VARCHAR(50)  NOT NULL COMMENT 'Identificador EXTERNO, el que espera la ruta de detalle: ISBN-13, imdbID, id de IGDB, id de álbum, youtube_id',
  entity_title VARCHAR(255) NOT NULL COMMENT 'Copiado al guardar, como feed_events y media_list_item',
  entity_cover VARCHAR(500) NULL     COMMENT 'Respaldo: al pintar manda la copia local (CoverService)',
  entry_date   DATE NOT NULL         COMMENT 'El día que lo consumiste, no el día que lo apuntaste',
  rating       DECIMAL(2,1) NULL     COMMENT '0.0 a 5.0 con medias, como el resto del proyecto',
  source       ENUM('manual','status','reading_session','series_season') NOT NULL DEFAULT 'manual',
  source_id    VARCHAR(120) NULL     COMMENT 'NULL solo en manual: es lo que permite repetir',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_journal_source (user_id, source, source_id),
  KEY idx_journal_user_date (user_id, entry_date DESC),
  KEY idx_journal_item (user_id, media, entity_id, entry_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Siembra: SOLO desde lo que tiene una fecha que signifique «esto lo consumí
-- ese día». `user_*_statuses.updated_at` queda fuera a propósito: es la fecha en
-- que lo marcaste en la app, y en un ítem importado hace meses miente.
--
-- `INSERT IGNORE` y no `ON DUPLICATE KEY UPDATE`: la siembra no debe pisar una
-- entrada que el usuario ya haya editado a mano si esto se vuelve a correr.
-- ---------------------------------------------------------------------------

-- Sesiones de lectura cerradas → una entrada por sesión.
-- `book_editions` NO tiene columna `isbn`: tiene `isbn_13` e `isbn_10`, y la
-- ruta `/books/:isbn` usa la primera (`MySqlEditionRepository::findByIsbn`
-- prueba ISBN-13 y luego ISBN-10).
INSERT IGNORE INTO journal_entry
  (user_id, media, entity_id, entity_title, entity_cover, entry_date, rating, source, source_id)
SELECT rs.user_id,
       'book',
       COALESCE(be.isbn_13, be.isbn_10),
       be.title,
       be.cover_url_medium,
       DATE(rs.end_date),
       NULL,
       'reading_session',
       CAST(rs.id AS CHAR)
FROM reading_sessions rs
JOIN book_editions be ON be.edition_id = rs.edition_id
WHERE rs.end_date IS NOT NULL
  AND COALESCE(be.isbn_13, be.isbn_10) IS NOT NULL;

-- Temporadas vistas con fecha → una entrada por temporada.
-- Las series viven en `movie` y se identifican por su imdbID (`movie.isbn`).
INSERT IGNORE INTO journal_entry
  (user_id, media, entity_id, entity_title, entity_cover, entry_date, rating, source, source_id)
SELECT uss.user_id,
       'series',
       uss.series_isbn,
       m.title,
       m.coverUrl,
       uss.date_viewed,
       uss.personal_rating,
       'series_season',
       CONCAT(uss.series_isbn, ':', uss.season_number)
FROM user_series_seasons uss
JOIN movie m ON m.isbn = uss.series_isbn
WHERE uss.date_viewed IS NOT NULL
  AND uss.status = 'viewed';
