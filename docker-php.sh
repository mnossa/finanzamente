#!/bin/bash
# Wrapper script per eseguire PHP nel container Docker
# Converte i path del sistema host in path del container

# Directory del progetto sul sistema host e nel container
HOST_DIR="/home/mnossa/www/finanzamente"
CONTAINER_DIR="/var/www"

# Converti tutti gli argomenti sostituendo il path host con quello del container
ARGS=()
for arg in "$@"; do
    ARGS+=("${arg/$HOST_DIR/$CONTAINER_DIR}")
done

# Esegui PHP nel container
cd "$HOST_DIR" && docker compose exec -T app php "${ARGS[@]}"
