# Migrazione server Hetzner — Guida alla persistenza dei dati

## Cosa rischia di andare perso

Le immagini degli articoli e i dati del database risiedono in Docker named volumes sul server attuale:

| Volume | Path fisica sul server | Contenuto |
|---|---|---|
| `finanzamente_storage` | `/var/lib/docker/volumes/finanzamente_storage/_data` | Upload, immagini magazine, log, sessioni |
| `finanzamente_bootstrap_cache` | `/var/lib/docker/volumes/finanzamente_bootstrap_cache/_data` | Cache Laravel |
| `finanzamente_db` | `/var/lib/docker/volumes/finanzamente_db/_data` | Database MySQL |

Se si crea un nuovo server senza trasferire questi volumi, **tutti i dati vengono persi**.

---

## Opzione A — Snapshot Hetzner (consigliata per upgrade)

La più semplice. Crea uno snapshot dell'intero server dalla console Hetzner, poi ripristinalo sul nuovo. Tutto si porta dietro inclusi volumi, configurazioni e certificati.

1. Dalla console Hetzner: **Server → Snapshots → Create Snapshot**
2. Crea il nuovo server selezionando lo snapshot come immagine di partenza
3. Aggiorna eventualmente l'IP nel DNS

> Attenzione: durante lo snapshot il server viene momentaneamente congelato (~pochi secondi).

---

## Opzione B — Backup manuale dei volumi

Utile se si vuole migrare su un server con OS diverso, o fare un backup precauzionale prima di qualsiasi intervento rischioso.

### Esporta i volumi (vecchio server)

```bash
# Storage (immagini, upload)
docker run --rm \
  -v finanzamente_storage:/data \
  -v /tmp:/backup \
  alpine tar czf /backup/storage.tar.gz -C /data .

# Database
docker run --rm \
  -v finanzamente_db:/data \
  -v /tmp:/backup \
  alpine tar czf /backup/db_data.tar.gz -C /data .
```

### Copia sul nuovo server

```bash
scp deploy@vecchio_ip:/tmp/storage.tar.gz deploy@nuovo_ip:/tmp/
scp deploy@vecchio_ip:/tmp/db_data.tar.gz deploy@nuovo_ip:/tmp/
```

### Importa i volumi (nuovo server)

```bash
# Assicurati che i container siano fermi prima di importare il DB
docker compose -f docker-compose.prod.yml down

# Storage
docker run --rm \
  -v finanzamente_storage:/data \
  -v /tmp:/backup \
  alpine tar xzf /backup/storage.tar.gz -C /data

# Database
docker run --rm \
  -v finanzamente_db:/data \
  -v /tmp:/backup \
  alpine tar xzf /backup/db_data.tar.gz -C /data

# Riavvia lo stack
docker compose -f docker-compose.prod.yml up -d
```

---

## Opzione C — Storage remoto con Hetzner Object Storage (soluzione definitiva)

Le immagini vengono salvate su bucket S3-compatibile (Hetzner Object Storage). Il server diventa stateless: le migrazioni diventano banali e i backup sono automatici.

### Configurazione Laravel

1. Aggiungi il package S3: `composer require league/flysystem-aws-s3-v3`
2. Configura `config/filesystems.php` con un disco `hetzner`:

```php
'hetzner' => [
    'driver'   => 's3',
    'key'      => env('HETZNER_S3_KEY'),
    'secret'   => env('HETZNER_S3_SECRET'),
    'region'   => env('HETZNER_S3_REGION', 'fsn1'),
    'bucket'   => env('HETZNER_S3_BUCKET'),
    'endpoint' => env('HETZNER_S3_ENDPOINT'), // es. https://fsn1.your-objectstorage.com
    'use_path_style_endpoint' => true,
    'url'      => env('HETZNER_S3_URL'),      // URL pubblico del bucket
    'visibility' => 'public',
],
```

3. Cambia `Storage::disk('public')` in `Storage::disk('hetzner')` nel controller
4. Aggiorna `getCoverImageUrlAttribute()` nel modello per usare l'URL del bucket

### Variabili `.env` da aggiungere

```
HETZNER_S3_KEY=
HETZNER_S3_SECRET=
HETZNER_S3_REGION=fsn1
HETZNER_S3_BUCKET=finanzamente-media
HETZNER_S3_ENDPOINT=https://fsn1.your-objectstorage.com
HETZNER_S3_URL=https://finanzamente-media.fsn1.your-objectstorage.com
```

> Questa opzione richiede lavoro di implementazione ma è la più robusta a lungo termine.
