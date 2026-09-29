#!/usr/bin/env bash
# =============================================================================
# augur-catalog.sh — LibraryVue: declara el catálogo de eventos en un Augur
# =============================================================================
# Uso:
#   tools/augur-catalog.sh <endpoint> <email> [slug]
#   tools/augur-catalog.sh http://localhost:8897 yo@ejemplo.com          (dev)
#   tools/augur-catalog.sh https://augur.dcahomelab.com yo@ejemplo.com   (prod)
#
# Hace `catalog.upsert` de CADA evento de frontend/src/analytics/catalog.js en el proyecto
# `libraryvue` (o el slug que se pase) de ese Augur, para que su dashboard deje de listarlos como
# `undeclared`. Es idempotente: upsert reemplaza la descripción y las propiedades de cada evento, y
# volver a correrlo tras añadir uno al catálogo solo pone al día. NO borra del Augur los eventos que
# ya no estén en el catálogo (eso es `catalog.delete`, a mano desde el dashboard).
#
# Plan «Catálogo de Eventos de Producto», M1. Cómo se añade un evento: entrada en catalog.js →
# track('nombre', {...}) en la app → este script contra dev y contra prod.
#
# La contraseña se pide con `read -s`: nunca por argumento (queda en el historial y en `ps`) ni por
# fichero. La API de gestión de Augur es sesión por cookie (`augur_session`, SameSite=Strict, sin
# token CSRF): el tarro de cookies va a un temporal que se borra al salir, pase lo que pase.
# Hace falta el rol editor (o más) en el proyecto.
#
# Necesita curl, jq y Node (el export lee el catálogo, que es un módulo ES). Sin Node en el host,
# el export corre dentro del contenedor `frontend` del compose de dev.
# =============================================================================

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
EXPORT="$ROOT_DIR/tools/augur-catalog-export.cjs"

if [ $# -lt 2 ] || [ $# -gt 3 ]; then
  sed -n '5,8p' "$0" | sed 's/^# \{0,1\}//' >&2
  exit 2
fi

ENDPOINT="${1%/}"
EMAIL="$2"
SLUG="${3:-libraryvue}"
API="$ENDPOINT/index.php"

for bin in curl jq; do
  command -v "$bin" >/dev/null || { echo "Falta '$bin' en el PATH" >&2; exit 1; }
done

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
chmod 700 "$WORK"
JAR="$WORK/cookies"
CATALOG_JSON="$WORK/catalog.json"

# ── 1. El catálogo, exportado al formato de catalog.upsert ─────────────────────
if command -v node >/dev/null; then
  # catalog.js es un módulo ES en un package.json sin "type": Node lo reinterpreta y avisa; el aviso
  # no dice nada útil aquí.
  node --disable-warning=MODULE_TYPELESS_PACKAGE_JSON "$EXPORT" > "$CATALOG_JSON"
else
  # En el contenedor, src/ está montado en /app/src y tools/ no: el script entra por stdin.
  docker compose -f "$ROOT_DIR/docker-compose.yml" exec -T frontend \
    node - /app/src/analytics/catalog.js < "$EXPORT" > "$CATALOG_JSON"
fi
TOTAL="$(jq 'length' "$CATALOG_JSON")"
echo "Catálogo: $TOTAL eventos"

# ── 2. Sesión ──────────────────────────────────────────────────────────────────
# `call <cuerpo-json>` → escribe la respuesta en $WORK/resp y devuelve el código HTTP por stdout.
call () {
  curl -sS -o "$WORK/resp" -w '%{http_code}' -b "$JAR" -c "$JAR" \
    -H 'Content-Type: application/json' --data-binary @- "$API"
}

# Sin terminal (p. ej. el `!` de Claude Code) `read` recibe EOF y `set -e` saldría sin decir nada.
if [ ! -t 0 ]; then
  echo "Hace falta una terminal interactiva para pedir la contraseña: ejecútalo en tu terminal." >&2
  exit 1
fi
read -r -s -p "Contraseña de $EMAIL en $ENDPOINT: " PASSWORD
echo
CODE="$(jq -n --arg e "$EMAIL" --arg p "$PASSWORD" '{action: "auth.login", email: $e, password: $p}' | call)"
unset PASSWORD
if [ "$CODE" != "200" ]; then
  echo "Login fallido (HTTP $CODE): $(cat "$WORK/resp")" >&2
  exit 1
fi

# ── 3. El proyecto ─────────────────────────────────────────────────────────────
CODE="$(echo '{"action":"project.list"}' | call)"
if [ "$CODE" != "200" ]; then
  echo "project.list falló (HTTP $CODE): $(cat "$WORK/resp")" >&2
  exit 1
fi
PROJECT_ID="$(jq -r --arg s "$SLUG" '.projects[] | select(.slug == $s) | .id' "$WORK/resp")"
if [ -z "$PROJECT_ID" ]; then
  echo "No hay proyecto '$SLUG' visible para $EMAIL en $ENDPOINT" >&2
  exit 1
fi
echo "Proyecto $SLUG → id $PROJECT_ID"

# ── 4. Un upsert por evento ────────────────────────────────────────────────────
OK=0
FAILED=0
while IFS= read -r event; do
  name="$(jq -r '.name' <<<"$event")"
  CODE="$(jq -c --argjson pid "$PROJECT_ID" '{action: "catalog.upsert", project_id: $pid} + .' <<<"$event" | call)"
  if [ "$CODE" = "200" ]; then
    OK=$((OK + 1))
  else
    FAILED=$((FAILED + 1))
    echo "  ✗ $name (HTTP $CODE): $(cat "$WORK/resp")" >&2
  fi
done < <(jq -c '.[]' "$CATALOG_JSON")

# Cerrar la sesión en el servidor también (la cookie se borra de todas formas con el temporal).
echo '{"action":"auth.logout"}' | call >/dev/null || true

echo "Declarados: $OK / $TOTAL  (fallidos: $FAILED)"
[ "$FAILED" -eq 0 ]
