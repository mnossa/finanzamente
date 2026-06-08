#!/usr/bin/env bash
# Importa un dump (plain .sql.gz o cifrato .sql.gz.enc) nel MySQL locale (docker compose db).
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${ROOT_DIR}/storage/backups"
DUMP_FILE="${1:-}"

if [[ -z "${DUMP_FILE}" ]]; then
    shopt -s nullglob
    candidates=("${BACKUP_DIR}"/prod-*.sql.gz.enc "${BACKUP_DIR}"/prod-*.sql.gz)
    shopt -u nullglob
    if [[ ${#candidates[@]} -eq 0 ]]; then
        echo "[!] Nessun dump in storage/backups/. Scarica prima con: make db-pull-prod" >&2
        echo "    Oppure: make db-import-local FILE=storage/backups/prod-....sql.gz" >&2
        exit 1
    fi
    DUMP_FILE="$(ls -1t "${candidates[@]}" | head -1)"
    echo "[i] FILE non specificato, uso il più recente: ${DUMP_FILE#"${ROOT_DIR}/"}"
fi

if [[ ! -f "${DUMP_FILE}" ]]; then
    echo "[!] File non trovato: ${DUMP_FILE}" >&2
    exit 1
fi

ENV_FILE="${ROOT_DIR}/.env.db-pull"
if [[ -f "${ENV_FILE}" ]]; then
    # shellcheck disable=SC1090
    source "${ENV_FILE}"
fi

LOCAL_DB_CONTAINER="${LOCAL_DB_CONTAINER:-finanzamente-db}"
LOCAL_DB_NAME="${LOCAL_DB_NAME:-finanzamente}"
LOCAL_DB_USER="${LOCAL_DB_USER:-root}"
LOCAL_DB_PASSWORD="${LOCAL_DB_PASSWORD:-root}"

if ! docker ps --format '{{.Names}}' | grep -qx "${LOCAL_DB_CONTAINER}"; then
    echo "[!] Container ${LOCAL_DB_CONTAINER} non attivo. Esegui: make up" >&2
    exit 1
fi

echo "[!] ATTENZIONE: sovrascriverà il database locale '${LOCAL_DB_NAME}'."
read -r -p "Continuare? [y/N] " CONFIRM
if [[ "${CONFIRM}" != "y" && "${CONFIRM}" != "Y" ]]; then
    echo "Annullato."
    exit 0
fi

mysql_local() {
    docker exec -i "${LOCAL_DB_CONTAINER}" mysql -u"${LOCAL_DB_USER}" --password="${LOCAL_DB_PASSWORD}" "$@"
}

# Dump prod include GTID_PURGED: su re-import locale collide con GTID_EXECUTED già presenti.
strip_gtid_from_dump() {
    sed -E '/^SET @@GLOBAL\.GTID_PURGED/d; /^SET @@SESSION\.SQL_LOG_BIN[[:space:]]*=/d'
}

echo "[+] Ricreo il database '${LOCAL_DB_NAME}'..."
mysql_local -e "DROP DATABASE IF EXISTS \`${LOCAL_DB_NAME}\`; CREATE DATABASE \`${LOCAL_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "[+] Import in corso..."
if [[ "${DUMP_FILE}" == *.enc ]]; then
    : "${BACKUP_ENCRYPTION_KEY:?BACKUP_ENCRYPTION_KEY richiesto per dump cifrati}"
    openssl enc -d -aes-256-cbc -pbkdf2 -pass pass:"${BACKUP_ENCRYPTION_KEY}" -in "${DUMP_FILE}" \
        | gunzip \
        | strip_gtid_from_dump \
        | mysql_local "${LOCAL_DB_NAME}"
else
    gunzip -c "${DUMP_FILE}" \
        | strip_gtid_from_dump \
        | mysql_local "${LOCAL_DB_NAME}"
fi

echo "[+] Import completato in ${LOCAL_DB_NAME}."
echo "[i] Poi anonimizza con: make db-anonymize"
echo "[i] Login dopo anonimizzazione: dev@finanzamente.local / password (porta 8080)"
