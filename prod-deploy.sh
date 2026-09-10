#!/usr/bin/env bash
# =============================================================================
# prod-deploy.sh — LibraryVue: despliegue en producción
# =============================================================================
# Uso:
#   ./prod-deploy.sh               → deploy completo (build + up)
#   ./prod-deploy.sh --rebuild     → fuerza rebuild de imágenes sin caché
#   ./prod-deploy.sh --stop        → detiene todos los contenedores de producción
#   ./prod-deploy.sh --logs        → muestra logs en tiempo real
#   ./prod-deploy.sh --status      → estado de contenedores y salud del servicio
#   ./prod-deploy.sh --mobile-build → compila APK/AAB de producción (Capacitor)
# =============================================================================

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="$ROOT_DIR/.env.prod"
BACKEND_ENV_FILE="$ROOT_DIR/backend/.env.docker-production"
COMPOSE_FILE="$ROOT_DIR/docker-compose.prod.yml"

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
MODE="deploy"
for arg in "$@"; do
  case "$arg" in
    --rebuild)      MODE="rebuild"      ;;
    --stop)         MODE="stop"         ;;
    --logs)         MODE="logs"         ;;
    --status)       MODE="status"       ;;
    --mobile-build) MODE="mobile-build" ;;
    --migrate)      MODE="migrate"      ;;
    --help|-h)
      echo "Uso: $0 [--rebuild|--stop|--logs|--status|--mobile-build|--migrate|--help]"
      echo ""
      echo "  (sin args)      Deploy completo (build si hay cambios + up)"
      echo "  --rebuild       Fuerza rebuild de todas las imágenes sin caché"
      echo "  --stop          Detiene todos los contenedores de producción"
      echo "  --logs          Logs en tiempo real de todos los servicios"
      echo "  --status        Estado de contenedores y salud del servicio"
      echo "  --mobile-build  Compila APK/AAB de producción y sincroniza Android"
      echo "  --migrate       Aplica migraciones de BD pendientes (sin resetear la BD)"
      exit 0
      ;;
  esac
done

# ---------------------------------------------------------------------------
# Verificar dependencias
# ---------------------------------------------------------------------------
check_deps() {
  local missing=()
  for cmd in docker curl openssl; do
    command -v "$cmd" &>/dev/null || missing+=("$cmd")
  done

  if ! docker compose version &>/dev/null 2>&1 && ! command -v docker-compose &>/dev/null; then
    missing+=("docker-compose")
  fi

  if [[ ${#missing[@]} -gt 0 ]]; then
    error "Faltan dependencias: ${missing[*]}"
    exit 1
  fi
}

compose_cmd() {
  if docker compose version &>/dev/null 2>&1; then
    docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
  else
    docker-compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
  fi
}

# ---------------------------------------------------------------------------
# Leer variable de un .env
# ---------------------------------------------------------------------------
env_get() {
  local file="$1" key="$2"
  grep -E "^${key}=" "$file" 2>/dev/null | head -1 | cut -d'=' -f2- || true
}

# ---------------------------------------------------------------------------
# SHA del commit del checkout
# ---------------------------------------------------------------------------
# Es el dato que sella la imagen del backend (LABEL
# org.opencontainers.image.revision) y el que la guarda de --migrate compara
# con el de la imagen desplegada.
#
# Si esto no es un repo git, `rev-parse` falla y sale `unknown`. Un checkout
# sucio NO cambia nada: HEAD sigue siendo el commit, y el estado del árbol de
# trabajo no se hornea en ninguna parte.
git_head_sha() {
  git -C "$ROOT_DIR" rev-parse HEAD 2>/dev/null || echo "unknown"
}

# ---------------------------------------------------------------------------
# Sello de versión de una imagen ya construida
# ---------------------------------------------------------------------------
# Lee el LABEL org.opencontainers.image.revision que el Dockerfile del backend
# hornea con el GIT_SHA del build. Lo comparten los dos sitios que preguntan
# "¿qué código lleva esta imagen?": la guarda de --migrate
# (check_image_revision) y la decisión de construir del deploy (build_needed).
#
# Dos detalles que deciden si sirve o no:
#   - `docker inspect` de una imagen inexistente sale != 0 y con `set -e`
#     mataría el script sin explicar nada: se captura con `|| true`.
#   - Un label ausente sale como cadena vacía o como `<no value>` según la
#     versión de Docker; las dos se normalizan a `unknown`, que NUNCA casa con
#     nada, ni con otro `unknown`: dos incógnitas no son una coincidencia.
image_revision() {
  local image="$1"
  local label="org.opencontainers.image.revision"
  local sha

  sha="$(docker inspect -f "{{ index .Config.Labels \"${label}\" }}" \
    "$image" 2>/dev/null || true)"
  [[ -z "$sha" || "$sha" == "<no value>" ]] && sha="unknown"

  printf '%s' "$sha"
}

ask() {
  local prompt="$1"
  local current="$2"
  local secret="${3:-no}"
  local value

  if [[ "$secret" == "yes" ]]; then
    read -rsp "  ${prompt} [${current:-(vacío)}]: " value
    echo
  else
    read -rp "  ${prompt} [${current:-(vacío)}]: " value
  fi

  echo "${value:-$current}"
}

# Solo pregunta si el valor está vacío.
ask_if_empty() {
  local prompt="$1"
  local current="$2"
  local secret="${3:-no}"

  if [[ -n "$current" ]]; then
    echo "$current"
    return
  fi

  ask "$prompt" "" "$secret"
}

# ---------------------------------------------------------------------------
# Crear / completar .env.prod (variables para docker-compose.prod.yml)
# ---------------------------------------------------------------------------
setup_prod_env() {
  local google_client_id spotify_client_id spotify_client_secret
  local lastfm_api_key youtube_api_key tmdb_api_key mysql_root_password mysql_password
  local google_books_api_key
  local mirror_password

  google_client_id=$(env_get      "$ENV_FILE" GOOGLE_CLIENT_ID)
  google_books_api_key=$(env_get  "$ENV_FILE" GOOGLE_BOOKS_API_KEY)
  spotify_client_id=$(env_get     "$ENV_FILE" SPOTIFY_CLIENT_ID)
  spotify_client_secret=$(env_get "$ENV_FILE" SPOTIFY_CLIENT_SECRET)
  lastfm_api_key=$(env_get        "$ENV_FILE" LASTFM_API_KEY)
  youtube_api_key=$(env_get       "$ENV_FILE" YOUTUBE_API_KEY)
  tmdb_api_key=$(env_get          "$ENV_FILE" TMDB_API_KEY)
  mysql_root_password=$(env_get   "$ENV_FILE" MYSQL_ROOT_PASSWORD)
  mysql_password=$(env_get        "$ENV_FILE" MYSQL_PASSWORD)
  mirror_password=$(env_get       "$ENV_FILE" DB_MIRROR_PASSWORD)

  local needs_input=false
  [[ -z "$google_client_id" || -z "$google_books_api_key" || -z "$spotify_client_id" || -z "$spotify_client_secret" \
     || -z "$lastfm_api_key" || -z "$youtube_api_key" || -z "$tmdb_api_key" \
     || -z "$mysql_root_password" || -z "$mysql_password" || -z "$mirror_password" ]] \
    && needs_input=true

  if [[ "$needs_input" == "true" ]]; then
    warn "Faltan claves en .env.prod — solo se pedirán las vacías."
    echo ""
    echo -e "${YELLOW}=== APIs externas (producción) ===${NC}"
    google_client_id=$(ask_if_empty      "Google OAuth Client ID"  "$google_client_id")
    google_books_api_key=$(ask_if_empty  "Google Books API Key"    "$google_books_api_key")
    spotify_client_id=$(ask_if_empty     "Spotify Client ID"       "$spotify_client_id")
    spotify_client_secret=$(ask_if_empty "Spotify Client Secret"   "$spotify_client_secret" "yes")
    lastfm_api_key=$(ask_if_empty        "Last.fm API Key"         "$lastfm_api_key")
    youtube_api_key=$(ask_if_empty       "YouTube Data API Key"    "$youtube_api_key")
    tmdb_api_key=$(ask_if_empty          "TMDB API Key (películas)" "$tmdb_api_key")

    echo ""
    echo -e "${YELLOW}=== Contraseñas MySQL (producción — usa valores seguros) ===${NC}"
    mysql_root_password=$(ask_if_empty "MySQL Root Password" "$mysql_root_password" "yes")
    mysql_password=$(ask_if_empty      "MySQL User Password" "$mysql_password"      "yes")

    echo ""
    echo -e "${YELLOW}=== Mirror de catálogos (servidor compartido con dev) ===${NC}"
    echo "  Tiene que ser la MISMA contraseña que en el .env de desarrollo:"
    echo "  es un único servidor MySQL, con un único usuario library_mirror_user."
    mirror_password=$(ask_if_empty "Mirror DB Password" "$mirror_password" "yes")
  else
    success "Todas las claves ya están en .env.prod — sin cambios."
    return
  fi

  cat > "$ENV_FILE" <<EOF
# Docker Compose Production Environment Variables
# Generado por prod-deploy.sh el $(date '+%Y-%m-%d %H:%M:%S')
# NUNCA commitear este archivo

# Google OAuth
GOOGLE_CLIENT_ID=${google_client_id}
GOOGLE_BOOKS_API_KEY=${google_books_api_key}

# Spotify API
SPOTIFY_CLIENT_ID=${spotify_client_id}
SPOTIFY_CLIENT_SECRET=${spotify_client_secret}

# Last.fm API
LASTFM_API_KEY=${lastfm_api_key}

# YouTube Data API v3
YOUTUBE_API_KEY=${youtube_api_key}

# TMDB (enriquecimiento de fichas de película/serie)
TMDB_API_KEY=${tmdb_api_key}

# Database
MYSQL_ROOT_PASSWORD=${mysql_root_password}
MYSQL_PASSWORD=${mysql_password}
DB_PASSWORD=${mysql_password}

# Mirror de catálogos — servidor compartido (docker-compose.mirror.yml)
DB_MIRROR_PASSWORD=${mirror_password}
EOF

  success ".env.prod creado/actualizado."
}

# ---------------------------------------------------------------------------
# Crear backend/.env.docker-production (si no existe)
# ---------------------------------------------------------------------------
# Helper: escribe o actualiza una clave en un .env file
env_set() {
  local file="$1" key="$2" value="$3"
  [[ -z "$value" ]] && return
  if grep -qE "^${key}=" "$file" 2>/dev/null; then
    sed -i "s|^${key}=.*|${key}=${value}|" "$file"
  else
    echo "${key}=${value}" >> "$file"
  fi
}

setup_backend_prod_env() {
  if [[ ! -f "$BACKEND_ENV_FILE" ]]; then
    info "Creando backend/.env.docker-production desde el ejemplo..."
    cp "$ROOT_DIR/backend/.env.docker-production.example" "$BACKEND_ENV_FILE"
    warn "Revisa $BACKEND_ENV_FILE antes de continuar (especialmente CORS_ALLOWED_ORIGINS y SESSION_*)."
  fi

  # Sincronizar TODAS las claves API desde .env raíz (en creación y actualización)
  env_set "$BACKEND_ENV_FILE" DB_PASSWORD           "$(env_get "$ENV_FILE" MYSQL_PASSWORD)"
  env_set "$BACKEND_ENV_FILE" MYSQL_PASSWORD        "$(env_get "$ENV_FILE" MYSQL_PASSWORD)"
  env_set "$BACKEND_ENV_FILE" GOOGLE_CLIENT_ID      "$(env_get "$ENV_FILE" GOOGLE_CLIENT_ID)"
  env_set "$BACKEND_ENV_FILE" GOOGLE_BOOKS_API_KEY  "$(env_get "$ENV_FILE" GOOGLE_BOOKS_API_KEY)"
  env_set "$BACKEND_ENV_FILE" SPOTIFY_CLIENT_ID     "$(env_get "$ENV_FILE" SPOTIFY_CLIENT_ID)"
  env_set "$BACKEND_ENV_FILE" SPOTIFY_CLIENT_SECRET "$(env_get "$ENV_FILE" SPOTIFY_CLIENT_SECRET)"
  env_set "$BACKEND_ENV_FILE" LASTFM_API_KEY        "$(env_get "$ENV_FILE" LASTFM_API_KEY)"
  env_set "$BACKEND_ENV_FILE" YOUTUBE_API_KEY       "$(env_get "$ENV_FILE" YOUTUBE_API_KEY)"
  env_set "$BACKEND_ENV_FILE" TMDB_API_KEY          "$(env_get "$ENV_FILE" TMDB_API_KEY)"
  # El mirror es un servidor aparte y compartido con dev: sin estas cuatro, el
  # conector cae a DB_HOST/library_user (constructor de DatabaseConnector) y
  # busca library_mirror en el MySQL de producción, donde no existe.
  env_set "$BACKEND_ENV_FILE" DB_MIRROR_HOST        "mirror-mysql"
  env_set "$BACKEND_ENV_FILE" DB_MIRROR_PORT        "3306"
  env_set "$BACKEND_ENV_FILE" DB_MIRROR_DATABASE    "library_mirror"
  env_set "$BACKEND_ENV_FILE" DB_MIRROR_USERNAME    "library_mirror_user"
  env_set "$BACKEND_ENV_FILE" DB_MIRROR_PASSWORD    "$(env_get "$ENV_FILE" DB_MIRROR_PASSWORD)"

  # Generar JWT_SECRET seguro automáticamente si no está configurado
  local current_jwt
  current_jwt=$(env_get "$BACKEND_ENV_FILE" JWT_SECRET)
  if [[ "$current_jwt" == *"CHANGE_TO"* ]] || [[ -z "$current_jwt" ]]; then
    local jwt_secret
    jwt_secret=$(openssl rand -base64 48 | tr -d '\n')
    sed -i "s|^JWT_SECRET=.*|JWT_SECRET=${jwt_secret}|" "$BACKEND_ENV_FILE"
    success "JWT_SECRET generado automáticamente con openssl."
  fi

  success "backend/.env.docker-production creado."
  warn "Revisa $BACKEND_ENV_FILE antes de continuar (especialmente CORS_ALLOWED_ORIGINS y SESSION_*)."
  echo ""
  read -rp "  ¿Continuar con el deploy? (s/N): " cont
  [[ "$cont" =~ ^[sS]$ ]] || { info "Cancelado. Edita el archivo y vuelve a ejecutar."; exit 0; }
}

# ---------------------------------------------------------------------------
# Build y despliegue
# ---------------------------------------------------------------------------
# ---------------------------------------------------------------------------
# El mirror de catálogos tiene que estar arriba ANTES que producción
# ---------------------------------------------------------------------------
# No es una dependencia blanda. La búsqueda de películas no sale a la red por
# diseño (FallbackMovieCatalog::search) y MySqlMovieCatalog no captura la
# PDOException: sin mirror, search_movies_omdb responde 500. Es mejor negarse a
# desplegar que publicar una app con el catálogo roto.
#
# Vive en su propio stack porque lo comparten dev y prod:
#   docker compose -f docker-compose.mirror.yml up -d
# ---------------------------------------------------------------------------
check_mirror() {
  info "Comprobando el mirror de catálogos..."

  if ! docker network inspect library_mirror_net &>/dev/null; then
    error "No existe la red library_mirror_net."
    error "Arranca el mirror primero:  ./mirror-sync.sh --bootstrap"
    exit 1
  fi

  if ! docker ps --format '{{.Names}}' | grep -qx 'libraryvue-mirror-mysql'; then
    error "El contenedor libraryvue-mirror-mysql no está corriendo."
    error "Arráncalo con:  docker compose -f docker-compose.mirror.yml up -d"
    exit 1
  fi

  local mirror_pass
  mirror_pass="$(env_get "$ENV_FILE" DB_MIRROR_PASSWORD)"

  # Se comprueba con las credenciales REALES de producción, no con root: lo que
  # importa no es que el servidor esté vivo, sino que el usuario que usará el
  # backend pueda leer el catálogo.
  local rows
  rows=$(docker exec libraryvue-mirror-mysql mysql \
           -ulibrary_mirror_user -p"$mirror_pass" library_mirror \
           -N -B -e "SELECT COUNT(*) FROM imdb_title;" 2>/dev/null | tail -1 | tr -d '\r')

  if [[ -z "$rows" ]]; then
    error "library_mirror_user no puede leer library_mirror en el mirror."
    error "Revisa DB_MIRROR_PASSWORD en $ENV_FILE: tiene que ser la MISMA que en dev."
    exit 1
  fi

  if [[ "$rows" -eq 0 ]]; then
    error "El mirror está vacío (imdb_title: 0 filas)."
    error "Impórtalo desde el checkout de desarrollo:  ./mirror-sync.sh --imdb"
    exit 1
  fi

  success "Mirror accesible — imdb_title: ${rows} filas."
}

# ---------------------------------------------------------------------------
# Migraciones pendientes: avisar, no aplicar
# ---------------------------------------------------------------------------
# `--deploy` no las aplica (es --migrate, aparte y a mano, por diseño), y un
# esquema desfasado no falla al arrancar: falla más tarde y en una consulta
# concreta. Ej.: sin 20260819_090000_users_is_admin, las dos rutas de LibraryX
# revientan al leer users.is_admin; sin las de albums, AddAlbumUseCase no puede
# escribir catalog_source. Mejor decirlo antes que descubrirlo en un log.
# ---------------------------------------------------------------------------
warn_pending_migrations() {
  local db_pass aplicadas pendientes=()
  db_pass="$(env_get "$ENV_FILE" MYSQL_PASSWORD)"

  aplicadas=$(compose_cmd exec -T mysql mysql -ulibrary_user -p"$db_pass" library_db_prod \
      -N -B -e "SELECT filename FROM schema_migrations;" 2>/dev/null | tr -d '\r')

  # Sin tabla schema_migrations (base recién creada) no hay nada que comparar:
  # run_migrations.sh la crea él mismo en su primera pasada.
  [[ -z "$aplicadas" ]] && return 0

  local f
  for f in "$ROOT_DIR"/docker/database/migrations/*.sql; do
    [[ -e "$f" ]] || continue
    grep -qxF "$(basename "$f")" <<< "$aplicadas" || pendientes+=("$(basename "$f")")
  done

  if [[ ${#pendientes[@]} -gt 0 ]]; then
    echo ""
    warn "Hay ${#pendientes[@]} migración(es) sin aplicar en producción:"
    printf '         - %s\n' "${pendientes[@]}"
    warn "Aplícalas tras el deploy con:  ./prod-deploy.sh --migrate"
    echo ""
  fi
}

# ---------------------------------------------------------------------------
# ¿Hay que construir las imágenes?
# ---------------------------------------------------------------------------
# En producción el código va HORNEADO en la imagen: docker-compose.prod.yml
# monta solo volúmenes persistentes, no el árbol de fuentes. Un `up -d` sin
# build no despliega código nuevo de ninguna manera, así que decidir por la
# mera existencia de las imágenes —lo que se hacía hasta hoy— dejaba pasar
# despliegues que no desplegaban nada y encima decían que todo estaba bien.
#
# Se decide comparando el sello de la imagen del backend con el HEAD del
# checkout, el mismo dato que la guarda de --migrate. Construye en los cuatro
# casos que importan —imagen ausente, imagen sin label, label `unknown`, label
# distinto del HEAD— y salta solo cuando coinciden, diciendo qué SHA da por
# bueno.
#
# El frontend NO lleva sello: el M3 etiqueta solo el backend, que es el que
# habla con el esquema. Pero su ausencia sigue siendo motivo para construir
# —sin imagen no hay `up -d` que valga—, y si está presente basta con el sello
# del backend: las dos se construyen en la misma pasada de `compose_cmd build`,
# así que un backend al día implica un frontend de esa misma pasada.
#
# Los nombres de imagen van en variables locales para poder ejercer la función
# contra imágenes de usar y tirar sin rozar los tags vivos de producción. No
# son parámetros del script: aquí se quedan con los valores de producción.
#
# Devuelve 0 = hay que construir, 1 = no hay nada que construir.
build_needed() {
  local backend_image="libraryvue_prod-backend:latest"
  local frontend_image="libraryvue_prod-frontend:latest"

  local head_sha image_sha
  head_sha="$(git_head_sha)"
  image_sha="$(image_revision "$backend_image")"

  if ! docker image inspect "$frontend_image" &>/dev/null; then
    info "Falta la imagen ${frontend_image} — construyendo."
    return 0
  fi

  if [[ "$image_sha" == "unknown" ]]; then
    info "La imagen ${backend_image} no existe o se construyó sin sello de versión — construyendo."
    return 0
  fi

  if [[ "$head_sha" == "unknown" ]]; then
    info "Este directorio no es un repositorio git: sin HEAD con el que comparar — construyendo."
    return 0
  fi

  if [[ "$image_sha" != "$head_sha" ]]; then
    info "La imagen ${backend_image} lleva ${image_sha} y el checkout está en ${head_sha} — construyendo."
    return 0
  fi

  info "Imágenes Docker al día en ${head_sha} — saltando build. Usa --rebuild para reconstruir."
  return 1
}

deploy_services() {
  local no_cache="${1:-no}"
  cd "$ROOT_DIR"

  # El SHA viaja al build como build-arg (`backend.build.args` en
  # docker-compose.prod.yml) y acaba de LABEL en la imagen del backend. Se
  # exporta ANTES de las dos ramas de abajo porque las dos construyen, y una
  # imagen sin sellar es una imagen que --migrate rechazará después.
  export GIT_SHA
  GIT_SHA="$(git_head_sha)"
  info "Sello de versión de la imagen del backend: GIT_SHA=${GIT_SHA}"

  if [[ "$no_cache" == "yes" ]]; then
    info "Rebuilding imágenes sin caché..."
    compose_cmd build --no-cache
  else
    if build_needed; then
      info "Construyendo imágenes Docker..."
      compose_cmd build
    fi
  fi

  info "Arrancando servicios de producción..."
  compose_cmd up -d

  # Esperar MySQL
  #
  # Se consulta la base SEMBRADA por TCP, no `mysqladmin ping -h localhost`.
  # Sobre un volumen virgen el entrypoint de la imagen levanta un servidor
  # TEMPORAL con `port: 0` (solo socket) para correr init.prod.sql, y lo para al
  # acabar: el ping por socket responde contra ESE, así que daría «MySQL listo»
  # varios segundos antes de que exista la base. Con `--protocol=TCP` no hay
  # falso positivo posible —el temporal no escucha en el puerto— y el SELECT
  # prueba además que init.prod.sql terminó y creó el usuario.
  #
  # Hoy en producción eso no rompe nada: prod NUNCA hace `down -v`, así que
  # init.prod.sql solo corre en la primera instalación, y nada consulta la base
  # en esa ventana —warn_pending_migrations va ANTES de deploy_services, y
  # detrás de esta espera solo quedan el curl a Nginx y el banner—. Se endurece
  # por simetría con dev-setup.sh:311 y porque ahí el mismo fallo SÍ mordió en
  # cuanto algo empezó a correr en esa ventana: llevaba años invisible.
  # $MYSQL_DATABASE lo resuelve el propio contenedor (library_db_prod), así que
  # el nombre no se repite aquí.
  info "Esperando a que MySQL esté disponible..."
  local retries=40
  until compose_cmd exec -T mysql sh -c \
      'mysql -h 127.0.0.1 --protocol=TCP -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT 1"' \
      >/dev/null 2>&1; do
    retries=$((retries - 1))
    if [[ $retries -le 0 ]]; then
      error "MySQL no respondió a tiempo. Revisa los logs: ./prod-deploy.sh --logs"
      exit 1
    fi
    sleep 3
  done
  success "MySQL listo."

  # Verificar backend (healthcheck vía nginx → backend)
  info "Verificando que el servicio responde..."
  retries=20
  until curl -sf http://127.0.0.1:8082/ -o /dev/null 2>/dev/null; do
    retries=$((retries - 1))
    if [[ $retries -le 0 ]]; then
      warn "El frontend no respondió en el tiempo esperado. Puede que aún esté iniciando."
      break
    fi
    sleep 3
  done

  echo ""
  success "=============================================="
  success " LibraryVue está corriendo en PRODUCCIÓN"
  success "=============================================="
  echo ""
  echo -e "  ${GREEN}Aplicación:${NC} http://localhost:8082"
  echo -e "  ${GREEN}MySQL:${NC}      localhost:3307  (user: library_user)"
  echo ""
  echo -e "  Logs:    ${BLUE}./prod-deploy.sh --logs${NC}"
  echo -e "  Estado:  ${BLUE}./prod-deploy.sh --status${NC}"
  echo -e "  Parar:   ${BLUE}./prod-deploy.sh --stop${NC}"
  echo ""
}

# ---------------------------------------------------------------------------
# Móvil producción
# ---------------------------------------------------------------------------
MOBILE_PROD_ENV="$ROOT_DIR/frontend/.env.production"
SECRETS_XML="$ROOT_DIR/frontend/android/app/src/main/res/values/secrets.xml"
PROD_API_URL="https://library.dcahomelab.com/api"

check_deps_mobile() {
  local missing=()

  export NVM_DIR="${NVM_DIR:-$HOME/.nvm}"
  # shellcheck source=/dev/null
  [[ -s "$NVM_DIR/nvm.sh" ]] && . "$NVM_DIR/nvm.sh"

  command -v node &>/dev/null || missing+=("node/nvm")
  command -v npm  &>/dev/null || missing+=("npm")

  ANDROID_HOME="${ANDROID_HOME:-$HOME/Android/Sdk}"
  [[ ! -d "$ANDROID_HOME" ]] && missing+=("Android SDK (esperado en $ANDROID_HOME)")

  if ! command -v java &>/dev/null; then
    missing+=("java")
  else
    local java_ver
    java_ver=$(java -version 2>&1 | head -1 | grep -oP '"\K[0-9]+')
    [[ "$java_ver" -lt 17 ]] && warn "Java $java_ver detectado. Se recomienda Java 17."
  fi

  if [[ ${#missing[@]} -gt 0 ]]; then
    error "Faltan dependencias para móvil: ${missing[*]}"
    exit 1
  fi
}

setup_mobile_prod_env() {
  local google_client_id
  google_client_id=$(env_get "$MOBILE_PROD_ENV" VUE_APP_GOOGLE_CLIENT_ID)

  # Fallbacks desde .env.prod
  [[ -z "$google_client_id" ]] && google_client_id=$(env_get "$ENV_FILE" GOOGLE_CLIENT_ID)

  local needs_input=false
  [[ -z "$google_client_id" ]] && needs_input=true

  if [[ "$needs_input" == "true" ]]; then
    info "Faltan claves en frontend/.env.production — solo se pedirán las vacías."
    echo ""
    echo -e "${YELLOW}=== App móvil producción ===${NC}"
    google_client_id=$(ask_if_empty "Google OAuth Client ID" "$google_client_id")

    cat > "$MOBILE_PROD_ENV" <<EOF
# Frontend production env — usado por npm run build:mobile:prod
# Generado por prod-deploy.sh el $(date '+%Y-%m-%d %H:%M:%S')
# NUNCA commitear este archivo

VUE_APP_API_URL=${PROD_API_URL}
VUE_APP_MODE=mobile
VUE_APP_GOOGLE_CLIENT_ID=${google_client_id}
EOF
    success "frontend/.env.production creado/actualizado."

    # Añadir a .gitignore si no está
    local gitignore="$ROOT_DIR/.gitignore"
    if [[ -f "$gitignore" ]] && ! grep -q '\.env\.production' "$gitignore"; then
      printf '\n# Frontend production env (claves API)\nfrontend/.env.production\n' >> "$gitignore"
      success ".env.production añadido a .gitignore."
    fi
  else
    success "frontend/.env.production ya tiene todas las claves — sin cambios."
  fi

  # secrets.xml — mismo que en dev, común para ambos entornos
  if [[ ! -f "$SECRETS_XML" ]]; then
    local client_id
    client_id=$(env_get "$MOBILE_PROD_ENV" VUE_APP_GOOGLE_CLIENT_ID)
    info "Creando android/app/.../secrets.xml..."
    mkdir -p "$(dirname "$SECRETS_XML")"
    cat > "$SECRETS_XML" <<EOF
<?xml version="1.0" encoding="utf-8"?>
<resources>
    <!-- Google OAuth client ID (web application type) -->
    <string name="server_client_id">${client_id}</string>
</resources>
EOF
    success "secrets.xml creado."

    local gitignore="$ROOT_DIR/.gitignore"
    if [[ -f "$gitignore" ]] && ! grep -q 'secrets.xml' "$gitignore"; then
      printf '\n# Android secrets (OAuth client ID)\nfrontend/android/app/src/main/res/values/secrets.xml\n' >> "$gitignore"
      success "secrets.xml añadido a .gitignore."
    fi
  else
    success "secrets.xml ya existe — sin cambios."
  fi
}

cmd_mobile_build() {
  check_deps_mobile
  setup_mobile_prod_env

  export NVM_DIR="${NVM_DIR:-$HOME/.nvm}"
  # shellcheck source=/dev/null
  [[ -s "$NVM_DIR/nvm.sh" ]] && . "$NVM_DIR/nvm.sh"

  cd "$ROOT_DIR/frontend"

  info "Instalando dependencias npm..."
  npm install --legacy-peer-deps --silent

  info "Compilando app móvil de producción (npm run build:mobile:prod)..."
  npm run build:mobile:prod
  success "Build completado."

  info "Sincronizando con proyecto Android (CAP_ENV=production cap sync)..."
  CAP_ENV=production npx cap sync android
  success "Sync completado."

  echo ""
  success "============================================"
  success " APK producción listo para compilar"
  success "============================================"
  echo -e "  URL backend: ${GREEN}${PROD_API_URL}${NC}"
  echo -e "  androidScheme: ${GREEN}https${NC} (Capacitor producción)"
  echo ""
  echo -e "  En Android Studio:"
  echo -e "    Build → Generate Signed App Bundle / APK"
  echo ""
  read -rp "  ¿Abrir Android Studio ahora? (s/N): " open_studio
  if [[ "$open_studio" =~ ^[sS]$ ]]; then
    info "Abriendo Android Studio..."
    npx cap open android
  else
    echo -e "  Para abrir más tarde: ${BLUE}cd frontend && npx cap open android${NC}"
  fi
  echo ""
}

# ---------------------------------------------------------------------------
# Flujos principales
# ---------------------------------------------------------------------------
cmd_stop() {
  cd "$ROOT_DIR"
  info "Deteniendo contenedores de producción..."
  compose_cmd down
  success "Contenedores detenidos."
}

cmd_logs() {
  cd "$ROOT_DIR"
  compose_cmd logs -f
}

cmd_status() {
  cd "$ROOT_DIR"
  echo ""
  echo -e "${YELLOW}=== Contenedores ===${NC}"
  compose_cmd ps
  echo ""
  echo -e "${YELLOW}=== Salud del servicio ===${NC}"
  if curl -sf http://127.0.0.1:8082/ -o /dev/null 2>/dev/null; then
    success "Frontend responde en http://localhost:8082"
  else
    error "Frontend NO responde en http://localhost:8082"
  fi
  if compose_cmd exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; then
    success "MySQL responde correctamente"
  else
    error "MySQL NO responde"
  fi
  echo ""
}

cmd_deploy() {
  check_deps

  if [[ ! -f "$ENV_FILE" ]]; then
    warn "No se encontró .env.prod. Se pedirán todas las claves."
  fi
  setup_prod_env
  setup_backend_prod_env
  check_mirror
  warn_pending_migrations
  deploy_services "no"
}

cmd_rebuild() {
  check_deps

  warn "REBUILD: se reconstruirán todas las imágenes sin caché."
  read -rp "¿Continuar? (s/N): " confirm
  [[ "$confirm" =~ ^[sS]$ ]] || { info "Cancelado."; exit 0; }

  setup_prod_env
  setup_backend_prod_env
  check_mirror
  warn_pending_migrations
  deploy_services "yes"
}

# ---------------------------------------------------------------------------
# Copia de seguridad antes de migrar
# ---------------------------------------------------------------------------
# El DDL de MySQL no es transaccional: una migración a medias no se deshace
# sola y el proyecto descarta el rollback por diseño (migrations/README.md).
# La red es este dump, así que si no sale bien --migrate ABORTA: mejor no
# migrar que migrar sin copia.
#
# Solo cuelga de --migrate. Desplegar no toca datos.
#
# La trampa está en la tubería: `mysqldump | gzip > f` devuelve el código de
# gzip, que sale 0 aunque mysqldump haya escupido un error y cero bytes, y el
# .gz de 20 bytes pasaría por copia buena. Hoy `set -euo pipefail` (:14) lo
# taparía, pero basta que alguien quite el pipefail para que la red desaparezca
# en silencio: se mira ${PIPESTATUS[0]} explícito y además el tamaño. El array
# se copia ENTERO y de una vez porque cualquier comando posterior —una
# asignación incluida— lo reescribe.
# ---------------------------------------------------------------------------
backup_prod_db() {
  local service="mysql"
  local db_user="library_user"
  # Igual que en cmd_migrate(): el nombre de la BD no está en .env.prod, está
  # hardcodeado en docker-compose.prod.yml, así que aquí va literal.
  local db_name="library_db_prod"
  local backup_dir="$ROOT_DIR/docker/database/backups"
  local keep=5
  local min_bytes=1024

  local db_pass
  db_pass="$(env_get "$ENV_FILE" MYSQL_PASSWORD)"

  mkdir -p "$backup_dir"
  local dump="$backup_dir/${db_name}_$(date +%Y%m%d_%H%M%S).sql.gz"

  info "Copia de seguridad de ${db_name} → ${dump#"$ROOT_DIR"/}"

  # --single-transaction: todo es InnoDB, así que el dump sale consistente sin
  #   bloquear la app mientras dura.
  # --no-tablespaces: library_user tiene GRANT ALL ON library_db_prod.* pero NO
  #   PROCESS, que es un privilegio GLOBAL y es lo que mysqldump pide para los
  #   tablespaces. Sin la bandera falla con el error 1227.
  # La extensión .sql.gz tampoco es cosmética: .gitignore cubre *.sql.gz, pero
  # *.sql a secas no, y ahí dentro hay datos reales.
  local -a st=()
  set +e
  compose_cmd exec -T "$service" mysqldump \
    -u"$db_user" -p"$db_pass" \
    --single-transaction --no-tablespaces --routines --events \
    "$db_name" | gzip > "$dump"
  st=("${PIPESTATUS[@]}")
  set -e

  if [[ "${st[0]}" -ne 0 ]]; then
    rm -f "$dump"
    error "mysqldump falló (código ${st[0]}); el error de MySQL está arriba."
    error "No se migra sin copia de seguridad."
    exit 1
  fi

  if [[ "${st[1]}" -ne 0 ]]; then
    rm -f "$dump"
    error "gzip falló (código ${st[1]}). No se migra sin copia de seguridad."
    exit 1
  fi

  # Umbral de cordura: un dump vacío o cortado a media escritura comprime a
  # ~20 bytes. Cualquier base real pasa de 1 KB de sobra.
  local size
  size=$(stat -c%s "$dump")
  if [[ "$size" -lt "$min_bytes" ]]; then
    rm -f "$dump"
    error "La copia salió de ${size} bytes (< ${min_bytes}): no es un dump válido."
    error "No se migra sin copia de seguridad."
    exit 1
  fi

  success "Copia de seguridad hecha — ${dump#"$ROOT_DIR"/} ($(du -h "$dump" | cut -f1))."

  # Rotación: se conservan las $keep más recientes y se borra el resto.
  ls -1t "$backup_dir"/*.sql.gz | tail -n "+$((keep + 1))" | xargs -r rm --
}

# ---------------------------------------------------------------------------
# Guarda de versión: el código desplegado tiene que ser el de este checkout
# ---------------------------------------------------------------------------
# Migrar antes de desplegar rompe producción: el esquema avanza y el backend
# que está corriendo sigue pidiendo lo que la migración acaba de borrar
# (20260910_120000_drop_consumption_dates.sql contra los 16 ficheros que nombran
# las columnas de consumo). Nada impedía ese orden hasta hoy.
#
# La imagen del backend lleva horneado el SHA con el que se construyó
# (Dockerfile.backend.prod, al final; lo rellena deploy_services). Aquí se
# compara con el HEAD del checkout, y si no casan se aborta.
#
# NO hay bandera para saltársela: la guarda existe justamente para el día en que
# uno tenga prisa.
#
# Tres detalles que deciden si funciona:
#   - Va ANTES de backup_prod_db: un --migrate prematuro no debe dejar ni dump.
#   - La lectura del label la hace image_revision(), compartida con
#     build_needed(): imagen inexistente y label ausente salen los dos como
#     `unknown` sin matar el script.
#   - `unknown` NUNCA casa, ni con otro `unknown`: dos incógnitas no son una
#     coincidencia.
#
# El nombre de la imagen va en una variable local para poder ejercer la función
# contra una imagen de ensayo sin tocar el tag vivo de producción. No es un
# parámetro del script: aquí se queda con el valor de producción.
# ---------------------------------------------------------------------------
check_image_revision() {
  local image="libraryvue_prod-backend:latest"

  local head_sha image_sha
  head_sha="$(git_head_sha)"

  image_sha="$(image_revision "$image")"

  if [[ "$image_sha" != "unknown" && "$head_sha" != "unknown" \
        && "$image_sha" == "$head_sha" ]]; then
    success "Código desplegado al día — imagen y checkout en ${head_sha}."
    return 0
  fi

  error "El código desplegado NO corresponde a este checkout."
  error "  la imagen ${image} lleva el SHA: ${image_sha}"
  error "  el HEAD de ${ROOT_DIR} es: ${head_sha}"
  if [[ "$image_sha" == "unknown" ]]; then
    error "  (la imagen no existe o se construyó sin sello de versión)"
  fi
  if [[ "$head_sha" == "unknown" ]]; then
    error "  (este directorio no es un repositorio git)"
  fi
  error "Primero el código, después el esquema: despliega con"
  error "  ./prod-deploy.sh --rebuild"
  error "y vuelve a lanzar --migrate. No se ha tocado la base ni se ha hecho copia."
  exit 1
}

cmd_migrate() {
  check_deps
  check_image_revision
  backup_prod_db
  info "Aplicando migraciones de base de datos pendientes..."
  # El nombre de la BD está hardcodeado en docker-compose.prod.yml, no en
  # .env.prod: sin este DB_NAME, run_migrations.sh cae a su default library_db,
  # que en producción no existe, y aborta sin mensaje (mysql_exec traga stderr).
  DB_NAME=library_db_prod \
  "$ROOT_DIR/docker/database/run_migrations.sh" \
    --env-file "$ENV_FILE" \
    --compose-file "$COMPOSE_FILE"
}

# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------
case "$MODE" in
  deploy)        cmd_deploy        ;;
  rebuild)       cmd_rebuild       ;;
  stop)          cmd_stop          ;;
  logs)          cmd_logs          ;;
  status)        cmd_status        ;;
  mobile-build)  cmd_mobile_build  ;;
  migrate)       cmd_migrate       ;;
esac
