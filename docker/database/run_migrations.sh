#!/usr/bin/env bash
# =============================================================================
# run_migrations.sh — Aplica migraciones de base de datos pendientes
# =============================================================================
# Uso (desde la raíz del proyecto):
#   ./docker/database/run_migrations.sh
#   ./docker/database/run_migrations.sh --env-file .env.prod --compose-file docker-compose.prod.yml
#   ./docker/database/run_migrations.sh --service mysql-test   # ensayo sobre la BD desechable
#
# Opciones:
#   --env-file      — archivo .env del que salen las credenciales (default: .env de la raíz)
#   --compose-file  — archivo compose (default: el que resuelva docker compose)
#   --service       — servicio de compose donde vive MySQL (default: mysql)
#
# Variables de entorno opcionales (sobreescriben los valores del .env):
#   DB_USER  — usuario de MySQL (default: library_user)
#   DB_PASS  — contraseña (se lee de MYSQL_PASSWORD en el .env si no se define)
#   DB_NAME  — base de datos (default: library_db)
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
MIGRATIONS_DIR="$SCRIPT_DIR/migrations"

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
# Parsear argumentos
# ---------------------------------------------------------------------------
ENV_FILE_ARG=""
COMPOSE_FILE_ARG=""
# Servicio de compose donde corre MySQL. Se parametriza para poder ensayar la
# tanda entera contra `mysql-test` (perfil `test`, sobre tmpfs) sin tocar la
# base de desarrollo. El default es el de siempre: nadie que no lo pase nota
# ningún cambio.
MYSQL_SERVICE="mysql"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --env-file=*)     ENV_FILE_ARG="${1#--env-file=}"         ;;
    --env-file)       shift; ENV_FILE_ARG="$1"                ;;
    --compose-file=*) COMPOSE_FILE_ARG="${1#--compose-file=}" ;;
    --compose-file)   shift; COMPOSE_FILE_ARG="$1"            ;;
    --service=*)      MYSQL_SERVICE="${1#--service=}"         ;;
    --service)        shift; MYSQL_SERVICE="$1"               ;;
  esac
  shift
done

# ---------------------------------------------------------------------------
# Construir el comando docker compose (con o sin env-file / compose-file)
# ---------------------------------------------------------------------------
compose_cmd() {
  local args=()
  [[ -n "$ENV_FILE_ARG" ]]     && args+=(--env-file "$ENV_FILE_ARG")
  [[ -n "$COMPOSE_FILE_ARG" ]] && args+=(-f "$COMPOSE_FILE_ARG")

  if docker compose version &>/dev/null 2>&1; then
    docker compose "${args[@]}" "$@"
  else
    docker-compose "${args[@]}" "$@"
  fi
}

# ---------------------------------------------------------------------------
# Leer una variable de un archivo .env
# ---------------------------------------------------------------------------
env_get() {
  local file="$1" key="$2"
  grep -E "^${key}=" "$file" 2>/dev/null | head -1 | cut -d'=' -f2- || true
}

# ---------------------------------------------------------------------------
# Resolver credenciales de MySQL
# ---------------------------------------------------------------------------
resolve_db_creds() {
  local env_source="${ENV_FILE_ARG:-$ROOT_DIR/.env}"

  DB_USER="${DB_USER:-library_user}"

  if [[ -z "${DB_NAME:-}" ]]; then
    DB_NAME="$(env_get "$env_source" MYSQL_DATABASE)"
  fi
  if [[ -z "${DB_NAME:-}" ]]; then
    DB_NAME="$(env_get "$env_source" DB_DATABASE)"
  fi
  DB_NAME="${DB_NAME:-library_db}"

  if [[ -z "${DB_PASS:-}" ]]; then
    DB_PASS="$(env_get "$env_source" MYSQL_PASSWORD)"
  fi
  if [[ -z "${DB_PASS:-}" ]]; then
    DB_PASS="$(env_get "$env_source" DB_PASSWORD)"
  fi
  if [[ -z "${DB_PASS:-}" ]]; then
    error "No se pudo resolver MYSQL_PASSWORD desde $env_source"
    error "Asegúrate de que el archivo .env existe y contiene MYSQL_PASSWORD."
    exit 1
  fi
}

# ---------------------------------------------------------------------------
# Filtrar la salida de error de MySQL
#
# Se descarta UNA sola línea, el aviso «Using a password on the command line»
# que MySQL escupe en cada invocación; todo lo demás pasa a stderr, que es
# donde se diagnostica. Antes se tiraba stderr entero con `2>/dev/null` y una
# migración fallida no decía por qué.
#
# Va por fichero temporal y no por tubería a propósito: con `set -o pipefail`
# (:21) el código de salida de `mysql | grep` sería el del `grep` —que devuelve
# 1 cuando no filtra ninguna línea—, y el de `mysql` es justo lo que decide si
# una migración se da por aplicada. Y el filtro escribe a stderr, nunca a
# stdout: `is_applied()` hace `tail -1` de la salida y cualquier línea de más
# se convertiría en «el resultado».
# ---------------------------------------------------------------------------
mysql_stderr_filter() {
  grep -v 'Using a password on the command line' "$1" >&2 || true
}

# ---------------------------------------------------------------------------
# Ejecutar SQL inline en el contenedor MySQL
# ---------------------------------------------------------------------------
mysql_exec() {
  local errfile status=0
  errfile="$(mktemp)"

  compose_cmd exec -T "$MYSQL_SERVICE" mysql \
    -u"$DB_USER" \
    -p"$DB_PASS" \
    "$DB_NAME" \
    -e "$1" 2>"$errfile" || status=$?

  mysql_stderr_filter "$errfile"
  rm -f "$errfile"
  return "$status"
}

# ---------------------------------------------------------------------------
# Ejecutar un archivo SQL local en el contenedor MySQL (vía stdin)
#
# El valor de retorno es el de `mysql`, y no es un detalle: es lo que decide si
# la migración se registra como aplicada (ver run_migrations()).
# ---------------------------------------------------------------------------
mysql_file() {
  local errfile status=0
  errfile="$(mktemp)"

  compose_cmd exec -T "$MYSQL_SERVICE" mysql \
    -u"$DB_USER" \
    -p"$DB_PASS" \
    "$DB_NAME" \
    2>"$errfile" < "$1" || status=$?

  mysql_stderr_filter "$errfile"
  rm -f "$errfile"
  return "$status"
}

# ---------------------------------------------------------------------------
# Crear la tabla de control si no existe (bootstrap idempotente)
# ---------------------------------------------------------------------------
bootstrap_migrations_table() {
  mysql_exec "
    CREATE TABLE IF NOT EXISTS schema_migrations (
      id         INT AUTO_INCREMENT PRIMARY KEY,
      filename   VARCHAR(255) NOT NULL UNIQUE,
      applied_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
      checksum   VARCHAR(64)  NOT NULL,
      INDEX idx_filename (filename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Registro de migraciones de base de datos aplicadas';
  "
}

# ---------------------------------------------------------------------------
# Comprobar si una migración ya fue aplicada
#
# Devuelve 0 si la fila existe y deja su `checksum` en APPLIED_CHECKSUM, para
# que run_migrations() pueda contrastarlo con el del fichero en disco.
#
# La consulta es de agregación a propósito: `COUNT(*)`/`MAX(...)` devuelven
# SIEMPRE una fila, así que la salida son dos líneas (cabecera + valores) haya
# o no registro, y el `tail -1` de siempre sigue siendo el resultado. Los dos
# campos vienen separados por tabulador, que es como los emite `mysql -e`.
# ---------------------------------------------------------------------------
APPLIED_CHECKSUM=""

is_applied() {
  local filename="$1"
  local row
  APPLIED_CHECKSUM=""
  row=$(mysql_exec "SELECT COUNT(*), COALESCE(MAX(checksum), '') FROM schema_migrations WHERE filename='${filename}';" | tail -1)
  [[ "${row%%$'\t'*}" == "1" ]] || return 1
  APPLIED_CHECKSUM="${row#*$'\t'}"
  return 0
}

# ---------------------------------------------------------------------------
# Registrar una migración como aplicada
# ---------------------------------------------------------------------------
record_migration() {
  local filename="$1" checksum="$2"
  mysql_exec "INSERT INTO schema_migrations (filename, checksum) VALUES ('${filename}', '${checksum}');"
}

# ---------------------------------------------------------------------------
# Runner principal
# ---------------------------------------------------------------------------
run_migrations() {
  resolve_db_creds

  info "Verificando conexión a MySQL..."
  if ! compose_cmd exec -T "$MYSQL_SERVICE" mysqladmin ping -h localhost --silent 2>/dev/null; then
    error "MySQL no está disponible. Asegúrate de que el contenedor está corriendo."
    exit 1
  fi
  success "MySQL disponible."

  info "Inicializando tabla de control de migraciones..."
  bootstrap_migrations_table

  # Recopilar archivos *.sql de la carpeta migrations, ordenados por nombre
  local migration_files=()
  if [[ -d "$MIGRATIONS_DIR" ]]; then
    while IFS= read -r -d '' f; do
      migration_files+=("$f")
    done < <(find "$MIGRATIONS_DIR" -maxdepth 1 -name "*.sql" -print0 | sort -z)
  fi

  if [[ ${#migration_files[@]} -eq 0 ]]; then
    success "No hay archivos de migración en docker/database/migrations/. La base de datos está al día."
    return
  fi

  local pending=0
  local already_applied=0
  # Migraciones ya aplicadas cuyo fichero ha cambiado desde entonces. Se
  # recogen todas antes de abortar, para que el listado salga completo.
  local modified=()

  echo ""
  echo -e "${YELLOW}=== Estado de migraciones ===${NC}"
  for filepath in "${migration_files[@]}"; do
    local filename disk_checksum
    filename=$(basename "$filepath")
    if is_applied "$filename"; then
      disk_checksum=$(sha256sum "$filepath" | cut -d' ' -f1)
      # Un checksum vacío en la fila (registro escrito a mano antes de que el
      # runner lo guardara) no es una divergencia: no hay con qué comparar.
      if [[ -n "$APPLIED_CHECKSUM" && "$APPLIED_CHECKSUM" != "$disk_checksum" ]]; then
        echo -e "  ${RED}✗${NC} $filename  ${RED}(MODIFICADA después de aplicarse)${NC}"
        modified+=("${filename}|${APPLIED_CHECKSUM}|${disk_checksum}")
      else
        echo -e "  ${GREEN}✓${NC} $filename  ${BLUE}(ya aplicada)${NC}"
      fi
      already_applied=$((already_applied + 1))
    else
      echo -e "  ${YELLOW}→${NC} $filename  ${YELLOW}(pendiente)${NC}"
      pending=$((pending + 1))
    fi
  done
  echo ""

  # Regla 1 de docker/database/migrations/README.md: una migración aplicada no
  # se edita. Hasta hoy el `checksum` se guardaba y no lo miraba nadie, así que
  # el cambio se saltaba en silencio. Se aborta ANTES de aplicar nada.
  if [[ ${#modified[@]} -gt 0 ]]; then
    error "Migraciones ya aplicadas cuyo fichero ha cambiado desde entonces:"
    local entry m_file m_db m_disk
    for entry in "${modified[@]}"; do
      IFS='|' read -r m_file m_db m_disk <<< "$entry"
      error "  $m_file"
      error "    registrado: $m_db"
      error "    en disco:   $m_disk"
    done
    error ""
    error "  La regla 1 de docker/database/migrations/README.md lo prohíbe: una"
    error "  migración aplicada no se modifica. Restaura el fichero a como estaba"
    error "  o escribe una migración nueva con el cambio."
    exit 1
  fi

  if [[ $pending -eq 0 ]]; then
    success "Base de datos actualizada — no hay migraciones pendientes. ($already_applied ya aplicadas)"
    return
  fi

  info "Aplicando $pending migración(es) pendiente(s)..."
  echo ""

  for filepath in "${migration_files[@]}"; do
    local filename
    filename=$(basename "$filepath")

    is_applied "$filename" && continue

    info "  → Aplicando: $filename ..."
    local checksum
    checksum=$(sha256sum "$filepath" | cut -d' ' -f1)

    if mysql_file "$filepath"; then
      record_migration "$filename" "$checksum"
      success "  ✓ Aplicada: $filename"
    else
      echo ""
      error "  ✗ Falló la migración: $filename"
      error "    Corrige el error en el archivo SQL y vuelve a ejecutar."
      error "    Las migraciones previas ya están registradas y no se re-ejecutarán."
      exit 1
    fi
  done

  echo ""
  success "=============================================="
  success " $pending migración(es) aplicada(s) correctamente"
  success "=============================================="
}

run_migrations
