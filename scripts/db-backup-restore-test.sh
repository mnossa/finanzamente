#!/usr/bin/env bash
# Verifica locale che dump MySQL sia ripristinabile (WFI-100 / Opzione A).
# Richiede stack dev: make up
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

DB_USER="${DB_USERNAME:-finanzamente}"
DB_PASS="${DB_PASSWORD:-finanzamente}"
DB_NAME="${DB_DATABASE:-finanzamente}"
RESTORE_DB="${RESTORE_DB_NAME:-finanzamente_restore_test}"
DUMP_FILE="/tmp/finanzamente-test-backup-$$.sql.gz"

cleanup() {
    rm -f "$DUMP_FILE"
    docker compose exec -T db mysql -uroot --password=root \
        -e "DROP DATABASE IF EXISTS \`${RESTORE_DB}\`;" 2>/dev/null || true
}
trap cleanup EXIT

echo "[+] Dump da ${DB_NAME}..."
docker compose exec -T db mysqldump -u"${DB_USER}" --password="${DB_PASS}" \
    --single-transaction --set-gtid-purged=OFF --no-tablespaces \
    "${DB_NAME}" \
    | gzip > "$DUMP_FILE"

echo "[+] Conteggio righe origine..."
ORIG_USERS="$(docker compose exec -T db mysql -u"${DB_USER}" --password="${DB_PASS}" "${DB_NAME}" -N -e "SELECT COUNT(*) FROM users;")"
ORIG_ARTICLES="$(docker compose exec -T db mysql -u"${DB_USER}" --password="${DB_PASS}" "${DB_NAME}" -N -e "SELECT COUNT(*) FROM magazine_articles;")"
echo "    users=${ORIG_USERS} magazine_articles=${ORIG_ARTICLES}"

echo "[+] Ripristino su ${RESTORE_DB}..."
docker compose exec -T db mysql -uroot --password=root \
    -e "DROP DATABASE IF EXISTS \`${RESTORE_DB}\`; CREATE DATABASE \`${RESTORE_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

gunzip -c "$DUMP_FILE" \
    | docker compose exec -T db mysql -uroot --password=root "${RESTORE_DB}"

echo "[+] Conteggio righe restore..."
REST_USERS="$(docker compose exec -T db mysql -uroot --password=root "${RESTORE_DB}" -N -e "SELECT COUNT(*) FROM users;")"
REST_ARTICLES="$(docker compose exec -T db mysql -uroot --password=root "${RESTORE_DB}" -N -e "SELECT COUNT(*) FROM magazine_articles;")"
echo "    users=${REST_USERS} magazine_articles=${REST_ARTICLES}"

if [[ "$ORIG_USERS" != "$REST_USERS" ]] || [[ "$ORIG_ARTICLES" != "$REST_ARTICLES" ]]; then
    echo "[!] ERRORE: row count non corrisponde dopo restore" >&2
    exit 1
fi

echo "[+] OK: backup/restore verificato"
