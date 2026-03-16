#!/usr/bin/env bash
# =============================================================================
# scripts/raspberry-deploy.sh
# Aggiornamento automatico di Finanzamente sul Raspberry Pi
# =============================================================================
#
# Questo script controlla se è disponibile una nuova release su GitHub,
# la scarica e installa/aggiorna i file necessari in modo idempotente
# e sicuro, con backup automatico e rollback in caso di errore.
#
# Requisiti: curl, jq, docker, docker compose (plugin V2)
#
# Installazione cron (ogni 6 ore) — sostituisci /home/mnossa con la home dell'utente reale:
#   0 */6 * * * /home/mnossa/scripts/raspberry-deploy.sh \
#               >> /home/mnossa/logs/finanzamente-deploy.log 2>&1
#
# Variabili d'ambiente opzionali:
#   GITHUB_TOKEN  Token GitHub (necessario per repo privati; senza token GitHub
#                 restituisce 404 anche se il repository esiste)
#   INSTALL_DIR   Directory di installazione (default: /home/mnossa/www/finanzamente)
# =============================================================================

set -euo pipefail

# ── Configurazione ────────────────────────────────────────────────────────────
INSTALL_DIR="/home/mnossa/finanzamente"
readonly BACKUP_DIR="${INSTALL_DIR}.backup"
readonly WORK_DIR="/tmp/finanzamente-deploy"
readonly VERSION_FILE="${INSTALL_DIR}/.release-archive"
readonly GITHUB_REPO="mnossa/finanzamente"
readonly GITHUB_REPO_API="https://api.github.com/repos/${GITHUB_REPO}"
readonly GITHUB_RELEASE_API="${GITHUB_REPO_API}/releases/latest"
readonly LOG_TAG="finanzamente-deploy"

# Token GitHub opzionale (necessario solo per repo privati)
GITHUB_TOKEN=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

# Numero massimo di tentativi di attesa avvio container (5s per tentativo = 120s totali)
readonly MAX_RETRIES=24

# ── Funzioni di utilità ───────────────────────────────────────────────────────
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [${LOG_TAG}] $1"
}

error() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [${LOG_TAG}] ERRORE: $1" >&2
}

# ── Verifica prerequisiti ─────────────────────────────────────────────────────
check_prerequisites() {
    local missing=0
    for cmd in curl jq docker; do
        if ! command -v "${cmd}" > /dev/null 2>&1; then
            error "Comando '${cmd}' non trovato. Installalo prima di continuare."
            missing=1
        fi
    done

    if ! docker compose version > /dev/null 2>&1; then
        error "Docker Compose (plugin V2) non trovato. Installalo con: apt install docker-compose-plugin"
        missing=1
    fi

    [ "${missing}" -eq 0 ] || exit 1
}

# ── Esegue una chiamata GET all'API GitHub ────────────────────────────────────
# Uso: github_api_get <url>
# Stampa il body della risposta + \n + codice HTTP sull'ultima riga.
github_api_get() {
    local url="$1"
    if [ -n "${GITHUB_TOKEN}" ]; then
        curl -s -w "\n%{http_code}" \
            -H "Accept: application/vnd.github.v3+json" \
            -H "Authorization: Bearer ${GITHUB_TOKEN}" \
            "${url}" 2>/dev/null
    else
        curl -s -w "\n%{http_code}" \
            -H "Accept: application/vnd.github.v3+json" \
            "${url}" 2>/dev/null
    fi
}

# ── Recupera info release più recente da GitHub ───────────────────────────────
get_latest_release() {
    local response http_code body
    response=$(github_api_get "${GITHUB_RELEASE_API}") || return 1

    http_code=$(printf '%s\n' "${response}" | tail -n 1)
    body=$(printf '%s\n' "${response}" | head -n -1)

    case "${http_code}" in
        200)
            printf '%s\n' "${body}"
            ;;
        404)
            # Verifica se il repo esiste ma non ha release, oppure è inaccessibile
            local repo_response repo_code
            repo_response=$(github_api_get "${GITHUB_REPO_API}") || return 1
            repo_code=$(printf '%s\n' "${repo_response}" | tail -n 1)
            if [ "${repo_code}" = "200" ]; then
                echo "{}"
            else
                error "Repository GitHub non accessibile (HTTP ${repo_code}). Se il repository e' privato, imposta GITHUB_TOKEN con permesso di lettura 'Contents'."
                return 1
            fi
            ;;
        *)
            error "GitHub API ha risposto con HTTP ${http_code}."
            return 1
            ;;
    esac
}

# ── Rollback in caso di errore ────────────────────────────────────────────────
rollback() {
    error "Deploy fallito. Avvio procedura di rollback..."

    if [ -d "${BACKUP_DIR}" ]; then
        log "Ripristino backup da ${BACKUP_DIR}..."

        # Ferma i container correnti (ignora errori)
        if [ -d "${INSTALL_DIR}" ]; then
            cd "${INSTALL_DIR}" && docker compose down --timeout 30 2>/dev/null || true
        fi

        # Sostituisci l'installazione attuale con il backup
        rm -rf "${INSTALL_DIR}"
        mv "${BACKUP_DIR}" "${INSTALL_DIR}"

        # Riavvia con la versione precedente
        cd "${INSTALL_DIR}"
        docker compose up -d
        log "Rollback completato. Webapp ripristinata alla versione precedente."
    else
        error "Nessun backup disponibile per il rollback. Intervento manuale necessario."
    fi

    rm -rf "${WORK_DIR}"
    exit 1
}

# ── Script principale ─────────────────────────────────────────────────────────
main() {
    log "=== Avvio controllo aggiornamenti Finanzamente ==="

    check_prerequisites

    # ── Recupera info release più recente da GitHub ──────────────────────────
    log "Controllo release disponibili su GitHub..."
    local release_json
    if ! release_json=$(get_latest_release); then
        error "Impossibile recuperare la release piu' recente. Verifica connessione internet, permessi del token GITHUB_TOKEN e presenza di almeno una GitHub Release pubblicata."
        exit 1
    fi

    local latest_version asset_name asset_url
    latest_version=$(echo "${release_json}" | jq -r '.tag_name // empty')
    asset_name=$(echo "${release_json}"     | jq -r '.assets[0].name // empty')
    # Per repo privati si usa l'API URL (campo .url) con Accept: application/octet-stream.
    # browser_download_url non funziona con Bearer token su redirect S3.
    asset_url=$(echo "${release_json}"      | jq -r '.assets[0].url // empty')

    if [ -z "${latest_version}" ]; then
        log "Nessuna GitHub Release pubblicata per ${GITHUB_REPO}. L'endpoint /releases/latest restituisce 404 finche' non esiste almeno una release. Uscita."
        exit 0
    fi

    if [ -z "${asset_url}" ]; then
        error "Release ${latest_version} trovata, ma nessun asset da scaricare."
        exit 1
    fi

    # ── Confronta con la versione installata ─────────────────────────────────
    local current_version=""
    if [ -f "${VERSION_FILE}" ]; then
        current_version=$(cat "${VERSION_FILE}")
    fi

    if [ "${latest_version}" = "${current_version}" ]; then
        log "Versione già aggiornata: ${current_version}. Nessun aggiornamento necessario."
        exit 0
    fi

    log "Nuova versione disponibile: ${latest_version} (installata: ${current_version:-nessuna})"
    log "Download archivio: ${asset_name}..."

    # ── Prepara directory di lavoro ───────────────────────────────────────────
    rm -rf "${WORK_DIR}"
    mkdir -p "${WORK_DIR}"

    # ── Scarica archivio release ──────────────────────────────────────────────
    local archive_path="${WORK_DIR}/${asset_name}"
    local dl_ok=0
    if [ -n "${GITHUB_TOKEN}" ]; then
        curl -sfL -o "${archive_path}" \
            -H "Accept: application/octet-stream" \
            -H "Authorization: Bearer ${GITHUB_TOKEN}" \
            "${asset_url}" && dl_ok=1
    else
        curl -sfL -o "${archive_path}" \
            -H "Accept: application/octet-stream" \
            "${asset_url}" && dl_ok=1
    fi

    if [ "${dl_ok}" -eq 0 ]; then
        error "Download dell'archivio fallito."
        rm -rf "${WORK_DIR}"
        exit 1
    fi

    log "Download completato: $(du -sh "${archive_path}" | cut -f1)"

    # ── Estrai archivio nella directory di lavoro ─────────────────────────────
    local extract_dir="${WORK_DIR}/release"
    mkdir -p "${extract_dir}"

    if ! tar -xzf "${archive_path}" -C "${extract_dir}"; then
        error "Estrazione archivio fallita."
        rm -rf "${WORK_DIR}"
        exit 1
    fi

    log "Archivio estratto. Avvio procedura di aggiornamento..."

    # Da qui in poi: qualsiasi errore avvia il rollback automatico
    trap rollback ERR

    # ── Backup installazione corrente ─────────────────────────────────────────
    if [ -d "${INSTALL_DIR}" ]; then
        log "Backup installazione corrente in ${BACKUP_DIR}..."
        rm -rf "${BACKUP_DIR}"
        cp -a "${INSTALL_DIR}" "${BACKUP_DIR}"
    fi

    # ── Salva file di configurazione e dati utente ───────────────────────────
    local user_data_dir="${WORK_DIR}/user-data"
    mkdir -p "${user_data_dir}"

    # Preserva il file .env.docker (contiene segreti configurati dall'utente)
    if [ -f "${INSTALL_DIR}/.env.docker" ]; then
        cp "${INSTALL_DIR}/.env.docker" "${user_data_dir}/.env.docker"
        log "Configurazione .env.docker salvata."
    fi

    # Preserva i file caricati dagli utenti (allegati, ricevute, ecc.)
    if [ -d "${INSTALL_DIR}/storage/app" ]; then
        cp -a "${INSTALL_DIR}/storage/app" "${user_data_dir}/storage-app"
        log "File utente (storage/app) salvati."
    fi

    # ── Ferma i container attuali ─────────────────────────────────────────────
    if [ -d "${INSTALL_DIR}" ]; then
        log "Arresto container Docker in corso..."
        cd "${INSTALL_DIR}"
        docker compose down --timeout 30 2>/dev/null || true
    fi

    # ── Installa la nuova versione ────────────────────────────────────────────
    log "Installazione nuova versione ${latest_version}..."
    rm -rf "${INSTALL_DIR}"
    mv "${extract_dir}" "${INSTALL_DIR}"

    # ── Ripristina file di configurazione utente ──────────────────────────────
    if [ -f "${user_data_dir}/.env.docker" ]; then
        cp "${user_data_dir}/.env.docker" "${INSTALL_DIR}/.env.docker"
        log "Configurazione .env.docker ripristinata."
    else
        log "AVVISO: .env.docker non trovato nel backup."
        if [ -f "${INSTALL_DIR}/.env.example" ]; then
            cp "${INSTALL_DIR}/.env.example" "${INSTALL_DIR}/.env.docker"
            log "Template .env.example copiato come .env.docker."
            log "AZIONE RICHIESTA: configura ${INSTALL_DIR}/.env.docker con i parametri corretti."
        fi
    fi

    # Ripristina i file caricati dagli utenti
    if [ -d "${user_data_dir}/storage-app" ]; then
        rm -rf "${INSTALL_DIR}/storage/app"
        cp -a "${user_data_dir}/storage-app" "${INSTALL_DIR}/storage/app"
        log "File utente (storage/app) ripristinati."
    fi

    # ── Imposta permessi corretti ─────────────────────────────────────────────
    chmod -R 775 "${INSTALL_DIR}/storage"
    chmod -R 775 "${INSTALL_DIR}/bootstrap/cache"
    chmod 600 "${INSTALL_DIR}/.env.docker" 2>/dev/null || true

    # ── Avvia stack Docker con la nuova versione ──────────────────────────────
    cd "${INSTALL_DIR}"
    log "Build e avvio container Docker..."
    docker compose up -d --build

    # ── Attendi che il container app sia pronto ───────────────────────────────
    log "Attesa avvio container app..."
    local retries=0
    until docker compose exec -T app php artisan --version > /dev/null 2>&1; do
        retries=$((retries + 1))
        if [ "${retries}" -ge "${MAX_RETRIES}" ]; then
            error "Timeout: il container app non risponde dopo $((MAX_RETRIES * 5)) secondi."
            exit 1
        fi
        sleep 5
    done

    # ── Esegui migrazioni database ────────────────────────────────────────────
    log "Esecuzione migrazioni database..."
    docker compose exec -T app php artisan migrate --force

    # ── Ottimizza Laravel per la produzione ───────────────────────────────────
    log "Ottimizzazione cache Laravel..."
    docker compose exec -T app php artisan optimize

    # ── Registra la versione installata ──────────────────────────────────────
    echo "${latest_version}" > "${VERSION_FILE}"

    # Disabilita il trap di rollback (deploy riuscito)
    trap - ERR

    # ── Pulizia ───────────────────────────────────────────────────────────────
    rm -rf "${WORK_DIR}"
    rm -rf "${BACKUP_DIR}"

    log "=== Deploy completato con successo. Versione installata: ${latest_version} ==="
}

main "$@"
