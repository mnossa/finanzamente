# Guida al Deploy — Finanzamente

## Indice

1. [Come funziona la pipeline CI/CD](#1-come-funziona-la-pipeline-cicd)
2. [Secret e configurazioni necessari](#2-secret-e-configurazioni-necessari)
3. [Primo deploy su Raspberry Pi](#3-primo-deploy-su-raspberry-pi)
4. [Aggiornamenti automatici via cron](#4-aggiornamenti-automatici-via-cron)
5. [Struttura dell'archivio di release](#5-struttura-dellarchivio-di-release)
6. [Assunzioni e limiti](#6-assunzioni-e-limiti)
7. [Troubleshooting](#7-troubleshooting)

---

## 1. Come funziona la pipeline CI/CD

Il file `.github/workflows/ci-cd.yml` definisce una pipeline automatica che si attiva
ad ogni **push sul branch `staging`**.

La pipeline è composta da tre job sequenziali:

### Job 1 — `test`: Esecuzione dei test

1. Effettua il checkout del codice sorgente.
2. Crea un file `.env.docker` temporaneo con i valori minimi per CI.
3. Avvia l'intero stack Docker Compose (`app`, `db`, `nginx`).
4. Installa le dipendenze PHP nel container (`composer install --no-dev`).
5. Esegue la suite di test con **`make test`** (usa SQLite in-memory, nessun dato di produzione toccato).
6. Ferma e rimuove i container e i volumi temporanei.

> Se anche un solo test fallisce, la pipeline si interrompe e i job successivi non vengono eseguiti.

### Job 2 — `build`: Build e pacchettizzazione

1. Calcola la **versione della release** nel formato `vYYYYMMDD-<sha-breve>` (es. `v20240315-abc1234f`).
2. Installa le dipendenze Node.js (`npm ci`) e compila il frontend con **Vite + TypeScript** (`npm run build`).
3. Installa le dipendenze PHP di produzione (`composer install --no-dev`).
4. Crea un archivio `.tar.gz` contenente tutto il necessario per il deploy (vedi [sezione 5](#5-struttura-dellarchivio-di-release)).
5. Carica l'archivio come artefatto CI (conservato per 7 giorni).

### Job 3 — `release`: Creazione Release GitHub

1. Scarica l'archivio prodotto dal job `build`.
2. Crea un **tag Git** e una **GitHub Release** con nome `vYYYYMMDD-<sha>`.
3. Allega l'archivio `.tar.gz` come asset della release.

---

## 2. Secret e configurazioni necessari

### GitHub Actions

La pipeline non richiede secret aggiuntivi: usa il `GITHUB_TOKEN` automatico di
GitHub Actions con il permesso `contents: write` per creare la release.

### Raspberry Pi — `.env.docker`

Sul Raspberry Pi, prima di avviare la webapp, crea il file
`/home/mnossa/www/finanzamente/.env.docker` a partire dal template:

```bash
cp /home/mnossa/www/finanzamente/.env.example \
   /home/mnossa/www/finanzamente/.env.docker
```

Configura obbligatoriamente le seguenti variabili:

| Variabile              | Descrizione                                          | Esempio                        |
|------------------------|------------------------------------------------------|--------------------------------|
| `APP_KEY`              | Chiave di cifratura Laravel (genera con artisan)     | `base64:...`                   |
| `APP_URL`              | URL pubblica della webapp                            | `http://192.168.1.100:8080`    |
| `APP_ENV`              | Ambiente applicativo                                 | `production`                   |
| `APP_DEBUG`            | Debug mode (disabilitare in produzione)              | `false`                        |
| `DB_DATABASE`          | Nome database MySQL                                  | `finanzamente`                 |
| `DB_USERNAME`          | Utente database MySQL                                | `finanzamente`                 |
| `DB_PASSWORD`          | Password database MySQL                              | *(scegli una password sicura)* |
| `ADV_THROTTLE_SALT`    | Salt per rate limiting GDPR-compliant                | *(stringa casuale sicura)*     |

Per generare `APP_KEY` al primo avvio:

```bash
cd /home/mnossa/www/finanzamente
docker compose exec app php artisan key:generate --show
```

Copia il valore ottenuto in `.env.docker` alla riga `APP_KEY=...`.

### Repo privato (opzionale)

Se il repository GitHub è privato, lo script di deploy richiede un
**Personal Access Token** con permesso `repo:read` e `contents:read`:

```bash
export GITHUB_TOKEN="ghp_xxxxxxxxxxxxxxxxxxxx"
```

Aggiungi questa riga al file `~/.bashrc` o direttamente nel crontab (vedi sezione 4).

---

## 3. Primo deploy su Raspberry Pi

### Prerequisiti

- Raspberry Pi con **Raspberry Pi OS** (o Debian-based) aggiornato
- **Docker Engine** installato ([guida ufficiale](https://docs.docker.com/engine/install/debian/))
- **Docker Compose Plugin V2** (`apt install docker-compose-plugin`)
- **curl**, **jq** installati (`apt install curl jq`)
- Accesso internet (anche solo in uscita verso GitHub)

### Installazione iniziale

1. **Crea le directory necessarie:**

   ```bash
   mkdir -p /home/mnossa/www
   mkdir -p /home/mnossa/scripts
   mkdir -p /home/mnossa/logs
   ```

2. **Scarica manualmente la prima release** dalla pagina
   [Releases](https://github.com/mnossa/finanzamente/releases) del repository,
   oppure tramite CLI:

   ```bash
   # Per repository pubblico
   RELEASE_URL=$(curl -s https://api.github.com/repos/mnossa/finanzamente/releases/latest \
     | jq -r '.assets[0].browser_download_url')
   curl -L -o /tmp/finanzamente-release.tar.gz "${RELEASE_URL}"

   # Per repository privato (usa GITHUB_TOKEN con permesso repo:read)
   RELEASE_URL=$(curl -s \
     -H "Authorization: Bearer ${GITHUB_TOKEN}" \
     https://api.github.com/repos/mnossa/finanzamente/releases/latest \
     | jq -r '.assets[0].browser_download_url')
   curl -L -o /tmp/finanzamente-release.tar.gz \
     -H "Authorization: Bearer ${GITHUB_TOKEN}" \
     "${RELEASE_URL}"
   ```

3. **Estrai l'archivio:**

   ```bash
   mkdir -p /home/mnossa/www/finanzamente
   tar -xzf /tmp/finanzamente-release.tar.gz -C /home/mnossa/www/finanzamente
   ```

4. **Configura le variabili d'ambiente:**

   ```bash
   cp /home/mnossa/www/finanzamente/.env.example \
      /home/mnossa/www/finanzamente/.env.docker

   nano /home/mnossa/www/finanzamente/.env.docker
   # Compila APP_KEY, APP_URL, DB_PASSWORD, ADV_THROTTLE_SALT, ecc.
   ```

5. **Imposta i permessi corretti:**

   ```bash
   chmod -R 775 /home/mnossa/www/finanzamente/storage
   chmod -R 775 /home/mnossa/www/finanzamente/bootstrap/cache
   chmod 600    /home/mnossa/www/finanzamente/.env.docker
   ```

6. **Avvia lo stack Docker:**

   ```bash
   cd /home/mnossa/www/finanzamente
   docker compose up -d --build
   ```

7. **Genera APP_KEY e aggiorna `.env.docker`:**

   ```bash
   docker compose exec app php artisan key:generate --show
   # Copia il valore e aggiornalo in .env.docker, poi:
   docker compose restart app
   ```

8. **Esegui le migrazioni del database:**

   ```bash
   docker compose exec app php artisan migrate --force
   ```

9. **Ottimizza Laravel:**

   ```bash
   docker compose exec app php artisan optimize
   ```

10. **Registra la versione installata:**

    ```bash
    # Sostituisci <versione> con il tag della release scaricata (es. v20240315-abc1234f)
    echo "<versione>" > /home/mnossa/www/finanzamente/.release-version
    ```

11. **Copia lo script di deploy:**

    ```bash
    cp /home/mnossa/www/finanzamente/scripts/raspberry-deploy.sh \
       /home/mnossa/scripts/raspberry-deploy.sh
    chmod +x /home/mnossa/scripts/raspberry-deploy.sh
    ```

---

## 4. Aggiornamenti automatici via cron

Lo script `scripts/raspberry-deploy.sh` controlla automaticamente se è disponibile
una nuova release e, se sì, la scarica e installa in modo sicuro.

### Configurazione cron

Apri il crontab dell'utente:

```bash
crontab -e
```

Aggiungi la riga per eseguire lo script ogni 6 ore:

```cron
0 */6 * * * /home/mnossa/scripts/raspberry-deploy.sh >> /home/mnossa/logs/finanzamente-deploy.log 2>&1
```

Per repo privato, usa:

```cron
0 */6 * * * GITHUB_TOKEN=ghp_xxxx /home/mnossa/scripts/raspberry-deploy.sh >> /home/mnossa/logs/finanzamente-deploy.log 2>&1
```

### Verifica manuale

Per verificare che lo script funzioni correttamente:

```bash
/home/mnossa/scripts/raspberry-deploy.sh
# oppure
bash /home/mnossa/www/finanzamente/scripts/raspberry-deploy.sh
```

### Visualizza i log

```bash
tail -f /home/mnossa/logs/finanzamente-deploy.log
```

### Comportamento dello script

| Situazione                      | Azione                                               |
|---------------------------------|------------------------------------------------------|
| Nessuna nuova release           | Esce senza fare nulla                                |
| Nuova release disponibile       | Scarica, backup, aggiorna, migra, riavvia            |
| Errore durante il deploy        | Rollback automatico alla versione precedente         |
| Primo deploy (nessuna versione) | Installa la versione più recente disponibile         |

---

## 5. Struttura dell'archivio di release

L'archivio `.tar.gz` include:

```
finanzamente-vYYYYMMDD-xxxxxxx.tar.gz
├── app/                   # Codice PHP (controllers, models, services)
├── artisan                # CLI Laravel
├── bootstrap/             # Bootstrap del framework
├── config/                # Configurazione Laravel
├── database/              # Migrazioni e seeder
├── docs/                  # Documentazione (incluso questo file)
├── nginx/                 # Configurazione Nginx
├── public/
│   ├── build/             # Asset frontend compilati (Vite)
│   └── index.php          # Entry point web
├── resources/             # Viste Blade, sorgenti JS/CSS
├── routes/                # Definizione rotte
├── scripts/               # Script di deploy (incluso raspberry-deploy.sh)
├── storage/               # Directory runtime (log, cache, sessioni)
├── vendor/                # Dipendenze PHP (pre-installate da Composer)
├── Dockerfile             # Immagine PHP-FPM
├── docker-compose.yml     # Orchestrazione container
├── Makefile               # Comandi di sviluppo
├── composer.json          # Manifest dipendenze PHP
├── composer.lock          # Lock file dipendenze PHP
└── .env.example           # Template variabili d'ambiente
```

**Non inclusi** (esclusi dall'archivio):
- `.git/` — storico versioni
- `node_modules/` — dipendenze Node.js (non necessarie in produzione)
- `.env`, `.env.docker`, `.env.testing` — file con segreti
- `storage/logs/*.log` — log runtime
- `storage/framework/cache/`, `sessions/`, `views/` — cache runtime

---

## 6. Assunzioni e limiti

- **Architettura**: il Raspberry Pi deve supportare Docker (ARM64 o ARMv7).
  Le immagini Docker (`php:8.2-fpm`, `mysql:8.0`, `nginx:alpine`) devono essere
  disponibili per l'architettura in uso.

- **Connessione internet**: il Raspberry Pi deve poter raggiungere `github.com`
  e `api.github.com` in uscita (porta 443). Non è necessaria accessibilità
  dall'esterno.

- **Risorse**: MySQL 8.0 è esigente. Consigliato almeno **2 GB di RAM**
  (Raspberry Pi 4 o superiore). In alternativa, valutare MariaDB o SQLite.

- **Persistenza dati**: il volume Docker `dbdata` persiste il database MySQL.
  Un `docker compose down -v` **elimina tutti i dati**. Usa sempre
  `docker compose down` (senza `-v`) per arresti normali.

- **Rollback**: il backup viene conservato fino al completamento del deploy.
  In caso di rollback, la versione precedente viene ripristinata e i container
  vengono riavviati. Dopo un rollback, verificare i log per identificare la causa.

- **Prima installazione**: lo script di deploy assume che la directory
  `INSTALL_DIR` esista già con un `.env.docker` valido. Per il primo deploy,
  seguire la procedura manuale descritta nella [sezione 3](#3-primo-deploy-su-raspberry-pi).

- **Aggiornamento dello script**: quando viene rilasciata una nuova versione,
  l'archivio include la versione aggiornata di `scripts/raspberry-deploy.sh`.
  Dopo il deploy, aggiorna manualmente la copia usata da cron:

  ```bash
  cp /home/mnossa/www/finanzamente/scripts/raspberry-deploy.sh \
     /home/mnossa/scripts/raspberry-deploy.sh
  ```

---

## 7. Troubleshooting

### I container non si avviano

```bash
cd /home/mnossa/www/finanzamente
docker compose logs app
docker compose logs db
docker compose ps
```

### Errore "APP_KEY not set"

Genera la chiave e aggiornala in `.env.docker`:

```bash
docker compose exec app php artisan key:generate --show
```

### Permessi su storage/

```bash
chmod -R 775 /home/mnossa/www/finanzamente/storage
chmod -R 775 /home/mnossa/www/finanzamente/bootstrap/cache
```

### Il deploy automatico non si attiva

Verifica i log cron:

```bash
tail -50 /home/mnossa/logs/finanzamente-deploy.log
# oppure
grep finanzamente /var/log/syslog | tail -20
```

Controlla che lo script sia eseguibile:

```bash
ls -la /home/mnossa/scripts/raspberry-deploy.sh
chmod +x /home/mnossa/scripts/raspberry-deploy.sh
```

### Rollback manuale

```bash
# Ferma i container
cd /home/mnossa/www/finanzamente
docker compose down

# Ripristina backup
rm -rf /home/mnossa/www/finanzamente
mv /home/mnossa/www/finanzamente.backup /home/mnossa/www/finanzamente

# Riavvia
cd /home/mnossa/www/finanzamente
docker compose up -d
```

### Visualizza la versione installata

```bash
cat /home/mnossa/www/finanzamente/.release-version
```
