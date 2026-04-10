# Guida al Deploy su Hetzner Cloud — Finanzamente

## Indice

1. [Architettura CI/CD](#1-architettura-cicd)
2. [Prerequisiti](#2-prerequisiti)
3. [Configurazione Docker Hub](#3-configurazione-docker-hub)
4. [Configurazione GitHub Secrets](#4-configurazione-github-secrets)
5. [Provisioning del server Hetzner](#5-provisioning-del-server-hetzner)
6. [Configurazione del server](#6-configurazione-del-server)
7. [Primo deploy manuale](#7-primo-deploy-manuale)
8. [Verifica del deploy](#8-verifica-del-deploy)
9. [SSL con Caddy (HTTPS)](#9-ssl-con-caddy-https)
10. [Workflow quotidiano](#10-workflow-quotidiano)
11. [Rollback](#11-rollback)
12. [Monitoraggio e log](#12-monitoraggio-e-log)
13. [Variabili d'ambiente di riferimento](#13-variabili-dambiente-di-riferimento)

---

## 1. Architettura CI/CD

```
Push su main
    │
    ▼
GitHub Actions — Job test
    • Avvia stack Docker Compose (sviluppo)
    • Installa dipendenze PHP
    • Esegue make test (PHPUnit, SQLite in-memory)
    │
    ▼ (solo se test OK)
GitHub Actions — Job build
    • Buildx multi-stage (Dockerfile.prod):
        Stage 1: node:24-alpine  → compila assets Vite/TS
        Stage 2: composer:2.9   → installa vendor (no-dev)
        Stage 3: php:8.2-fpm-alpine + nginx + supervisord
    • Push su Docker Hub: mnossa/finanzamente:sha-XXXXXXX + :latest
    │
    ▼ (solo se build OK)
GitHub Actions — Job deploy
    • SSH su Hetzner CX22
    • docker compose pull (scarica nuova immagine)
    • docker compose up -d (riavvia con zero-config)
    • Entrypoint→ php artisan migrate + optimize
    • Health check su /up
```

**Container in produzione:**
| Container | Immagine | Ruolo |
|---|---|---|
| `finanzamente-app` | `mnossa/finanzamente:sha-*` | PHP-FPM + Nginx (supervisord) |
| `finanzamente-scheduler` | `mnossa/finanzamente:sha-*` | Laravel scheduler (schedule:work) |
| `finanzamente-db` | `mysql:8.0` | Database MySQL |

---

## 2. Prerequisiti

- Account [Hetzner Cloud](https://console.hetzner.cloud) ✓
- Account [Docker Hub](https://hub.docker.com) con repository `mnossa/finanzamente` (privato) ✓
- Repository GitHub `mnossa/finanzamente` ✓
- Dominio configurato (es. `finanzamente.app` o sottodominio) → punta all'IP del server Hetzner

---

## 3. Configurazione Docker Hub

### 3.1 Crea un Access Token

1. Vai su [hub.docker.com](https://hub.docker.com) → Account Settings → Security
2. Clic su **New Access Token**
3. Nome: `finanzamente-github-actions`
4. Permessi: **Read & Write** (serve per fare push)
5. **Copia il token** — non sarà più visibile

### 3.2 Verifica il repository

Il repository `mnossa/finanzamente` deve essere privato.  
GitHub Actions si autenticherà con le credenziali che caricherai nella sezione successiva.

---

## 4. Configurazione GitHub Secrets

Vai su: **GitHub → Repository → Settings → Secrets and variables → Actions**

Clic **New repository secret** per ognuno:

| Secret | Valore | Descrizione |
|--------|--------|-------------|
| `DOCKERHUB_USERNAME` | `mnossa` | Username Docker Hub |
| `DOCKERHUB_TOKEN` | `dckr_pat_xxx...` | Access token Docker Hub (step 3.1) |
| `HETZNER_HOST` | `1.2.3.4` | IP pubblico del server Hetzner |
| `HETZNER_USER` | `deploy` | Utente SSH (creato in step 6) |
| `HETZNER_SSH_KEY` | *(chiave privata SSH)* | Chiave privata per SSH verso Hetzner |
| `HETZNER_PORT` | `22` | Porta SSH (default 22, opzionale) |

### 4.1 Come generare la coppia di chiavi SSH per GitHub Actions

Sul tuo computer locale:

```bash
# Genera una chiave dedicata per il deploy (senza passphrase)
ssh-keygen -t ed25519 -C "github-actions-finanzamente" -f ~/.ssh/finanzamente_deploy -N ""

# Mostra la chiave PRIVATA (da incollare in HETZNER_SSH_KEY su GitHub)
cat ~/.ssh/finanzamente_deploy

# Mostra la chiave PUBBLICA (da aggiungere al server, step 6.3)
cat ~/.ssh/finanzamente_deploy.pub
```

> **Importante**: la chiave privata inizia con `-----BEGIN OPENSSH PRIVATE KEY-----`.
> Copia tutto il contenuto (incluse le righe di intestazione/fine) nel secret `HETZNER_SSH_KEY`.

### 4.2 Configura l'ambiente "production" su GitHub

> **Nota piano gratuito**: su repository **privati con piano Free**, gli Environment esistono ma le **protection rules** (Required reviewers, wait timer) non sono disponibili — richiedono piano Pro/Team/Enterprise. Su repository **pubblici** sono gratuite.
>
> Con un repo privato gratuito, l'`environment: production` nel workflow funziona comunque (il deploy parte in automatico senza approvazione manuale).

Se vuoi comunque creare l'environment (consigliato per visibilità nella UI GitHub):

1. **Settings → Environments → New environment**
2. Nome: `production`
3. (Solo piano Pro+) Abilita "Required reviewers" e aggiungi te stesso
4. (Solo piano Pro+) Aggiungi "Deployment branches" → solo `main`

---

## 5. Provisioning del server Hetzner

### 5.1 Scegli il piano

Per cominciare, il piano **CX22** è più che sufficiente:

| Piano | vCPU | RAM | SSD | Traffico | Prezzo |
|-------|------|-----|-----|----------|--------|
| CX22  | 2    | 4GB | 40GB | 20TB | ~€4.35/mese |
| CX32  | 4    | 8GB | 80GB | 20TB | ~€8.70/mese |

> 4GB di RAM è il minimo consigliato per MySQL + PHP-FPM + Nginx su Docker. Se il sito è solo per uso personale, CX22 è sufficiente.

### 5.2 Crea il server

1. Accedi a [console.hetzner.cloud](https://console.hetzner.cloud)
2. **New Server**:
   - **Location**: Falkenstein o Nuremberg (Europa, latenza bassa per l'Italia)
   - **Image**: tab **Apps** → seleziona **"Docker CE"** (Docker già installato, nessun setup manuale)
   - **Type**: CX22
   - **SSH Keys**: aggiungi la tua chiave pubblica personale (`~/.ssh/id_ed25519.pub` o simile)
   - **Networking**: IPv4 + IPv6 abilitati
   - **Firewall**: crea un nuovo firewall (step 5.3)
   - **Name**: `finanzamente-prod`

   > Usando l'immagine **Docker CE** (App), Docker e Docker Compose sono già installati e configurati. Puoi **saltare il §6.2** (installazione manuale di Docker).

3. Nota l'**IP pubblico** del server appena creato → inseriscilo nel secret `HETZNER_HOST`

> **Server già esistente (chiave SSH aggiunta in seguito)**
>
> Se hai creato il server senza aggiungere la chiave SSH, hai due opzioni:
>
> **Opzione A — Dalla console Hetzner (consigliata, non richiede accesso root):**
> 1. Vai su **console.hetzner.cloud → Server → `finanzamente-prod` → Actions → Add SSH Key**
> 2. Incolla il contenuto di `~/.ssh/id_ed25519.pub` (o la chiave che vuoi usare)
> 3. Hetzner la aggiungerà automaticamente a `/root/.ssh/authorized_keys` al prossimo riavvio, oppure usa la **console web** (icona terminale) per aggiungerla subito manualmente (vedi Opzione B)
>
> **Opzione B — Direttamente via console web Hetzner (senza SSH, subito):**
> 1. Vai su **console.hetzner.cloud → Server → `finanzamente-prod` → Console** (icona `>_`)
> 2. Accedi come `root` usando la password inviata via email al momento della creazione
> 3. Aggiungi la chiave manualmente:
>    ```bash
>    echo "INCOLLA_QUI_IL_CONTENUTO_DI_id_ed25519.pub" >> /root/.ssh/authorized_keys
>    chmod 600 /root/.ssh/authorized_keys
>    ```
> 4. Da questo momento puoi usare `ssh root@<IP_SERVER>` normalmente

### 5.3 Configura il Firewall

Nella console Hetzner → **Firewalls → Create Firewall** con queste regole:

**Inbound:**
| Protocol | Port | Source | Descrizione |
|----------|------|--------|-------------|
| TCP | 22 | Any (o solo il tuo IP) | SSH |
| TCP | 80 | Any | HTTP |
| TCP | 443 | Any | HTTPS |

**Outbound:** Allow all (default)

Assegna il firewall al server `finanzamente-prod`.

---

## 6. Configurazione del server

Connettiti al server come root:

```bash
ssh root@<IP_SERVER>
```

### 6.1 Aggiornamento del sistema

```bash
apt update && apt upgrade -y
apt install -y curl git ufw fail2ban
```

### 6.2 Installa Docker

> **Salta questo step** se hai scelto l'immagine **Docker CE** (App) al momento della creazione del server — Docker è già installato e funzionante.

Se hai scelto Ubuntu plain, installa Docker manualmente:

```bash
# Script ufficiale Docker
curl -fsSL https://get.docker.com | sh

# Verifica installazione
docker --version
docker compose version
```

### 6.3 Crea l'utente `deploy`

```bash
# Crea utente senza home privilegiata
useradd -m -s /bin/bash deploy

# Aggiungi al gruppo docker (permesso di eseguire docker senza sudo)
usermod -aG docker deploy

# Crea la directory SSH
mkdir -p /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chown deploy:deploy /home/deploy/.ssh
```

### 6.4 Aggiungi la chiave pubblica di GitHub Actions

Copia il contenuto di `~/.ssh/finanzamente_deploy.pub` (generato nel step 4.1) e aggiungilo al server:

```bash
# Sul server, come root:
echo "INCOLLA_QUI_IL_CONTENUTO_DI_finanzamente_deploy.pub" \
  >> /home/deploy/.ssh/authorized_keys

chmod 600 /home/deploy/.ssh/authorized_keys
chown deploy:deploy /home/deploy/.ssh/authorized_keys
```

**Verifica che la connessione SSH funzioni** (dal tuo computer):
```bash
ssh -i ~/.ssh/finanzamente_deploy deploy@<IP_SERVER> "echo 'SSH OK'"
```

### 6.5 Crea la directory del progetto

```bash
mkdir -p /opt/finanzamente
chown deploy:deploy /opt/finanzamente
```

### 6.6 Autenticazione Docker Hub (sul server)

```bash
# Esegui come utente deploy
su - deploy

docker login -u mnossa
# Inserisci il tuo Access Token Docker Hub quando richiesto
# Le credenziali vengono salvate in ~/.docker/config.json
exit
```

> **Nota**: le credenziali Docker Hub sono necessarie anche sul server per fare `docker compose pull` di immagini private.

---

## 7. Primo deploy manuale

Il primo deploy deve essere eseguito manualmente per creare il file `.env` e avviare i container per la prima volta.

### 7.1 Connettiti come deploy

```bash
ssh deploy@<IP_SERVER>
cd /opt/finanzamente
```

### 7.2 Copia i file di produzione

Esegui questo comando **dal tuo computer locale** (non dal server):

```bash
scp -i ~/.ssh/finanzamente_deploy docker-compose.prod.yml deploy@<IP_SERVER>:/opt/finanzamente/
```

> Se vuoi evitare di specificare `-i` ogni volta, aggiungi al tuo `~/.ssh/config` locale:
> ```
> Host <IP_SERVER>
>     User deploy
>     IdentityFile ~/.ssh/finanzamente_deploy
> ```
> Dopo di che `scp` e `ssh` useranno automaticamente la chiave giusta.

### 7.3 Crea il file .env di produzione

```bash
# Sul server, come deploy:
nano /opt/finanzamente/.env
```

Incolla il seguente template e compila tutti i valori:

```dotenv
# ── App ────────────────────────────────────────────────────────────────────
APP_NAME="Finanzamente"
APP_ENV=production
APP_KEY=                          # Genera con: php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://tuodominio.com    # Il tuo dominio con HTTPS

# ── Database ───────────────────────────────────────────────────────────────
DB_CONNECTION=mysql
DB_HOST=db                        # Nome del servizio Docker
DB_PORT=3306
DB_DATABASE=finanzamente
DB_USERNAME=finanzamente
DB_PASSWORD=SCEGLI_PASSWORD_SICURA_QUI
DB_ROOT_PASSWORD=SCEGLI_ROOT_PASSWORD_SICURA_QUI

# ── Cache / Session / Queue ────────────────────────────────────────────────
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# ── Mail (esempio con Mailgun o SMTP) ──────────────────────────────────────
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@tuodominio.com"
MAIL_FROM_NAME="Finanzamente"

# ── Sicurezza ──────────────────────────────────────────────────────────────
ADV_THROTTLE_SALT=GENERA_STRINGA_CASUALE_QUI_MIN_32_CHAR

# ── Docker Hub Image Tag (gestito automaticamente dal workflow) ────────────
IMAGE_TAG=latest
```

### 7.4 Genera APP_KEY

Esegui questo comando **dal tuo computer locale**, dove lo stack di sviluppo è già attivo:

```bash
docker compose exec app php artisan key:generate --show
```

Copia il valore `base64:...` e incollalo in `.env` alla riga `APP_KEY=` **sul server**.

> `docker run --rm mnossa/finanzamente:latest ...` non funziona la prima volta perché l'immagine non esiste ancora su Docker Hub — viene creata solo dopo il primo push su `main`.

### 7.5 Pull e avvio

```bash
cd /opt/finanzamente

# Pull dell'immagine da Docker Hub
docker compose -f docker-compose.prod.yml pull

# Avvio di tutti i container
docker compose -f docker-compose.prod.yml up -d

# Controlla i log
docker compose -f docker-compose.prod.yml logs -f app
```

L'entrypoint eseguirà automaticamente:
1. `php artisan storage:link`
2. `php artisan migrate --force`
3. `php artisan optimize`

---

## 8. Verifica del deploy

```bash
# Stato container
docker compose -f docker-compose.prod.yml ps

# Health check
curl http://localhost/up

# Log applicazione
docker compose -f docker-compose.prod.yml logs app

# Log database
docker compose -f docker-compose.prod.yml logs db
```

L'app è accessibile su `http://<IP_SERVER>` (prima di configurare il dominio/SSL).

---

## 9. SSL con Caddy (HTTPS)

Caddy gestisce automaticamente i certificati Let's Encrypt. Aggiungilo allo stack.

### 9.1 Aggiorna docker-compose.prod.yml

Aggiungi il servizio Caddy alla fine della sezione `services:`:

```yaml
  caddy:
    image: caddy:2-alpine
    container_name: finanzamente-caddy
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy_data:/data
      - caddy_config:/config
    networks:
      - finanzamente
    depends_on:
      - app
```

E in `volumes:` aggiungi:
```yaml
  caddy_data:
  caddy_config:
```

> **Importante**: rimuovi o commenta il mapping `ports: - "80:80"` dal servizio `app`, altrimenti Caddy non riuscirà a legarlo.

### 9.2 Crea il Caddyfile

```bash
nano /opt/finanzamente/Caddyfile
```

```caddyfile
tuodominio.com {
    reverse_proxy app:80
    
    encode gzip
    
    log {
        output file /var/log/caddy/access.log
    }
}
```

> Sostituisci `tuodominio.com` con il tuo dominio reale. Caddy otterrà automaticamente il certificato TLS da Let's Encrypt al primo accesso.

### 9.3 Riavvia lo stack

```bash
docker compose -f docker-compose.prod.yml up -d
```

---

## 10. Workflow quotidiano

### Push → Deploy automatico

```bash
# Dal tuo computer:
git add .
git commit -m "feat: nuova funzionalità"
git push origin main

# GitHub Actions esegue automaticamente:
# 1. Test
# 2. Build immagine Docker
# 3. Push Docker Hub
# 4. Deploy su Hetzner
```

Puoi monitorare il progresso su: **GitHub → Actions → Deploy to Production**

### Verifica deploymennt dal server

```bash
ssh deploy@<IP_SERVER>
cd /opt/finanzamente
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs --tail=50 app
```

---

## 11. Rollback

### Rollback alla versione precedente

Ogni build crea un tag `sha-XXXXXXX` su Docker Hub. Per fare rollback:

```bash
ssh deploy@<IP_SERVER>
cd /opt/finanzamente

# Imposta il tag della versione precedente
sed -i "s|^IMAGE_TAG=.*|IMAGE_TAG=sha-XXXXXXX|" .env

# Pull e riavvio con la versione vecchia
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
```

> I tag disponibili sono visibili su Docker Hub → `mnossa/finanzamente` → Tags.

### Rollback del database

Le migrazioni Laravel sono irreversibili per default (le rollback richiedono implementazione manuale).
Prima di ogni deploy che modifica il DB, fare un backup:

```bash
# Backup database
docker compose -f docker-compose.prod.yml exec db \
  mysqldump -u finanzamente -p finanzamente > backup-$(date +%Y%m%d%H%M%S).sql
```

---

## 12. Monitoraggio e log

### Log real-time

```bash
# Tutti i servizi
docker compose -f docker-compose.prod.yml logs -f

# Solo l'app
docker compose -f docker-compose.prod.yml logs -f app

# Solo il database
docker compose -f docker-compose.prod.yml logs -f db
```

### Uso risorse

```bash
docker stats
```

### Accesso diretto al container

```bash
# Esegui comandi artisan in produzione
docker compose -f docker-compose.prod.yml exec app php artisan tinker
docker compose -f docker-compose.prod.yml exec app php artisan queue:retry all
```

### Spazio su disco

```bash
df -h
docker system df        # spazio usato da Docker
docker image prune -f   # rimuovi immagini non usate
```

---

## 13. Variabili d'ambiente di riferimento

| Variabile | Obbligatoria | Descrizione |
|-----------|:---:|-------------|
| `APP_KEY` | ✅ | Chiave cifratura Laravel (base64:...) |
| `APP_URL` | ✅ | URL pubblico con schema HTTPS |
| `APP_ENV` | ✅ | `production` |
| `APP_DEBUG` | ✅ | `false` in produzione |
| `DB_DATABASE` | ✅ | Nome database MySQL |
| `DB_USERNAME` | ✅ | Utente MySQL |
| `DB_PASSWORD` | ✅ | Password MySQL (forte) |
| `DB_ROOT_PASSWORD` | ✅ | Password root MySQL (forte) |
| `MAIL_MAILER` | ✅ | Driver email (`smtp`, `mailgun`, ecc.) |
| `MAIL_FROM_ADDRESS` | ✅ | Email mittente |
| `ADV_THROTTLE_SALT` | ✅ | Salt SHA256 rate limiting (min 32 char) |
| `IMAGE_TAG` | ✅ | Tag immagine Docker (gestito da CI/CD) |
| `SKIP_INIT` | — | `true` nel container scheduler |

### GitHub Secrets richiesti

| Secret | Obbligatorio | Descrizione |
|--------|:---:|-------------|
| `DOCKERHUB_USERNAME` | ✅ | Username Docker Hub (`mnossa`) |
| `DOCKERHUB_TOKEN` | ✅ | Access Token Docker Hub |
| `HETZNER_HOST` | ✅ | IP del server Hetzner |
| `HETZNER_USER` | ✅ | Utente SSH (`deploy`) |
| `HETZNER_SSH_KEY` | ✅ | Chiave SSH privata (per GitHub Actions) |
| `HETZNER_PORT` | — | Porta SSH (default: `22`) |
