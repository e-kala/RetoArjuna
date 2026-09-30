#!/usr/bin/env bash
set -Eeuo pipefail

# Despliegue manual por FTP para los dos entornos remotos de Reto Arjuna.
# La única variable que se cambia entre destinos es DEPLOY_ENTORNO en .env:
#   DEPLOY_ENTORNO=pruebas
#   DEPLOY_ENTORNO=produccion
#
# Cada servidor conserva su propio plataforma/backend/config.local.php. Este
# script lo excluye siempre, junto con credenciales, uploads y datos runtime.

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${DEPLOY_ENV_FILE:-$PROJECT_ROOT/.env}"
DEPLOY_ENTORNO_OVERRIDE="${DEPLOY_ENTORNO:-}"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Error: no existe $ENV_FILE" >&2
  exit 1
fi

set -a
# shellcheck disable=SC1090
source "$ENV_FILE"
set +a

if [[ -n "$DEPLOY_ENTORNO_OVERRIDE" ]]; then
  DEPLOY_ENTORNO="$DEPLOY_ENTORNO_OVERRIDE"
fi

for required in DEPLOY_ENTORNO FTP_HOST FTP_USER FTP_PASS FTP_PUERTO; do
  if [[ -z "${!required:-}" ]]; then
    echo "Error: falta $required en $ENV_FILE" >&2
    exit 1
  fi
done

case "$DEPLOY_ENTORNO" in
  pruebas)
    REMOTE_ROOT="/pruebas.arjuna.mx"
    PUBLIC_URL="https://pruebas.arjuna.mx/"
    ;;
  produccion)
    REMOTE_ROOT="/public_html"
    PUBLIC_URL="https://arjuna.mx/"
    ;;
  *)
    echo "Error: DEPLOY_ENTORNO debe ser 'pruebas' o 'produccion'." >&2
    exit 1
    ;;
esac

FTP_PROTOCOLO="${FTP_PROTOCOLO:-ftp}"
if [[ "$FTP_PROTOCOLO" != "ftp" ]]; then
  echo "Error: este hosting está configurado para FTP clásico; protocolo recibido: $FTP_PROTOCOLO" >&2
  exit 1
fi

DRY_RUN=0
ASSUME_YES=0
DEPLOY_ALL=0
declare -a REQUESTED_FILES=()

while (($#)); do
  case "$1" in
    --dry-run) DRY_RUN=1 ;;
    --yes) ASSUME_YES=1 ;;
    --all) DEPLOY_ALL=1 ;;
    --help|-h)
      sed -n '2,12p' "$0"
      echo
      echo "Uso: scripts/deploy_ftp.sh [--dry-run] [--all] [--yes] [archivo ...]"
      exit 0
      ;;
    --*)
      echo "Error: opción desconocida: $1" >&2
      exit 1
      ;;
    *) REQUESTED_FILES+=("$1") ;;
  esac
  shift
done

if ((DEPLOY_ALL)) && ((${#REQUESTED_FILES[@]})); then
  echo "Error: --all no se combina con una lista explícita de archivos." >&2
  exit 1
fi

cd "$PROJECT_ROOT"

declare -a CANDIDATE_FILES=()
if ((${#REQUESTED_FILES[@]})); then
  CANDIDATE_FILES=("${REQUESTED_FILES[@]}")
elif ((DEPLOY_ALL)); then
  mapfile -t CANDIDATE_FILES < <(git ls-files)
else
  mapfile -t CANDIDATE_FILES < <(git diff-tree --no-commit-id --name-only --diff-filter=ACMRT -r HEAD)
fi

is_protected_path() {
  case "$1" in
    .env|.env.*|credenciales.txt|notas.txt|*.md|*.code-workspace|*.txt|*.sql|*.odg|*.xcf|*.zip|*.pdf|scripts/*) return 0 ;;
    logs/*|matomo/*|plataforma/vendor/*|plataforma/uploads/*|plataforma/backend/config.local.php|plataforma/backend/.deploy_estado.json) return 0 ;;
    *) return 1 ;;
  esac
}

declare -a FILES=()
for file in "${CANDIDATE_FILES[@]}"; do
  if [[ "$file" = /* || "$file" == *".."* ]]; then
    echo "Error: ruta no permitida: $file" >&2
    exit 1
  fi
  if is_protected_path "$file"; then
    echo "Omitido (protegido): $file"
    continue
  fi
  if [[ ! -f "$file" ]]; then
    echo "Omitido (no es archivo regular): $file"
    continue
  fi
  FILES+=("$file")
done

if ((${#FILES[@]} == 0)); then
  echo "No hay archivos desplegables en la selección."
  exit 0
fi

echo "Entorno: $DEPLOY_ENTORNO"
echo "Destino FTP: $REMOTE_ROOT"
echo "Archivos (${#FILES[@]}):"
printf '  - %s\n' "${FILES[@]}"

if ((DRY_RUN)); then
  echo "Dry run completo: no se modificó el servidor."
  exit 0
fi

if [[ "$DEPLOY_ENTORNO" == "produccion" ]] && ((ASSUME_YES == 0)); then
  if [[ ! -t 0 ]]; then
    echo "Error: producción requiere confirmación interactiva o --yes." >&2
    exit 1
  fi
  read -r -p "Escribe PRODUCCION para continuar: " confirmation
  if [[ "$confirmation" != "PRODUCCION" ]]; then
    echo "Despliegue cancelado."
    exit 1
  fi
fi

FTP_BASE="ftp://$FTP_HOST:$FTP_PUERTO"
CURL_FTP=(curl --silent --show-error --fail --user "$FTP_USER:$FTP_PASS")

# Preflight: ambos entornos válidos contienen este router. Evita subir por
# error a la raíz de la cuenta o a otro subdominio.
"${CURL_FTP[@]}" "$FTP_BASE$REMOTE_ROOT/plataforma/index.php" -o /dev/null

TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="/tmp/retoarjuna-deploy-backups/${DEPLOY_ENTORNO}-${TIMESTAMP}"
VERIFY_DIR="$(mktemp -d "/tmp/retoarjuna-verify-${DEPLOY_ENTORNO}.XXXXXX")"
mkdir -p "$BACKUP_DIR"

declare -a UPLOADED=()

rollback() {
  local file
  set +e
  echo "Falló el despliegue; restaurando archivos ya reemplazados..." >&2
  for file in "${UPLOADED[@]}"; do
    if [[ -f "$BACKUP_DIR/$file" ]]; then
      "${CURL_FTP[@]}" --ftp-create-dirs --upload-file "$BACKUP_DIR/$file" "$FTP_BASE$REMOTE_ROOT/$file" >/dev/null
      echo "  restaurado: $file" >&2
    else
      echo "  aviso: $file era nuevo y no se eliminó automáticamente" >&2
    fi
  done
}

cleanup() {
  rm -rf "$VERIFY_DIR"
}

on_exit() {
  local exit_code=$?
  if ((exit_code != 0)); then rollback; fi
  cleanup
  exit "$exit_code"
}
trap on_exit EXIT

for file in "${FILES[@]}"; do
  mkdir -p "$BACKUP_DIR/$(dirname "$file")" "$VERIFY_DIR/$(dirname "$file")"

  if "${CURL_FTP[@]}" "$FTP_BASE$REMOTE_ROOT/$file" -o "$BACKUP_DIR/$file"; then
    :
  else
    rm -f "$BACKUP_DIR/$file"
    echo "  nuevo en destino: $file"
  fi

  "${CURL_FTP[@]}" --ftp-create-dirs --upload-file "$file" "$FTP_BASE$REMOTE_ROOT/$file"
  UPLOADED+=("$file")
  "${CURL_FTP[@]}" "$FTP_BASE$REMOTE_ROOT/$file" -o "$VERIFY_DIR/$file"

  if [[ "$(sha256sum "$file" | cut -d' ' -f1)" != "$(sha256sum "$VERIFY_DIR/$file" | cut -d' ' -f1)" ]]; then
    echo "Error: la verificación SHA-256 falló para $file" >&2
    exit 1
  fi
  echo "Subido y verificado: $file"
done

curl --silent --show-error --fail --location --max-time 20 "$PUBLIC_URL" -o /dev/null

trap - EXIT
cleanup
echo "Despliegue completo en $DEPLOY_ENTORNO."
echo "Respaldo remoto anterior: $BACKUP_DIR"
