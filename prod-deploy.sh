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
    --help|-h)
      echo "Uso: $0 [--rebuild|--stop|--logs|--status|--mobile-build|--help]"
      echo ""
      echo "  (sin args)      Deploy completo (build si hay cambios + up)"
      echo "  --rebuild       Fuerza rebuild de todas las imágenes sin caché"
      echo "  --stop          Detiene todos los contenedores de producción"
      echo "  --logs          Logs en tiempo real de todos los servicios"
      echo "  --status        Estado de contenedores y salud del servicio"
      echo "  --mobile-build  Compila APK/AAB de producción y sincroniza Android"
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
    docker compose -f "$COMPOSE_FILE" "$@"
  else
    docker-compose -f "$COMPOSE_FILE" "$@"
  fi
}

# ---------------------------------------------------------------------------
# Leer variable de un .env
# ---------------------------------------------------------------------------
env_get() {
  local file="$1" key="$2"
  grep -E "^${key}=" "$file" 2>/dev/null | head -1 | cut -d'=' -f2- || true
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
  local lastfm_api_key mysql_root_password mysql_password

  google_client_id=$(env_get      "$ENV_FILE" GOOGLE_CLIENT_ID)
  spotify_client_id=$(env_get     "$ENV_FILE" SPOTIFY_CLIENT_ID)
  spotify_client_secret=$(env_get "$ENV_FILE" SPOTIFY_CLIENT_SECRET)
  lastfm_api_key=$(env_get        "$ENV_FILE" LASTFM_API_KEY)
  mysql_root_password=$(env_get   "$ENV_FILE" MYSQL_ROOT_PASSWORD)
  mysql_password=$(env_get        "$ENV_FILE" MYSQL_PASSWORD)

  local needs_input=false
  [[ -z "$google_client_id" || -z "$spotify_client_id" || -z "$spotify_client_secret" \
     || -z "$lastfm_api_key" || -z "$mysql_root_password" || -z "$mysql_password" ]] \
    && needs_input=true

  if [[ "$needs_input" == "true" ]]; then
    warn "Faltan claves en .env.prod — solo se pedirán las vacías."
    echo ""
    echo -e "${YELLOW}=== APIs externas (producción) ===${NC}"
    google_client_id=$(ask_if_empty      "Google OAuth Client ID"  "$google_client_id")
    spotify_client_id=$(ask_if_empty     "Spotify Client ID"       "$spotify_client_id")
    spotify_client_secret=$(ask_if_empty "Spotify Client Secret"   "$spotify_client_secret" "yes")
    lastfm_api_key=$(ask_if_empty        "Last.fm API Key"         "$lastfm_api_key")

    echo ""
    echo -e "${YELLOW}=== Contraseñas MySQL (producción — usa valores seguros) ===${NC}"
    mysql_root_password=$(ask_if_empty "MySQL Root Password" "$mysql_root_password" "yes")
    mysql_password=$(ask_if_empty      "MySQL User Password" "$mysql_password"      "yes")
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

# Spotify API
SPOTIFY_CLIENT_ID=${spotify_client_id}
SPOTIFY_CLIENT_SECRET=${spotify_client_secret}

# Last.fm API
LASTFM_API_KEY=${lastfm_api_key}

# Database
MYSQL_ROOT_PASSWORD=${mysql_root_password}
MYSQL_PASSWORD=${mysql_password}
DB_PASSWORD=${mysql_password}
EOF

  success ".env.prod creado/actualizado."
}

# ---------------------------------------------------------------------------
# Crear backend/.env.docker-production (si no existe)
# ---------------------------------------------------------------------------
setup_backend_prod_env() {
  if [[ -f "$BACKEND_ENV_FILE" ]]; then
    success "backend/.env.docker-production ya existe — sin cambios."
    return
  fi

  info "Creando backend/.env.docker-production desde el ejemplo..."
  cp "$ROOT_DIR/backend/.env.docker-production.example" "$BACKEND_ENV_FILE"

  # Sincronizar contraseñas
  local mysql_password google_client_id spotify_client_id spotify_client_secret lastfm_api_key
  mysql_password=$(env_get        "$ENV_FILE" MYSQL_PASSWORD)
  google_client_id=$(env_get      "$ENV_FILE" GOOGLE_CLIENT_ID)
  spotify_client_id=$(env_get     "$ENV_FILE" SPOTIFY_CLIENT_ID)
  spotify_client_secret=$(env_get "$ENV_FILE" SPOTIFY_CLIENT_SECRET)
  lastfm_api_key=$(env_get        "$ENV_FILE" LASTFM_API_KEY)

  sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${mysql_password}|"             "$BACKEND_ENV_FILE"
  sed -i "s|^MYSQL_PASSWORD=.*|MYSQL_PASSWORD=${mysql_password}|"       "$BACKEND_ENV_FILE"
  sed -i "s|^GOOGLE_CLIENT_ID=.*|GOOGLE_CLIENT_ID=${google_client_id}|" "$BACKEND_ENV_FILE"
  sed -i "s|^SPOTIFY_CLIENT_ID=.*|SPOTIFY_CLIENT_ID=${spotify_client_id}|"         "$BACKEND_ENV_FILE"
  sed -i "s|^SPOTIFY_CLIENT_SECRET=.*|SPOTIFY_CLIENT_SECRET=${spotify_client_secret}|" "$BACKEND_ENV_FILE"
  sed -i "s|^LASTFM_API_KEY=.*|LASTFM_API_KEY=${lastfm_api_key}|"       "$BACKEND_ENV_FILE"

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
deploy_services() {
  local no_cache="${1:-no}"
  cd "$ROOT_DIR"

  if [[ "$no_cache" == "yes" ]]; then
    info "Rebuilding imágenes sin caché..."
    compose_cmd build --no-cache --env-file "$ENV_FILE"
  else
    info "Construyendo imágenes (solo si hay cambios)..."
    compose_cmd build --env-file "$ENV_FILE"
  fi

  info "Arrancando servicios de producción..."
  compose_cmd up -d --env-file "$ENV_FILE"

  # Esperar MySQL
  info "Esperando a que MySQL esté disponible..."
  local retries=40
  until compose_cmd exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; do
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
  local google_client_id omdb_api_key
  google_client_id=$(env_get "$MOBILE_PROD_ENV" VUE_APP_GOOGLE_CLIENT_ID)
  omdb_api_key=$(env_get     "$MOBILE_PROD_ENV" VUE_APP_OMDB_API_KEY)

  # Fallbacks desde .env.prod
  [[ -z "$google_client_id" ]] && google_client_id=$(env_get "$ENV_FILE" GOOGLE_CLIENT_ID)

  local needs_input=false
  [[ -z "$google_client_id" || -z "$omdb_api_key" ]] && needs_input=true

  if [[ "$needs_input" == "true" ]]; then
    info "Faltan claves en frontend/.env.production — solo se pedirán las vacías."
    echo ""
    echo -e "${YELLOW}=== App móvil producción ===${NC}"
    google_client_id=$(ask_if_empty "Google OAuth Client ID" "$google_client_id")
    omdb_api_key=$(ask_if_empty     "OMDB API Key (películas)" "$omdb_api_key")

    cat > "$MOBILE_PROD_ENV" <<EOF
# Frontend production env — usado por npm run build:mobile:prod
# Generado por prod-deploy.sh el $(date '+%Y-%m-%d %H:%M:%S')
# NUNCA commitear este archivo

VUE_APP_API_URL=${PROD_API_URL}
VUE_APP_MODE=mobile
VUE_APP_GOOGLE_CLIENT_ID=${google_client_id}
VUE_APP_OMDB_API_KEY=${omdb_api_key}
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
  deploy_services "no"
}

cmd_rebuild() {
  check_deps

  warn "REBUILD: se reconstruirán todas las imágenes sin caché."
  read -rp "¿Continuar? (s/N): " confirm
  [[ "$confirm" =~ ^[sS]$ ]] || { info "Cancelado."; exit 0; }

  setup_prod_env
  setup_backend_prod_env
  deploy_services "yes"
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
esac
