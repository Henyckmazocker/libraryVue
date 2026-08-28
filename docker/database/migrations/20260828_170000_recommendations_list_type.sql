-- Migration: 20260828_170000_recommendations_list_type
-- Description: Let the inbox carry list collaboration invitations

-- La invitación a colaborar en una lista entra por el MISMO buzón que las
-- recomendaciones, y no por una tabla propia. El motivo es `get_inbox_count`:
-- la campanita la pide en cada navegación, es la acción más llamada de la app y
-- sale entera de `idx_inbox` con un solo `COUNT(*)`. Con dos tablas habría que
-- sumar dos consultas en cada navegación, y la paginación de `get_inbox`
-- tendría que ordenar por fecha across dos orígenes.
--
-- `entity_id` guarda el id de la lista y `entity_title` su nombre, igual que
-- copia el título de una película. `status` reaprovecha los tres de siempre:
-- `added` es «acepté la invitación».
--
-- Ojo con el modelo: `Recommendation::VALID_ENTITY_TYPES` pasa a incluir `list`
-- porque es lo que la COLUMNA acepta, pero `send_recommendation` valida contra
-- `MEDIA_ENTITY_TYPES` (los cinco medios). Sin esa separación se podría
-- «recomendar» una lista como si fuera un ítem, y la bandeja intentaría darla
-- de alta en la biblioteca.
--
-- MySQL 8.0 no admite `IF NOT EXISTS` en `ALTER TABLE ... MODIFY`, así que se
-- comprueba antes contra INFORMATION_SCHEMA, como manda el README de esta
-- carpeta.
SET @ya_esta = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'recommendations'
    AND COLUMN_NAME = 'entity_type'
    AND COLUMN_TYPE LIKE '%list%'
);
SET @sql = IF(@ya_esta = 0,
  "ALTER TABLE recommendations MODIFY entity_type ENUM('book','movie','game','album','video','list') NOT NULL",
  "SELECT 'recommendations.entity_type already accepts list'"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
