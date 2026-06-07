#!/usr/bin/env bash
# Scarica un dump MySQL da produzione e lo salva in storage/backups/.
# Configurazione: copia .env.db-pull.example → .env.db-pull (gitignored).
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env.db-pull"
BACKUP_DIR="${ROOT_DIR}/storage/backups"

if [[ ! -f "${ENV_FILE}" ]]; then
    echo "[!] Manca ${ENV_FILE}. Copia .env.db-pull.example e compila i valori." >&2
    exit 1
fi

# shellcheck disable=SC1090
source "${ENV_FILE}"

: "${PROD_SSH_HOST:?PROD_SSH_HOST richiesto in .env.db-pull}"
: "${PROD_SSH_USER:?PROD_SSH_USER richiesto in .env.db-pull}"
PROD_SSH_PORT="${PROD_SSH_PORT:-22}"
PROD_COMPOSE_DIR="${PROD_COMPOSE_DIR:-/opt/finanzamente}"
PROD_COMPOSE_FILE="${PROD_COMPOSE_FILE:-docker-compose.prod.yml}"
PROD_DB_SERVICE="${PROD_DB_SERVICE:-db}"
SSH_OPTS=(-p "${PROD_SSH_PORT}" -o BatchMode=yes -o StrictHostKeyChecking=accept-new)

if [[ -n "${PROD_SSH_KEY:-}" && -f "${PROD_SSH_KEY}" ]]; then
    SSH_OPTS+=(-i "${PROD_SSH_KEY}")
fi

ssh_remote() {
    ssh "${SSH_OPTS[@]}" "${PROD_SSH_USER}@${PROD_SSH_HOST}" "$@"
}

mkdir -p "${BACKUP_DIR}"
STAMP="$(date +%Y%m%d-%H%M%S)"
OUTPUT_PLAIN="${BACKUP_DIR}/prod-${STAMP}.sql.gz"
OUTPUT_ENC="${BACKUP_DIR}/prod-${STAMP}.sql.gz.enc"

echo "[+] Connessione a ${PROD_SSH_USER}@${PROD_SSH_HOST}:${PROD_SSH_PORT}..."

if [[ "${DB_PULL_MODE:-dump}" == "latest-backup" ]]; then
    : "${BACKUP_ENCRYPTION_KEY:?BACKUP_ENCRYPTION_KEY richiesto per DB_PULL_MODE=latest-backup}"
    echo "[+] Recupero ultimo backup cifrato dal volume produzione..."
    LATEST_ENC="$(ssh_remote bash -s <<EOF
set -euo pipefail
cd "${PROD_COMPOSE_DIR}"
BACKUP_VOL="\$(docker compose -f ${PROD_COMPOSE_FILE} config --volumes | grep -E 'backup' | head -1 || true)"
if [[ -z "\${BACKUP_VOL}" ]]; then
  echo "Nessun volume backup trovato in compose" >&2
  exit 1
fi
docker run --rm -v "\${BACKUP_VOL}:/backups:ro" alpine sh -c 'ls -1t /backups/*.sql.gz.enc 2>/dev/null | head -1'
EOF
)"
    if [[ -z "${LATEST_ENC}" ]]; then
        echo "[!] Nessun backup .sql.gz.enc trovato sul server." >&2
        exit 1
    fi
    echo "[+] Backup remoto: ${LATEST_ENC}"
    ssh_remote bash -s <<EOF > "${OUTPUT_ENC}"
set -euo pipefail
cd "${PROD_COMPOSE_DIR}"
BACKUP_VOL="\$(docker compose -f ${PROD_COMPOSE_FILE} config --volumes | grep -E 'backup' | head -1)"
docker run --rm -v "\${BACKUP_VOL}:/backups:ro" alpine cat '${LATEST_ENC}'
EOF
    echo "[+] Salvato: ${OUTPUT_ENC}"
    echo "[i] Per importare: make db-import-local FILE=${OUTPUT_ENC}"
    exit 0
fi

echo "[+] mysqldump diretto dal container DB produzione..."
ssh_remote bash -s <<EOF | gzip > "${OUTPUT_PLAIN}"
set -euo pipefail
cd "${PROD_COMPOSE_DIR}"
set -a
source .env
set +a

# Root (DB_ROOT_PASSWORD): privilegi completi, dump consistente.
# App user: stessi flag del backup notturno prod — niente FLUSH TABLES.
if [[ -n "\${DB_ROOT_PASSWORD:-}" ]]; then
  echo "[i] Dump con utente root (DB_ROOT_PASSWORD)" >&2
  docker compose -f ${PROD_COMPOSE_FILE} exec -T ${PROD_DB_SERVICE} \\
    mysqldump -uroot --password="\${DB_ROOT_PASSWORD}" \\
      --single-transaction --skip-lock-tables --no-tablespaces \\
      --routines --triggers "\${DB_DATABASE}"
else
  echo "[i] Dump con utente app (DB_USERNAME) — senza --single-transaction" >&2
  docker compose -f ${PROD_COMPOSE_FILE} exec -T ${PROD_DB_SERVICE} \\
    mysqldump -u"\${DB_USERNAME}" --password="\${DB_PASSWORD}" \\
      --skip-lock-tables --no-tablespaces \\
      --routines --triggers "\${DB_DATABASE}"
fi
EOF

echo "[+] Salvato: ${OUTPUT_PLAIN}"
echo "[i] Per importare: make db-import-local FILE=${OUTPUT_PLAIN}"
