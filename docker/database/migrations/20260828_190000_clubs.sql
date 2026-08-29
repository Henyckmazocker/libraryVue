-- Migration: 20260828_190000_clubs
-- Author: David
-- Description: Clubs — un grupo de amigos con un ítem activo e historial

-- La primera agrupación de PERSONAS del proyecto. Hasta ahora `friendships` era
-- una relación de dos y nada más: no existía el concepto de grupo en ningún
-- sitio. Un club es un grupo de amigos consumiendo la misma cosa a la vez.
--
-- Lo que lo distingue de una lista compartida (`media_list`) es que aquí hay
-- UN ítem activo y un historial, y que las notas de los demás se ocultan si te
-- van por delante. Por eso no se montó sobre una lista.

CREATE TABLE IF NOT EXISTS club (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id    INT NOT NULL,
  name        VARCHAR(120) NOT NULL,
  description TEXT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS club_member (
  club_id   INT UNSIGNED NOT NULL,
  user_id   INT NOT NULL,
  joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (club_id, user_id),
  FOREIGN KEY (club_id) REFERENCES club(id)  ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  -- «En qué clubs estoy», que es la consulta al entrar en la sección social.
  -- Sin él sería un scan de la tabla entera de miembros.
  INDEX idx_club_member_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- El ítem activo es `finished_at IS NULL`; el historial es el resto. NO hay
-- columna `is_active`: un booleano y una fecha diciendo lo mismo se
-- desincronizan. La regla «solo un activo por club» la impone el use case y no
-- el esquema, porque MySQL no tiene índices parciales y un
-- `UNIQUE (club_id, finished_at)` solo funcionaría con un valor centinela.
--
-- `entity_type` + `entity_id` es el mismo par que `feed_events`,
-- `recommendations` y `media_list_item`: es la cuarta aplicación del patrón.
-- Las series se guardan con AddMovieUseCase, así que viajan como 'movie'.
CREATE TABLE IF NOT EXISTS club_pick (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  club_id      INT UNSIGNED NOT NULL,
  entity_type  ENUM('book','movie','game','album','video') NOT NULL,
  entity_id    VARCHAR(50) NOT NULL,       -- isbn / imdb_id / rawg_id / mbid / youtube_id
  -- Copiados al elegir, igual que en las otras tres tablas del patrón: la
  -- pantalla del club se pinta sin resolver nada contra cinco catálogos. La
  -- portada se pide por `?cover=<entity_type>/<entity_id>` y esta columna es
  -- solo el respaldo.
  entity_title VARCHAR(255) NULL,
  entity_cover VARCHAR(500) NULL,
  started_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  finished_at  TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (club_id) REFERENCES club(id) ON DELETE CASCADE,
  -- Sirve a las dos consultas: el activo (`finished_at IS NULL`) y el
  -- historial ordenado.
  INDEX idx_club_pick_active (club_id, finished_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La invitación al club entra por el MISMO buzón que las recomendaciones y las
-- invitaciones a colaborar en una lista, y por el mismo motivo que aquellas:
-- `get_inbox_count` es la acción más llamada de la app y tiene que seguir
-- siendo un solo `COUNT(*)` que sale entero de `idx_inbox`.
--
-- Ojo con el modelo: `Recommendation::VALID_ENTITY_TYPES` pasa a incluir
-- `club` porque es lo que la COLUMNA acepta, pero `send_recommendation` valida
-- contra `MEDIA_ENTITY_TYPES` (los cinco medios). Sin esa separación se podría
-- «recomendar» un club como si fuera un ítem, y la bandeja intentaría darlo de
-- alta en la biblioteca con el `enrich` de un medio inexistente.
--
-- MySQL 8.0 no admite `IF NOT EXISTS` en `ALTER TABLE ... MODIFY`, así que se
-- comprueba antes contra INFORMATION_SCHEMA, como manda el README de esta
-- carpeta.
SET @ya_esta = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'recommendations'
    AND COLUMN_NAME = 'entity_type'
    AND COLUMN_TYPE LIKE '%club%'
);
SET @sql = IF(@ya_esta = 0,
  "ALTER TABLE recommendations MODIFY entity_type ENUM('book','movie','game','album','video','list','club') NOT NULL",
  "SELECT 'recommendations.entity_type already accepts club'"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
