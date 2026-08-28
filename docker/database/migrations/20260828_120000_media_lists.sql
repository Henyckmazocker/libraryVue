-- Migration: 20260828_120000_media_lists
-- Description: Named collections that mix media, with three visibilities

-- Hasta ahora la única forma de agrupar ítems era el estado (`to-read`,
-- `in-watchlist`…), que es un conjunto cerrado y por medio: no había manera de
-- tener «Para el verano» con dos libros y una película. Los tags tampoco
-- cruzan, porque hay una tabla `user_*_tags` por medio.
--
-- `entity_type` + `entity_id` es el mismo par que `feed_events` usa desde la
-- migración de mayo y que `recommendations` repitió en agosto: es la tercera
-- aplicación del patrón, no una idea nueva. Las series se guardan con
-- AddMovieUseCase, así que viajan como 'movie'.
CREATE TABLE IF NOT EXISTS media_list (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id    INT NOT NULL,
  name        VARCHAR(120) NOT NULL,
  description TEXT NULL,
  -- `collaborative` NO es pública: la ven sus colaboradores y nadie más. Para
  -- «que todos la vean y unos pocos la editen» se usa `public` con
  -- colaboradores, y por eso `media_list_collaborator` no está atado a este
  -- valor. La regla entera vive en `Domain/Services/ListAccess.php`.
  visibility  ENUM('private','public','collaborative') NOT NULL DEFAULT 'private',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
  -- «Mis listas» y «las listas públicas de fulano» son la misma consulta con
  -- distinto filtro, y las dos salen de este índice.
  INDEX idx_media_list_owner (owner_id, visibility)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_list_item (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  list_id      INT UNSIGNED NOT NULL,
  entity_type  ENUM('book','movie','game','album','video') NOT NULL,
  entity_id    VARCHAR(50) NOT NULL,       -- isbn / imdb_id / igdb_id / mbid / youtube_id
  -- Copiados al añadir, igual que en `feed_events` y `recommendations`: la
  -- lista se pinta sin resolver nada contra cinco catálogos distintos. La
  -- portada se pide por `?cover=<entity_type>/<entity_id>` y esta columna es
  -- solo el respaldo.
  entity_title VARCHAR(255) NULL,
  entity_cover VARCHAR(500) NULL,
  -- Quién metió el ítem. Hoy en una colaborativa cualquiera puede quitar
  -- cualquier cosa —es lo que «colaborativa» significa—, pero la columna deja
  -- abierta la otra política sin migrar.
  added_by     INT NOT NULL,
  -- El orden manual no entra en este plan; la columna se escribe ya para no
  -- migrar dos veces.
  position     INT UNSIGNED NOT NULL DEFAULT 0,
  added_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (list_id)  REFERENCES media_list(id) ON DELETE CASCADE,
  FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE CASCADE,
  -- El mismo ítem no entra dos veces en la misma lista. `add_list_item` lo
  -- traduce a un 409 legible en vez de dejar salir el error de duplicado.
  UNIQUE KEY uq_list_entity (list_id, entity_type, entity_id),
  INDEX idx_list_position (list_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Se consulta en las TRES visibilidades, no solo en `collaborative`.
CREATE TABLE IF NOT EXISTS media_list_collaborator (
  list_id  INT UNSIGNED NOT NULL,
  user_id  INT NOT NULL,
  added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (list_id, user_id),
  FOREIGN KEY (list_id) REFERENCES media_list(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
