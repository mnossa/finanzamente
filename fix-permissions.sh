#!/bin/bash
# Corregge ownership/permessi dopo create file nei container Docker.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
OWNER="$(id -un):$(id -gn)"

echo "Correzione permessi in $ROOT_DIR (owner $OWNER)..."

sudo chown -R "$OWNER" "$ROOT_DIR"

chmod -R 775 "$ROOT_DIR/storage" "$ROOT_DIR/bootstrap/cache"

chmod 600 "$ROOT_DIR/.env" 2>/dev/null || true
chmod 600 "$ROOT_DIR/.env.docker" 2>/dev/null || true

if [ -f "$ROOT_DIR/public/hot" ]; then
    rm -f "$ROOT_DIR/public/hot"
fi

echo "Permessi aggiornati."
