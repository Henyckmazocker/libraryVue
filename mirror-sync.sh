#!/usr/bin/env bash
# =============================================================================
# mirror-sync.sh — LibraryVue: mantenimiento del mirror local de catálogos
# =============================================================================
# Uso:
#   ./mirror-sync.sh --status   → fecha del dump vigente y estado de la caché
#   ./mirror-sync.sh --imdb     → reimporta los dumps de IMDb (~13 min)
#   ./mirror-sync.sh --musicbrainz → reimporta el dump de MusicBrainz (~26 min)
#   ./mirror-sync.sh --purge    → borra el enriquecimiento TMDB caducado
#   ./mirror-sync.sh --bootstrap → crea red, volúmenes y usuarios del mirror
#
# El mirror vive en SU PROPIO stack (docker-compose.mirror.yml), compartido por
# dev y por producción: son 2,2 GB de catálogo público y dos cachés con cuota
# ajena, y duplicarlo por entorno costaría el doble de todo. Por eso este script
# habla con DOS servidores MySQL: el del mirror (catálogo) y el de desarrollo
# (library_db, la biblioteca del usuario, que --purge, --tracks y --covers leen).
#
# El mirror es reconstruible y desechable: nada de lo que hay en library_mirror
# es dato del usuario, y --imdb lo rehace entero sin dejar la búsqueda caída
# (las tablas se cargan aparte y se cambian con un RENAME atómico).
#
# --purge no es higiene, es cumplimiento: las condiciones de uso de TMDB
# prohíben cachear más de 6 meses. Se purga a los 5 para tener margen.
# =============================================================================

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="$ROOT_DIR/.env"

# Cinco, no seis: margen antes del límite que imponen las condiciones de TMDB.
PURGE_AFTER="5 MONTH"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info()    { echo -e "${BLUE}[INFO]${NC}  $*"; }
success() { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
error()   { echo -e "${RED}[ERROR]${NC} $*" >&2; }

# ---------------------------------------------------------------------------
# Argumentos
# ---------------------------------------------------------------------------
MODE="status"
FORCE=""
for arg in "$@"; do
  case "$arg" in
    --imdb)   MODE="imdb"   ;;
    --musicbrainz) MODE="musicbrainz" ;;
    --tracks) MODE="tracks"  ;;
    --force)  FORCE="--force" ;;
    --purge)  MODE="purge"  ;;
    --covers) MODE="covers" ;;
    --status) MODE="status" ;;
    --bootstrap) MODE="bootstrap" ;;
    --help|-h)
      echo "Uso: $0 [--status|--bootstrap|--imdb|--musicbrainz|--tracks|--purge|--covers] [--force] [--help]"
      echo ""
      echo "  (sin args)     Igual que --status"
      echo "  --status       Fecha del dump vigente, filas por tabla y caché TMDB"
      echo "  --imdb         Reimporta los dumps de IMDb en library_mirror"
      echo "  --musicbrainz  Reimporta el dump de MusicBrainz (álbumes) si hay uno nuevo"
      echo "  --purge        Caduca el catálogo ajeno: tmdb_title y el de Spotify en albums"
      echo "  --tracks       Baja de MusicBrainz las pistas que falten de tus álbumes"
      echo "  --covers       Caduca la caché del catálogo y baja las portadas pendientes"
      echo "  --force        Con --musicbrainz, reimporta aunque el dump ya esté dentro"
      echo "  --bootstrap    Crea la red, los volúmenes y los usuarios del mirror y lo levanta"
      exit 0
      ;;
  esac
done

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
# El stack de DESARROLLO (library_db, backend que corre bin/mirror).
compose_cmd() {
  if docker compose version &>/dev/null 2>&1; then
    docker compose "$@"
  else
    docker-compose "$@"
  fi
}

# El stack del MIRROR, que es otro. Separarlos no es ceremonia: `--musicbrainz`
# hace DROP/RENAME y sube el buffer pool a 4 GB, y eso no puede ocurrir en el
# servidor que atiende a producción ni en el que guarda la biblioteca.
MIRROR_COMPOSE="$ROOT_DIR/docker-compose.mirror.yml"
MIRROR_CONTAINER="libraryvue-mirror-mysql"

mirror_compose_cmd() {
  if docker compose version &>/dev/null 2>&1; then
    docker compose -f "$MIRROR_COMPOSE" "$@"
  else
    docker-compose -f "$MIRROR_COMPOSE" "$@"
  fi
}

env_get() {
  local file="$1" key="$2"
  grep -E "^${key}=" "$file" 2>/dev/null | head -1 | cut -d'=' -f2- || true
}

# El mirror se consulta como library_mirror_user, igual que lo hace la app.
# Usuario propio y no library_user: este servidor lo comparten dev y prod, y un
# MySQL tiene una sola contraseña por usuario — la de library_user difiere.
mirror_sql() {
  local pass
  pass="$(env_get "$ENV_FILE" DB_MIRROR_PASSWORD)"
  if [[ -z "$pass" ]]; then
    error "No se pudo resolver DB_MIRROR_PASSWORD desde $ENV_FILE."
    error "Ejecuta ./dev-setup.sh o ./mirror-sync.sh --bootstrap para generarla."
    exit 1
  fi

  docker exec -i "$MIRROR_CONTAINER" \
    mysql -ulibrary_mirror_user -p"$pass" library_mirror -e "$1" 2>/dev/null
}

# La biblioteca del usuario, no el mirror: el purgado del catálogo de Spotify
# toca albums, que vive en library_db.
library_sql() {
  local pass
  pass="$(env_get "$ENV_FILE" MYSQL_PASSWORD)"
  if [[ -z "$pass" ]]; then
    error "No se pudo resolver MYSQL_PASSWORD desde $ENV_FILE."
    return 1
  fi
  compose_cmd exec -T mysql mysql -ulibrary_user -p"$pass" library_db -N -B -e "$1" 2>/dev/null
}

# El buffer pool se toca como root, y es el DEL MIRROR: library_mirror_user no
# tiene SYSTEM_VARIABLES_ADMIN, y el servidor que hay que ajustar durante una
# importación es el del catálogo, no el de la biblioteca.
root_sql() {
  local pass
  pass="$(env_get "$ENV_FILE" MYSQL_ROOT_PASSWORD)"
  if [[ -z "$pass" ]]; then
    error "No se pudo resolver MYSQL_ROOT_PASSWORD desde $ENV_FILE."
    return 1
  fi
  docker exec -i "$MIRROR_CONTAINER" mysql -uroot -p"$pass" -N -B -e "$1" 2>/dev/null
}

check_running() {
  if ! docker exec "$MIRROR_CONTAINER" mysqladmin ping -h localhost --silent &>/dev/null; then
    error "El MySQL del mirror no está disponible."
    error "Levántalo con:  ./mirror-sync.sh --bootstrap"
    exit 1
  fi

  # El de desarrollo también hace falta: library_db (la biblioteca del usuario)
  # la leen --purge, --tracks y --covers, y bin/mirror corre en su backend.
  if ! compose_cmd exec -T mysql mysqladmin ping -h localhost --silent &>/dev/null; then
    error "El MySQL de desarrollo no está disponible. Levántalo con ./dev-setup.sh."
    exit 1
  fi
}

# ---------------------------------------------------------------------------
# --status
# ---------------------------------------------------------------------------
# ---------------------------------------------------------------------------
# --bootstrap
# ---------------------------------------------------------------------------
# Crea de una vez lo que comparten los tres stacks (dev, prod y el mirror) y
# levanta el servidor del catálogo. Idempotente: se puede repetir sin efectos.
#
# La red y los volúmenes se declaran `external: true` en los tres compose, y por
# eso hay que crearlos aquí: `external` significa que ningún proyecto los posee,
# que es justo la propiedad que se busca — un `docker compose down -v` en dev o
# en prod NO puede llevarse el catálogo ni las portadas.
# ---------------------------------------------------------------------------
cmd_bootstrap() {
  local mirror_pass import_pass root_pass

  mirror_pass="$(env_get "$ENV_FILE" DB_MIRROR_PASSWORD)"
  import_pass="$(env_get "$ENV_FILE" DB_MIRROR_IMPORT_PASSWORD)"
  root_pass="$(env_get "$ENV_FILE" MYSQL_ROOT_PASSWORD)"

  if [[ -z "$root_pass" ]]; then
    error "No se pudo resolver MYSQL_ROOT_PASSWORD desde $ENV_FILE."
    error "Ejecuta ./dev-setup.sh primero."
    exit 1
  fi
  if [[ -z "$mirror_pass" || -z "$import_pass" ]]; then
    error "Faltan DB_MIRROR_PASSWORD o DB_MIRROR_IMPORT_PASSWORD en $ENV_FILE."
    error "Ejecuta ./dev-setup.sh, que las genera."
    exit 1
  fi

  info "Creando la red y los volúmenes compartidos (si faltan)..."
  docker network create library_mirror_net &>/dev/null \
    && success "Red library_mirror_net creada." \
    || info "Red library_mirror_net ya existía."
  for vol in library_mirror_data library_mirror_files library_covers_data; do
    docker volume create "$vol" &>/dev/null \
      && info "Volumen $vol listo." \
      || info "Volumen $vol ya existía."
  done

  info "Levantando el stack del mirror..."
  mirror_compose_cmd up -d

  info "Esperando a que el MySQL del mirror responda..."
  local intentos=0
  until docker exec "$MIRROR_CONTAINER" mysqladmin ping -h localhost --silent &>/dev/null; do
    intentos=$((intentos + 1))
    if [[ $intentos -gt 60 ]]; then
      error "El MySQL del mirror no arrancó en 120 s."
      mirror_compose_cmd logs --tail 30 mirror-mysql
      exit 1
    fi
    sleep 2
  done
  success "MySQL del mirror arriba."

  # El esquema ya lo aplica el entrypoint en un volumen virgen, pero esto cubre
  # el caso de un volumen que ya existía de una versión anterior. Es idempotente.
  info "Aplicando docker/database/mirror_schema.sql..."
  docker exec -i "$MIRROR_CONTAINER" mysql -uroot -p"$root_pass" \
    < "$ROOT_DIR/docker/database/mirror_schema.sql" 2>/dev/null \
    && success "Esquema library_mirror listo." \
    || { error "Falló la aplicación del esquema."; exit 1; }

  # Los usuarios NO van en mirror_schema.sql: llevan contraseña y ese fichero
  # está versionado. El ALTER USER mantiene las credenciales en sintonía con el
  # .env aunque el usuario ya existiera de una ejecución anterior.
  info "Creando los usuarios del mirror..."
  docker exec -i "$MIRROR_CONTAINER" mysql -uroot -p"$root_pass" 2>/dev/null <<SQL
-- El usuario con el que consultan el catálogo los backends de dev y de prod.
CREATE USER IF NOT EXISTS 'library_mirror_user'@'%' IDENTIFIED BY '${mirror_pass}';
ALTER USER 'library_mirror_user'@'%' IDENTIFIED BY '${mirror_pass}';
GRANT SELECT, INSERT, UPDATE, DELETE ON library_mirror.* TO 'library_mirror_user'@'%';

-- El importador. FILE es un privilegio GLOBAL (ON *.*) y no se puede acotar a
-- un esquema: deja leer cualquier fichero que el servidor pueda leer. Por eso
-- no lo tiene el usuario con el que la app web atiende peticiones — solo este,
-- que únicamente usa backend/bin/mirror.
CREATE USER IF NOT EXISTS 'library_mirror_importer'@'%' IDENTIFIED BY '${import_pass}';
ALTER USER 'library_mirror_importer'@'%' IDENTIFIED BY '${import_pass}';
GRANT ALL PRIVILEGES ON library_mirror.* TO 'library_mirror_importer'@'%';
GRANT FILE ON *.* TO 'library_mirror_importer'@'%';
FLUSH PRIVILEGES;
SQL
  success "Usuarios library_mirror_user y library_mirror_importer listos."

  echo ""
  success "=============================================="
  success " Mirror listo"
  success "=============================================="
  echo ""
  echo "  Servidor : $MIRROR_CONTAINER (host: 127.0.0.1:3313)"
  echo "  Red      : library_mirror_net (dev y prod se conectan por aquí)"
  echo ""
  local filas
  filas=$(mirror_sql "SELECT COUNT(*) FROM imdb_title;" 2>/dev/null | tail -1 | tr -d '\r')
  if [[ -z "$filas" || "$filas" == "0" ]]; then
    warn "El catálogo está VACÍO. Impórtalo:"
    echo "    ./mirror-sync.sh --imdb          (~13 min)"
    echo "    ./mirror-sync.sh --musicbrainz   (~26 min)"
  else
    echo "  imdb_title: ${filas} filas."
  fi
  echo ""
}

cmd_status() {
  check_running

  echo ""
  echo -e "${YELLOW}=== Última importación ===${NC}"
  mirror_sql "
    SELECT source, source_version AS dump, started_at, finished_at,
           rows_loaded, status
    FROM mirror_import
    ORDER BY id DESC LIMIT 5;"

  echo ""
  echo -e "${YELLOW}=== Contenido del mirror ===${NC}"
  # COUNT(*) real y no information_schema.table_rows: esa columna es una
  # estimación de InnoDB que aquí se queda a la mitad del valor verdadero.
  mirror_sql "
    SELECT 'imdb_title' AS tabla, COUNT(*) AS filas FROM imdb_title
    UNION ALL SELECT 'imdb_title_es', COUNT(*) FROM imdb_title_es
    UNION ALL SELECT 'imdb_episode',  COUNT(*) FROM imdb_episode
    UNION ALL SELECT 'tmdb_title',    COUNT(*) FROM tmdb_title
    UNION ALL SELECT 'mb_release_group', COUNT(*) FROM mb_release_group
    UNION ALL SELECT 'mb_track',         COUNT(*) FROM mb_track;"

  mirror_sql "
    SELECT table_name AS tabla,
           ROUND((data_length + index_length) / 1024 / 1024) AS mb
    FROM information_schema.tables
    WHERE table_schema = 'library_mirror'
    ORDER BY mb DESC;"

  echo ""
  echo -e "${YELLOW}=== Portadas locales ===${NC}"
  # 'muertas' son las que agotaron los 3 intentos: no las volverá a pedir nadie
  # y solo salen de ahí borrando la fila o cambiando su source_url.
  mirror_sql "
    SELECT COUNT(*) AS filas,
           COALESCE(SUM(storage_path IS NOT NULL), 0) AS bajadas,
           COALESCE(SUM(storage_path IS NULL AND attempts < 3), 0) AS pendientes,
           COALESCE(SUM(storage_path IS NULL AND attempts >= 3), 0) AS muertas,
           COALESCE(ROUND(SUM(bytes) / 1024 / 1024), 0) AS mb
    FROM cover_file;"

  echo ""
  echo -e "${YELLOW}=== Caché de TMDB (límite: 6 meses) ===${NC}"
  mirror_sql "
    SELECT COUNT(*) AS filas,
           SUM(cached_at < NOW() - INTERVAL ${PURGE_AFTER}) AS caducadas,
           MIN(cached_at) AS mas_antigua,
           MAX(cached_at) AS mas_reciente
    FROM tmdb_title;"
  echo ""
}

# ---------------------------------------------------------------------------
# --imdb
# ---------------------------------------------------------------------------
cmd_imdb() {
  check_running

  info "Reimportando los dumps de IMDb. Son ~760 MB de descarga: esto tarda."
  info "La búsqueda sigue respondiendo mientras tanto (swap por RENAME al final)."

  compose_cmd exec -T backend php bin/mirror import:imdb

  success "Mirror de IMDb al día."
}

# ---------------------------------------------------------------------------
# --musicbrainz
# ---------------------------------------------------------------------------
# Dos cosas que este modo hace y el CLI por sí solo no:
#
#   1. **Mira si hay dump nuevo antes de bajar 7,6 GB.** El importador también lo
#      comprueba, pero aquí se dice en voz alta antes de arrancar nada.
#   2. **Sube el buffer pool mientras dura, y lo baja al terminar.** Medido sobre
#      el dump real: con 4 GB el pase tarda ~26 min y con los 128 MB que MySQL
#      trae por defecto, 3,7 h. Es dinámico en MySQL 8, así que no hace falta
#      dedicarle 4 GB fijos a un contenedor de dev todo el año para una
#      importación que corre dos veces al año.
MB_POOL_IMPORT=4294967296   # 4 GB mientras importa

cmd_musicbrainz() {
  check_running

  local latest actual
  latest=$(curl -fsS --max-time 20 \
    https://data.metabrainz.org/pub/musicbrainz/data/fullexport/LATEST 2>/dev/null | tr -d '\r\n' || true)

  if [[ -z "$latest" ]]; then
    error "No se pudo leer el marcador LATEST de MusicBrainz. ¿Hay red?"
    exit 1
  fi

  actual=$(mirror_sql \
    "SELECT COALESCE(MAX(source_version), '') FROM mirror_import
      WHERE source = 'musicbrainz' AND status = 'ok';" | tail -1 | tr -d '\r')

  info "Dump publicado: ${latest}"
  info "Dump importado: ${actual:-(ninguno)}"

  if [[ -z "$FORCE" && "$actual" == "$latest" ]]; then
    success "El mirror de música ya está al día. Nada que descargar."
    exit 0
  fi

  # Se guarda el valor previo para devolverlo tal cual, en vez de asumir 128 MB:
  # si alguien lo tenía tuneado, no es cosa de este script cambiárselo.
  # SIN 'local', y no es un descuido: el trap de abajo se dispara en EXIT, o sea
  # DESPUÉS de que esta función haya retornado. Con una variable local, para
  # entonces ya no existe, la guarda de restaurar_pool da falso y el buffer pool
  # se queda en 4 GB en silencio. Con Ctrl-C sí funcionaba —ahí el trap salta
  # dentro de la función—, y por eso la prueba original dio un falso positivo.
  pool_previo=$(root_sql "SELECT @@innodb_buffer_pool_size;" | tail -1 | tr -d '\r')

  restaurar_pool() {
    # Se desarma en cuanto entra: con el trap puesto en EXIT, INT y TERM, una
    # señal lo dispara y la salida del shell lo dispararía otra vez. Restaurar
    # dos veces es inofensivo, pero el mensaje duplicado confunde.
    trap - EXIT INT TERM
    if [[ -n "${pool_previo:-}" ]]; then
      root_sql "SET GLOBAL innodb_buffer_pool_size = ${pool_previo};" >/dev/null || true
      info "Buffer pool devuelto a $(( pool_previo / 1024 / 1024 )) MB."
    fi
  }
  # EXIT y no solo el camino feliz: si la importación revienta o alguien hace
  # Ctrl-C, dejar 4 GB clavados en el MySQL de dev sería peor que el problema.
  trap restaurar_pool EXIT INT TERM

  if [[ -n "$pool_previo" && "$pool_previo" -lt "$MB_POOL_IMPORT" ]]; then
    info "Subiendo el buffer pool a $(( MB_POOL_IMPORT / 1024 / 1024 )) MB mientras dura la importación..."
    root_sql "SET GLOBAL innodb_buffer_pool_size = ${MB_POOL_IMPORT};" >/dev/null || \
      warn "No se pudo subir el buffer pool; la importación funcionará igual pero tardará mucho más."
  fi

  info "Importando MusicBrainz. Son 7,6 GB de descarga: esto tarda ~26 min."
  info "La búsqueda sigue respondiendo mientras tanto (swap por RENAME al final)."

  compose_cmd exec -T backend php bin/mirror import:musicbrainz $FORCE

  success "Mirror de música al día."
}

# ---------------------------------------------------------------------------
# --purge
# ---------------------------------------------------------------------------
cmd_purge() {
  check_running

  info "Borrando el enriquecimiento TMDB con más de ${PURGE_AFTER}..."

  local antes despues
  antes=$(mirror_sql "SELECT COUNT(*) FROM tmdb_title;" | tail -1)

  mirror_sql "DELETE FROM tmdb_title WHERE cached_at < NOW() - INTERVAL ${PURGE_AFTER};"

  despues=$(mirror_sql "SELECT COUNT(*) FROM tmdb_title;" | tail -1)

  success "Purgadas $(( antes - despues )) fila(s). Quedan ${despues}."
  info "Se repoblarán solas la próxima vez que se abra cada ficha."

  # -------------------------------------------------------------------------
  # Catálogo de Spotify en albums
  # -------------------------------------------------------------------------
  # Los términos de Spotify prohíben "store, aggregate or create compilations or
  # databases of Spotify Content". La inmensa mayoría de los álbumes vienen del
  # mirror de MusicBrainz (CC0) y no caducan, pero lo que entró por el fallback
  # sí, y esto le quita el enriquecimiento pasados ${PURGE_AFTER}.
  #
  # ANULA, no borra: user_albums referencia la fila y el álbum es dato del
  # usuario. Lo que se va es el catálogo ajeno —sello, popularidad, géneros,
  # UPC, duración—; el título y el artista se quedan, o la biblioteca mostraría
  # filas en blanco.
  echo ""
  info "Caducando el catálogo de Spotify con más de ${PURGE_AFTER}..."

  local caducables
  caducables=$(library_sql "
    SELECT COUNT(*) FROM albums
     WHERE catalog_source = 'spotify'
       AND catalog_cached_at < NOW() - INTERVAL ${PURGE_AFTER}
       AND (label IS NOT NULL OR popularity IS NOT NULL OR genres IS NOT NULL
            OR upc IS NOT NULL OR duration_ms IS NOT NULL);" | tail -1 | tr -d '\r')

  if [[ "${caducables:-0}" -eq 0 ]]; then
    info "Nada que caducar: ningún álbum de Spotify supera ${PURGE_AFTER}."
  else
    library_sql "
      UPDATE albums
         SET label = NULL, popularity = NULL, genres = NULL, upc = NULL,
             duration_ms = NULL, external_url = NULL
       WHERE catalog_source = 'spotify'
         AND catalog_cached_at < NOW() - INTERVAL ${PURGE_AFTER};"
    success "Enriquecimiento anulado en ${caducables} álbum(es) de Spotify."
    info "Las filas siguen en la biblioteca; solo se fue el catálogo ajeno."
  fi
}

# ---------------------------------------------------------------------------
# --tracks
# ---------------------------------------------------------------------------
# Los dumps traen el CONTEO de pistas, no la lista: esa se pide a la API por la
# release canónica. Normalmente la trae sola el primer acceso a la ficha, en
# diferido; esto recoge lo que se quedó atrás — lo guardado antes de que
# existiera mb_track, y los diferidos que no llegaron a correr.
cmd_tracks() {
  check_running

  info "Bajando de MusicBrainz las pistas que falten..."
  info "Va a 1 petición cada 2 s: la API es lenta y limita a 1/s."

  compose_cmd exec -T backend php bin/mirror tracks:backfill

  success "Pistas al día."
}

# ---------------------------------------------------------------------------
# --covers
# ---------------------------------------------------------------------------
# La red de seguridad del guardado: lo que se registra al añadir a biblioteca se
# baja tras la respuesta, pero si el proceso se cortó o el CDN estaba caído, la
# fila se queda pendiente y solo la recoge esto.
cmd_covers() {
  check_running

  # Purgar ANTES de bajar, por lo mismo que covers:backfill siembra antes de
  # descargar: así el backfill no gasta red en portadas de catálogo que están a
  # punto de caducar, y las filas quemadas —sin fichero y con los intentos
  # agotados— vuelven a estar disponibles para reintentarse limpias.
  info "Caducando la caché de portadas del catálogo..."
  compose_cmd exec -T backend php bin/mirror covers:purge

  info "Bajando las portadas pendientes..."
  compose_cmd exec -T backend php bin/mirror covers:backfill
  success "Backfill de portadas terminado."
}

# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------
case "$MODE" in
  status) cmd_status ;;
  bootstrap) cmd_bootstrap ;;
  imdb)   cmd_imdb   ;;
  musicbrainz) cmd_musicbrainz ;;
  purge)  cmd_purge  ;;
  tracks) cmd_tracks ;;
  covers) cmd_covers ;;
esac
