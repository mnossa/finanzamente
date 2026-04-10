#!/bin/sh
set -e

# =============================================================================
# Entrypoint del container di produzione Finanzamente
#
# Esegue l'inizializzazione Laravel al primo avvio e poi delega al CMD.
# Imposta SKIP_INIT=true per bypassare (es. container scheduler).
# =============================================================================

if [ "${SKIP_INIT}" != "true" ]; then
    echo "==> [entrypoint] Inizializzazione Laravel..."

    # Crea il link simbolico storage → public/storage (idempotente)
    if [ ! -L "/var/www/public/storage" ]; then
        echo "    → storage:link"
        php artisan storage:link --force
    fi

    # Esegue le migrazioni (safe: le migrazioni già eseguite vengono saltate)
    echo "    → migrate"
    php artisan migrate --force --no-interaction

    # Ottimizza la cache di configurazione, rotte e viste
    echo "    → optimize"
    php artisan optimize

    echo "==> [entrypoint] Inizializzazione completata."
fi

echo "==> [entrypoint] Avvio: $*"
exec "$@"
