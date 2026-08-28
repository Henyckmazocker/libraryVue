-- Migration: 20260827_100000_recommendations
-- Description: Directed user-to-user recommendations (the inbox behind the header bell)

-- El primer canal dirigido de la app: hasta ahora la única comunicación entre
-- usuarios era la solicitud de amistad. Un usuario le manda a un amigo un ítem
-- de cualquiera de los cinco medios, con un comentario opcional.
--
-- `entity_type` + `entity_id` es el mismo par que `feed_events` usa desde la
-- migración de mayo: no hay una tabla por medio. Las series se guardan con
-- AddMovieUseCase, así que viajan como 'movie' — igual que en `cover_file`.
CREATE TABLE IF NOT EXISTS recommendations (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sender_id     INT NOT NULL,
  recipient_id  INT NOT NULL,
  entity_type   ENUM('book','movie','game','album','video') NOT NULL,
  entity_id     VARCHAR(50) NOT NULL,       -- isbn / imdb_id / igdb_id / mbid / youtube_id
  -- Copiados al mandar, igual que en `feed_events`: la bandeja tiene que poder
  -- pintarse sin resolver nada contra el catálogo. La portada se pide por
  -- `?cover=<entity_type>/<entity_id>` y esta columna es solo el respaldo.
  entity_title  VARCHAR(255) NULL,
  entity_cover  VARCHAR(500) NULL,
  comment       TEXT NULL,
  -- Tres estados y `resolved_at` en vez de borrar la fila: así queda historial
  -- y el que recomienda podría saber algún día si le hicieron caso. Hoy no se
  -- expone, pero borrar cerraría esa puerta para siempre.
  status        ENUM('pending','added','dismissed') NOT NULL DEFAULT 'pending',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at   TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (sender_id)    REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
  -- El mismo amigo no puede mandarte dos veces el mismo ítem. Consecuencia
  -- aceptada a propósito: una vez descartada, no te la pueden volver a mandar.
  -- Dejar duplicar solo cuando la anterior está resuelta exigiría un índice
  -- parcial, que MySQL no tiene; se elige la restricción simple y
  -- `send_recommendation` devuelve un error legible en vez de un 500.
  UNIQUE KEY uq_pending (recipient_id, sender_id, entity_type, entity_id),
  -- La campanita pide `get_inbox_count` en cada navegación: tiene que ser un
  -- COUNT(*) que no salga de este índice.
  INDEX idx_inbox (recipient_id, status, created_at DESC)
);
