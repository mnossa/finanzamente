#!/bin/bash
# Wrapper: esegue PHP nel container Docker convertendo path host → /var/www
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
CONTAINER_DIR="/var/www"

ARGS=()
for arg in "$@"; do
    ARGS+=("${arg/$ROOT_DIR/$CONTAINER_DIR}")
done

cd "$ROOT_DIR" && docker compose exec -T app php "${ARGS[@]}"
