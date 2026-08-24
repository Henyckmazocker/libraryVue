#!/usr/bin/env bash
# =============================================================================
# dev-setup.sh — LibraryVue: setup y arranque del entorno de desarrollo
# =============================================================================
# Uso:
#   ./dev-setup.sh          → setup interactivo (pide claves API si faltan)
#   ./dev-setup.sh --reset  → recrea contenedores y volúmenes desde cero
#   ./dev-setup.sh --stop   → detiene todos los contenedores
#   ./dev-setup.sh --logs   → muestra logs en tiempo real
#   ./dev-setup.sh --mobile → prepara y compila la app Android (Capacitor)
# =============================================================================

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="$ROOT_DIR/.env"
BACKEND_ENV_FILE="$ROOT_DIR/backend/.env.docker-development"

# Usuario que importa los dumps al mirror. Va aparte de library_user porque
# LOAD DATA INFILE exige el privilegio FILE, que solo existe global (ON *.*):
# dárselo al usuario de la app web ampliaría lo que podría hacer una inyección
# SQL en cualquier endpoint.
MIRROR_IMPORT_USER="library_mirror_importer"
MIRROR_IMPORT_DEFAULT_PASSWORD="mirror_import_dev"
# Usuario con el que los backends (dev Y prod) consultan el catálogo. Uno propio
# porque un servidor MySQL tiene una sola contraseña por usuario y la de
# library_user difiere entre los dos entornos.
MIRROR_DEFAULT_PASSWORD="mirror_dev"

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
# Helpers de argumentos
# ---------------------------------------------------------------------------
MODE="start"
for arg in "$@"; do
  case "$arg" in
    --reset)   MODE="reset"   ;;
    --stop)    MODE="stop"    ;;
    --logs)    MODE="logs"    ;;
    --mobile)  MODE="mobile"  ;;
    --migrate) MODE="migrate" ;;
    --help|-h)
      echo "Uso: $0 [--reset|--stop|--logs|--mobile|--migrate|--help]"
      echo ""
      echo "  (sin args)  Setup interactivo + arranque web (Docker)"
      echo "  --reset     Recrea contenedores y volúmenes desde cero"
      echo "  --stop      Detiene todos los contenedores"
      echo "  --logs      Logs en tiempo real de todos los servicios"
      echo "  --mobile    Prepara .env.mobile, secrets.xml, compila APK de debug"
      echo "  --migrate   Aplica migraciones de BD pendientes (sin resetear la BD)"
      exit 0
      ;;
  esac
done

# ---------------------------------------------------------------------------
# Verificar dependencias
# ---------------------------------------------------------------------------
check_deps() {
  local missing=()
  for cmd in docker curl; do
    command -v "$cmd" &>/dev/null || missing+=("$cmd")
  done

  # docker compose (plugin) o docker-compose (standalone)
  if ! docker compose version &>/dev/null 2>&1 && ! command -v docker-compose &>/dev/null; then
    missing+=("docker-compose")
  fi

  if [[ ${#missing[@]} -gt 0 ]]; then
    error "Faltan dependencias: ${missing[*]}"
    error "Instálalas antes de continuar."
    exit 1
  fi
}

compose_cmd() {
  if docker compose version &>/dev/null 2>&1; then
    docker compose "$@"
  else
    docker-compose "$@"
  fi
}

# ---------------------------------------------------------------------------
# Leer una variable de un .env existente (por si ya está configurado)
# ---------------------------------------------------------------------------
env_get() {
  local file="$1" key="$2"
  grep -E "^${key}=" "$file" 2>/dev/null | head -1 | cut -d'=' -f2- || true
}

# ---------------------------------------------------------------------------
# Pedir valor al usuario con fallback al valor actual
# ---------------------------------------------------------------------------
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

# Solo pregunta si el valor está vacío; si ya tiene valor, lo reutiliza en silencio.
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
# Crear / actualizar .env raíz (para docker-compose.yml)
# ---------------------------------------------------------------------------
setup_root_env() {
  # Leer valores actuales si el archivo ya existe
  local google_client_id google_books_api_key spotify_client_id spotify_client_secret
  local lastfm_api_key youtube_api_key mysql_root_password mysql_password mirror_import_password
  local mirror_password tmdb_api_key

  google_client_id=$(env_get "$ENV_FILE"      GOOGLE_CLIENT_ID)
  google_books_api_key=$(env_get "$ENV_FILE"  GOOGLE_BOOKS_API_KEY)
  spotify_client_id=$(env_get "$ENV_FILE"     SPOTIFY_CLIENT_ID)
  spotify_client_secret=$(env_get "$ENV_FILE" SPOTIFY_CLIENT_SECRET)
  lastfm_api_key=$(env_get "$ENV_FILE"        LASTFM_API_KEY)
  youtube_api_key=$(env_get "$ENV_FILE"       YOUTUBE_API_KEY)
  tmdb_api_key=$(env_get "$ENV_FILE"          TMDB_API_KEY)
  mysql_root_password=$(env_get "$ENV_FILE"   MYSQL_ROOT_PASSWORD)
  mysql_password=$(env_get "$ENV_FILE"        MYSQL_PASSWORD)
  # No se pregunta: es una credencial interna del importador del mirror, que no
  # sale de este docker-compose. Ver bootstrap_mirror_db().
  mirror_import_password=$(env_get "$ENV_FILE" DB_MIRROR_IMPORT_PASSWORD)
  mirror_import_password="${mirror_import_password:-$MIRROR_IMPORT_DEFAULT_PASSWORD}"
  # Tampoco se pregunta, por lo mismo. Pero OJO: esta sí la comparte producción
  # —es un único servidor MySQL con un único library_mirror_user—, así que si la
  # cambias aquí hay que cambiarla en el .env.prod de libraryVue_prod.
  mirror_password=$(env_get "$ENV_FILE" DB_MIRROR_PASSWORD)
  mirror_password="${mirror_password:-$MIRROR_DEFAULT_PASSWORD}"

  # Detectar si falta alguna clave antes de mostrar el bloque interactivo
  local needs_input=false
  # DB_MIRROR_* entra en la condición aunque nunca se pregunte: si el .env es de
  # antes del mirror compartido, sin esto el `return` de abajo lo dejaría sin las
  # claves nuevas y el backend buscaría library_mirror en el MySQL de la app.
  [[ -z "$google_client_id" || -z "$google_books_api_key" || -z "$spotify_client_id" \
     || -z "$spotify_client_secret" || -z "$lastfm_api_key" || -z "$youtube_api_key" \
     || -z "$tmdb_api_key" || -z "$mysql_root_password" || -z "$mysql_password" \
     || -z "$(env_get "$ENV_FILE" DB_MIRROR_PASSWORD)" ]] && needs_input=true

  if [[ "$needs_input" == "true" ]]; then
    info "Faltan claves en .env — solo se pedirán las vacías."
    echo ""
    echo -e "${YELLOW}=== APIs externas ===${NC}"
    google_client_id=$(ask_if_empty    "Google OAuth Client ID" "$google_client_id")
    google_books_api_key=$(ask_if_empty "Google Books API Key"  "$google_books_api_key")
    spotify_client_id=$(ask_if_empty   "Spotify Client ID"      "$spotify_client_id")
    spotify_client_secret=$(ask_if_empty "Spotify Client Secret" "$spotify_client_secret" "yes")
    lastfm_api_key=$(ask_if_empty      "Last.fm API Key"        "$lastfm_api_key")
    youtube_api_key=$(ask_if_empty     "YouTube Data API Key"   "$youtube_api_key")
    tmdb_api_key=$(ask_if_empty        "TMDB API Key"           "$tmdb_api_key")

    echo ""
    echo -e "${YELLOW}=== Contraseñas MySQL ===${NC}"
    mysql_root_password=$(ask_if_empty "MySQL Root Password" "${mysql_root_password:-rootpass_dev}" "yes")
    mysql_password=$(ask_if_empty      "MySQL User Password" "${mysql_password:-devpassword}"       "yes")
  else
    success "Todas las claves ya están configuradas en .env — sin cambios."
    return
  fi

  cat > "$ENV_FILE" <<EOF
# Docker Compose Environment Variables — DESARROLLO
# Generado por dev-setup.sh el $(date '+%Y-%m-%d %H:%M:%S')

# Google OAuth
GOOGLE_CLIENT_ID=${google_client_id}

# Google Books API
GOOGLE_BOOKS_API_KEY=${google_books_api_key}

# Spotify API
SPOTIFY_CLIENT_ID=${spotify_client_id}
SPOTIFY_CLIENT_SECRET=${spotify_client_secret}

# Last.fm API
LASTFM_API_KEY=${lastfm_api_key}

# YouTube Data API v3
YOUTUBE_API_KEY=${youtube_api_key}

# Database
MYSQL_ROOT_PASSWORD=${mysql_root_password}
MYSQL_PASSWORD=${mysql_password}
DB_PASSWORD=${mysql_password}
DB_MIRROR_IMPORT_PASSWORD=${mirror_import_password}
DB_MIRROR_PASSWORD=${mirror_password}
TMDB_API_KEY=${tmdb_api_key}
EOF

  success ".env raíz creado/actualizado."
}

# ---------------------------------------------------------------------------
# Helper: escribe o actualiza una clave en un .env file
# ---------------------------------------------------------------------------
env_set() {
  local file="$1" key="$2" value="$3"
  [[ -z "$value" ]] && return
  if grep -qE "^${key}=" "$file" 2>/dev/null; then
    sed -i "s|^${key}=.*|${key}=${value}|" "$file"
  else
    echo "${key}=${value}" >> "$file"
  fi
}

# ---------------------------------------------------------------------------
# Crear/sincronizar backend/.env.docker-development
# ---------------------------------------------------------------------------
setup_backend_env() {
  if [[ ! -f "$BACKEND_ENV_FILE" ]]; then
    info "Creando backend/.env.docker-development desde el ejemplo..."
    cp "$ROOT_DIR/backend/.env.docker-development.example" "$BACKEND_ENV_FILE"
    warn "Revisa $BACKEND_ENV_FILE y ajusta JWT_SECRET si es necesario."
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
  # El mirror es un servidor aparte. Bajo Apache manda el entorno del compose
  # (variables_order=EGPCS, y bootstrap.php solo rellena huecos), así que estas
  # son redundantes ahí — pero no para quien lea el fichero para entender la
  # configuración, ni si algún día se ejecuta el backend fuera de este compose.
  env_set "$BACKEND_ENV_FILE" DB_MIRROR_HOST        "mirror-mysql"
  env_set "$BACKEND_ENV_FILE" DB_MIRROR_PORT        "3306"
  env_set "$BACKEND_ENV_FILE" DB_MIRROR_USERNAME    "library_mirror_user"
  env_set "$BACKEND_ENV_FILE" DB_MIRROR_PASSWORD    "$(env_get "$ENV_FILE" DB_MIRROR_PASSWORD)"

  success "backend/.env.docker-development sincronizado."
}

# ---------------------------------------------------------------------------
# Build y arranque de contenedores
# ---------------------------------------------------------------------------
start_services() {
  local rebuild="${1:-no}"
  cd "$ROOT_DIR"

  if [[ "$rebuild" == "yes" ]]; then
    info "Eliminando contenedores y volúmenes existentes..."
    compose_cmd down -v --remove-orphans 2>/dev/null || true
    info "Rebuilding imágenes Docker (sin caché)..."
    compose_cmd build --no-cache
  else
    # Si las imágenes ya existen localmente, saltamos el build.
    # El código fuente se sirve vía volúmenes montados, no necesita rebuild para cambios de código.
    # Usa --reset para forzar reconstrucción (p.ej. al cambiar dependencias en composer.json/package.json).
    if docker image inspect libraryvue-backend:latest &>/dev/null \
       && docker image inspect libraryvue-frontend:latest &>/dev/null; then
      info "Imágenes Docker ya existen — saltando build. Usa --reset para reconstruir."
    else
      info "Construyendo imágenes Docker..."
      compose_cmd build
    fi
  fi

  info "Arrancando servicios (MySQL, Backend, Frontend)..."
  compose_cmd up -d

  # Esperar a que MySQL esté listo
  info "Esperando a que MySQL esté disponible..."
  local retries=30
  until compose_cmd exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; do
    retries=$((retries - 1))
    if [[ $retries -le 0 ]]; then
      error "MySQL no respondió a tiempo. Revisa los logs: ./dev-setup.sh --logs"
      exit 1
    fi
    sleep 2
  done
  success "MySQL listo."

  # Esperar a que el backend esté disponible
  info "Esperando a que el backend esté disponible..."
  retries=30
  until curl -sf http://127.0.0.1:8888/index.php -o /dev/null 2>/dev/null; do
    retries=$((retries - 1))
    if [[ $retries -le 0 ]]; then
      warn "Backend no respondió en el tiempo esperado. Puede que aún esté iniciando."
      break
    fi
    sleep 2
  done

  # Lint del frontend: las reglas de accesibilidad están en `error`, así que un
  # `<div @click>` nuevo se cae aquí en vez de llegar a producción. No tumba el
  # arranque —los contenedores ya están arriba y sirven— pero lo dice claro.
  info "Pasando el lint del frontend..."
  if compose_cmd exec -T frontend npm run lint -- --no-fix > /dev/null 2>&1; then
    success "Lint del frontend limpio."
  else
    error "El lint del frontend encuentra errores. Los servicios siguen arriba."
    error "Míralos con: docker compose exec frontend npm run lint -- --no-fix"
  fi

  # Lint de estilos: tres reglas que defienden el sistema de color y de breakpoints
  # —ni un hex suelto, ni un px dentro de @media, ni un @import—. Un color a mano
  # nuevo se cae aquí, que es lo que impide que el barrido se deshaga solo.
  info "Pasando el lint de estilos..."
  if compose_cmd exec -T frontend npm run lint:styles > /dev/null 2>&1; then
    success "Lint de estilos limpio."
  else
    error "El lint de estilos encuentra errores. Los servicios siguen arriba."
    error "Míralos con: docker compose exec frontend npm run lint:styles"
  fi

  echo ""
  success "=============================================="
  success " LibraryVue está corriendo en desarrollo"
  success "=============================================="
  echo ""
  echo -e "  ${GREEN}Frontend:${NC} http://localhost:8080"
  echo -e "  ${GREEN}Backend:${NC}  http://localhost:8888"
  echo -e "  ${GREEN}MySQL:${NC}    localhost:3308  (user: library_user)"
  echo ""
  echo -e "  Logs:   ${BLUE}./dev-setup.sh --logs${NC}"
  echo -e "  Parar:  ${BLUE}./dev-setup.sh --stop${NC}"
  echo -e "  Reset:  ${BLUE}./dev-setup.sh --reset${NC}"
  echo ""
}

# ---------------------------------------------------------------------------
# Verificar dependencias móviles (nvm/node, Android SDK, Java)
# ---------------------------------------------------------------------------
check_deps_mobile() {
  local missing=()

  # nvm
  export NVM_DIR="${NVM_DIR:-$HOME/.nvm}"
  # shellcheck source=/dev/null
  [[ -s "$NVM_DIR/nvm.sh" ]] && . "$NVM_DIR/nvm.sh"

  command -v node &>/dev/null || missing+=("node/nvm")
  command -v npm  &>/dev/null || missing+=("npm")

  # Android SDK (ANDROID_HOME o ruta por defecto)
  ANDROID_HOME="${ANDROID_HOME:-$HOME/Android/Sdk}"
  if [[ ! -d "$ANDROID_HOME" ]]; then
    missing+=("Android SDK (esperado en $ANDROID_HOME)")
  fi

  # Java 17
  if ! command -v java &>/dev/null; then
    missing+=("java")
  else
    local java_ver
    java_ver=$(java -version 2>&1 | head -1 | grep -oP '"\K[0-9]+')
    if [[ "$java_ver" -lt 17 ]]; then
      warn "Java $java_ver detectado. Se recomienda Java 17 para Android."
    fi
  fi

  if [[ ${#missing[@]} -gt 0 ]]; then
    error "Faltan dependencias para móvil: ${missing[*]}"
    error "Instálalas y vuelve a intentarlo."
    exit 1
  fi
}

# ---------------------------------------------------------------------------
# Crear frontend/.env.mobile (si no existe o si el usuario quiere actualizar)
# ---------------------------------------------------------------------------
MOBILE_ENV_FILE="$ROOT_DIR/frontend/.env.mobile"
SECRETS_XML="$ROOT_DIR/frontend/android/app/src/main/res/values/secrets.xml"

setup_mobile_env() {
  local update="no"
  if [[ -f "$MOBILE_ENV_FILE" ]]; then
    read -rp "frontend/.env.mobile ya existe. ¿Actualizar? (s/N): " update
  fi

  if [[ ! -f "$MOBILE_ENV_FILE" ]] || [[ "$update" =~ ^[sS]$ ]]; then
    info "Configurando frontend/.env.mobile..."

    local api_url google_client_id
    api_url=$(env_get "$MOBILE_ENV_FILE"          VUE_APP_API_URL)
    google_client_id=$(env_get "$MOBILE_ENV_FILE" VUE_APP_GOOGLE_CLIENT_ID)

    # Fallbacks desde .env raíz
    [[ -z "$google_client_id" ]] && google_client_id=$(env_get "$ENV_FILE" GOOGLE_CLIENT_ID)

    echo ""
    echo -e "${YELLOW}=== Configuración móvil ===${NC}"
    warn "VUE_APP_API_URL usa 10.0.2.2 para emulador Android (= localhost del host)."
    api_url=$(ask_if_empty    "VUE_APP_API_URL"        "${api_url:-http://10.0.2.2:8888/index.php}")
    google_client_id=$(ask_if_empty "Google OAuth Client ID" "$google_client_id")

    cat > "$MOBILE_ENV_FILE" <<EOF
# Mobile environment — Capacitor / Android
# Generado por dev-setup.sh el $(date '+%Y-%m-%d %H:%M:%S')
# NUNCA commitear este archivo

VUE_APP_API_URL=${api_url}
VUE_APP_MODE=mobile
VUE_APP_GOOGLE_CLIENT_ID=${google_client_id}
EOF
    success "frontend/.env.mobile creado/actualizado."
  fi

  # secrets.xml — Google OAuth Client ID para el plugin nativo
  local google_client_id
  google_client_id=$(env_get "$MOBILE_ENV_FILE" VUE_APP_GOOGLE_CLIENT_ID)

  if [[ ! -f "$SECRETS_XML" ]]; then
    info "Creando android/app/.../secrets.xml..."
    mkdir -p "$(dirname "$SECRETS_XML")"
    cat > "$SECRETS_XML" <<EOF
<?xml version="1.0" encoding="utf-8"?>
<resources>
    <!-- Google OAuth client ID (web application type) -->
    <string name="server_client_id">${google_client_id}</string>
</resources>
EOF
    success "secrets.xml creado."

    # Añadir a .gitignore si no está ya
    local gitignore="$ROOT_DIR/.gitignore"
    if [[ -f "$gitignore" ]] && ! grep -q 'secrets.xml' "$gitignore"; then
      echo "\n# Android secrets (OAuth client ID)\nfrontend/android/app/src/main/res/values/secrets.xml" >> "$gitignore"
      success "secrets.xml añadido a .gitignore."
    fi
  else
    success "secrets.xml ya existe — sin cambios."
  fi
}

# ---------------------------------------------------------------------------
# Build Capacitor + sync Android
# ---------------------------------------------------------------------------
cmd_mobile() {
  check_deps_mobile
  setup_mobile_env

  export NVM_DIR="${NVM_DIR:-$HOME/.nvm}"
  # shellcheck source=/dev/null
  [[ -s "$NVM_DIR/nvm.sh" ]] && . "$NVM_DIR/nvm.sh"

  cd "$ROOT_DIR/frontend"

  info "Instalando dependencias npm (si es necesario)..."
  npm install --silent

  info "Compilando app móvil (npm run build:mobile)..."
  npm run build:mobile
  success "Build móvil completado."

  info "Sincronizando con proyecto Android (cap sync)..."
  npx cap sync android
  success "Sync completado."

  echo ""
  success "============================================"
  success " App Android lista para ejecutar"
  success "============================================"
  echo -e "  Emulador (10.0.2.2) apunta a: ${GREEN}http://localhost:8888${NC}"
  echo ""
  echo -e "  Asegúrate de que el backend web está corriendo:"
  echo -e "    ${BLUE}./dev-setup.sh${NC}  (en otra terminal si no está levantado)"
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
  info "Deteniendo contenedores..."
  compose_cmd down
  success "Contenedores detenidos."
}

cmd_logs() {
  cd "$ROOT_DIR"
  compose_cmd logs -f
}

cmd_start() {
  check_deps

  if [[ ! -f "$ENV_FILE" ]]; then
    warn "No se encontró el archivo .env raíz. Se pedirán todas las claves."
  fi
  setup_root_env

  setup_backend_env
  start_services "no"
}

cmd_reset() {
  check_deps
  warn "RESET: se eliminarán contenedores y volúmenes Docker (incluida la BD)."
  read -rp "¿Continuar? (s/N): " confirm
  [[ "$confirm" =~ ^[sS]$ ]] || { info "Cancelado."; exit 0; }

  setup_root_env
  setup_backend_env
  start_services "yes"
}

# ---------------------------------------------------------------------------
# Bootstrap del mirror de catálogos (library_mirror)
# ---------------------------------------------------------------------------
# Se aplica con root, y NO como una migración más. run_migrations.sh conecta
# como library_user, que solo tiene GRANT ALL ON library_db.*: no puede crear
# una base nueva. Y como prod-deploy.sh comparte ese runner, un fichero en
# migrations/ rompería producción al promoverse a master.
# Idempotente: mirror_schema.sql usa IF NOT EXISTS en todo.
# ---------------------------------------------------------------------------
bootstrap_mirror_db() {
  # El mirror ya NO vive dentro de este docker-compose: tiene su propio stack
  # (docker-compose.mirror.yml) porque lo comparte con producción. Duplicarlo
  # serían 2,2 GB por entorno y dos cuotas de TMDB y MusicBrainz para servir el
  # mismo catálogo público, y meterlo aquí ataría la búsqueda de películas de
  # library.dcahomelab.com a que el entorno de desarrollo esté levantado.
  #
  # Toda la creación (red, volúmenes, esquema y usuarios) vive en un solo sitio,
  # `mirror-sync.sh --bootstrap`, para que dev y prod arranquen lo mismo.
  info "Preparando el mirror de catálogos (stack compartido)..."
  if "$ROOT_DIR/mirror-sync.sh" --bootstrap; then
    success "Mirror de catálogos listo."
  else
    error "Falló el arranque del mirror de catálogos."
    error "Reintenta a mano con: ./mirror-sync.sh --bootstrap"
    exit 1
  fi
}

cmd_migrate() {
  check_deps
  bootstrap_mirror_db
  info "Aplicando migraciones de base de datos pendientes..."
  "$ROOT_DIR/docker/database/run_migrations.sh"
}

# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------
case "$MODE" in
  start)   cmd_start   ;;
  reset)   cmd_reset   ;;
  stop)    cmd_stop    ;;
  logs)    cmd_logs    ;;
  mobile)  cmd_mobile  ;;
  migrate) cmd_migrate ;;
esac
