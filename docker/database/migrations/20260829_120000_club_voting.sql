-- Migration: 20260829_120000_club_voting
-- Author: David
-- Description: Votación del club — la ronda, las propuestas y los votos

-- Hasta aquí el ítem del club lo elegía el dueño (`set_club_pick`) y nadie más.
-- Esto lo sustituye por una RONDA: cuando el club se queda sin ítem activo,
-- cada miembro propone uno, se vota, y gana el más votado. `set_club_pick` NO
-- desaparece: se queda como vía de escape del dueño para el club atascado.

-- Una ronda por cada vez que el club se queda sin ítem. La fase va en un ENUM
-- explícito y NO derivada de fechas, a diferencia de `club_pick.finished_at`:
-- son tres estados con transiciones propias, y escribirlos como dos timestamps
-- nulos obligaría a leer «voting_started_at IS NOT NULL AND closed_at IS NULL»
-- en cada consulta para decir «se está votando».
--
-- `winning_proposal_id` es una COLUMNA y no un `ORDER BY`, y es la decisión
-- menos evidente del esquema: la ronda se resuelve al LEER el club —no hay cron
-- (`Infrastructure/Http/PostResponse.php:12`)— y el desempate final es un
-- sorteo. Un ganador sorteado que se recalculase en cada lectura cambiaría en
-- cada `get_club`, y dos miembros mirando a la vez verían libros distintos.
CREATE TABLE IF NOT EXISTS club_round (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  club_id              INT UNSIGNED NOT NULL,
  phase                ENUM('proposing','voting','closed') NOT NULL DEFAULT 'proposing',
  ballot               TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- 1 = voto normal, 2+ = desempates
  winning_proposal_id  INT UNSIGNED NULL,
  created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  closed_at            TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (club_id) REFERENCES club(id) ON DELETE CASCADE,
  -- La consulta que se hace en CADA `get_club`: «¿hay ronda abierta?».
  -- Y es además la guarda de que no se abran dos: abrir es idempotente.
  INDEX idx_club_round_open (club_id, phase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Misma pareja `entity_type` + `entity_id` que `club_pick`, `feed_events`,
-- `recommendations` y `media_list_item`: la quinta aplicación del patrón. Las
-- series se guardan con AddMovieUseCase, así que viajan como 'movie'.
--
-- El UNIQUE es la regla «una propuesta por persona y ronda», puesta en el
-- ESQUEMA porque es lo único que la sostiene ante dos pestañas a la vez.
--
-- `entity_title` y `entity_cover` van copiados como en las otras cuatro tablas
-- del patrón: la pantalla se pinta sin resolver nada contra cinco catálogos. La
-- portada se pide por `?cover=<entity_type>/<entity_id>`, y como son ítems que
-- NO están en la biblioteca de nadie, es portada de catálogo.
CREATE TABLE IF NOT EXISTS club_proposal (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  round_id     INT UNSIGNED NOT NULL,
  user_id      INT NOT NULL,
  entity_type  ENUM('book','movie','game','album','video') NOT NULL,
  entity_id    VARCHAR(50) NOT NULL,
  entity_title VARCHAR(255) NULL,
  entity_cover VARCHAR(500) NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (round_id) REFERENCES club_round(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES users(id)      ON DELETE CASCADE,
  UNIQUE KEY uq_proposal_per_user (round_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- `ballot` en la PK es lo que permite el desempate: la revotación son votos
-- nuevos de la MISMA ronda, no una ronda nueva. Sin él, revotar exigiría borrar
-- los votos anteriores y se perdería el recuento que justificó la eliminación.
--
-- La PK es además lo que permite CAMBIAR el voto mientras la ronda siga
-- abierta, con un `ON DUPLICATE KEY UPDATE`.
CREATE TABLE IF NOT EXISTS club_vote (
  round_id    INT UNSIGNED NOT NULL,
  ballot      TINYINT UNSIGNED NOT NULL,
  user_id     INT NOT NULL,
  proposal_id INT UNSIGNED NOT NULL,
  voted_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (round_id, ballot, user_id),
  FOREIGN KEY (round_id)    REFERENCES club_round(id)    ON DELETE CASCADE,
  FOREIGN KEY (user_id)     REFERENCES users(id)         ON DELETE CASCADE,
  FOREIGN KEY (proposal_id) REFERENCES club_proposal(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
