# Gestione Magazine — Guida Operativa

## Dove stanno i dati

Gli articoli e le categorie sono salvati su database MySQL in due tabelle:

| Tabella | Contenuto |
|---|---|
| `magazine_categories` | Categorie con nome, slug e colore |
| `magazine_articles` | Articoli con titolo, slug, contenuto Markdown, excerpt, cover image, categoria, autore, data pubblicazione, meta SEO, contatore visite |

Il contenuto degli articoli è scritto in **Markdown** e convertito in HTML solo al momento della lettura (mai salvato HTML grezzo — protezione XSS).

---

## Chi è l'amministratore

L'accesso all'area admin del magazine è riservato all'utente la cui email corrisponde alla variabile d'ambiente:

```
MAGAZINE_ADMIN_EMAIL=tua@email.com
```

Questa variabile è separata da `PRE_LAUNCH_OWNER_EMAIL` per chiarezza semantica: la modalità pre-lancio (waitlist) e la gestione editoriale sono concetti distinti. Se `MAGAZINE_ADMIN_EMAIL` non è impostata, il sistema ricade su `PRE_LAUNCH_OWNER_EMAIL` come fallback.

Il middleware `OwnerMiddleware` applica questo controllo. Le rotte admin richiedono tre condizioni:
1. Utente autenticato (`auth`)
2. Email verificata (`verified`)
3. Email corrisponde a `MAGAZINE_ADMIN_EMAIL` (`owner`)

Chiunque altro riceve un `403 Accesso riservato`.

---

## Rotte admin

| Metodo | URL | Azione |
|---|---|---|
| GET | `/admin/magazine/` | Lista articoli (bozze + pubblicati) |
| GET | `/admin/magazine/crea` | Form nuovo articolo |
| POST | `/admin/magazine/` | Salva nuovo articolo |
| GET | `/admin/magazine/{id}/modifica` | Form modifica articolo |
| PUT | `/admin/magazine/{id}` | Salva modifiche |
| DELETE | `/admin/magazine/{id}` | Elimina articolo e cover image |

---

## Ciclo di vita di un articolo

### Bozza
Lasciare **vuoto** il campo "Data di pubblicazione" — l'articolo non è visibile pubblicamente.

### Pubblicazione immediata
Impostare la data nel passato o "adesso".

### Pubblicazione programmata
Impostare una data futura — l'articolo rimarrà bozza fino a quella data.

---

## Campi del form

| Campo | Obbligatorio | Note |
|---|---|---|
| Titolo | ✅ | Genera automaticamente lo slug |
| Contenuto | ✅ | Markdown. Viene sanificato al rendering (HTML strip, no unsafe links) |
| Excerpt | ✅ | Testo breve per card e meta description fallback |
| Categoria | ✅ | Seleziona da quelle disponibili |
| Autore | ✅ | Nome visualizzato nell'articolo |
| Data di pubblicazione | ❌ | Vuota = bozza |
| In evidenza | ❌ | Mostra l'articolo nella sezione hero dell'index |
| Cover image | ❌ | JPEG/PNG/WebP, max 2MB. Salvata in `storage/app/public/magazine/covers/` |
| Meta title | ❌ | Sovrascrive il titolo per i motori di ricerca |
| Meta description | ❌ | Sovrascrive l'excerpt per i motori di ricerca |

---

## Contatore visite

Il contatore visite degli articoli è aggiornato automaticamente a ogni visita pubblica. Le visite sono deduplicate per IP (una ogni 30 minuti) tramite cache — l'IP non è mai salvato in chiaro, viene hashato con SHA-256 + `APP_KEY`.

---

## Immagini di copertina

Le cover image sono salvate nel volume Docker persistente `storage` (`storage/app/public/magazine/covers/`). Sopravvivono ai deploy e alle migrazioni server come tutti gli altri upload.

- Nome file: UUID casuale (es. `a1b2c3d4-....jpg`)
- Alla modifica: la vecchia immagine viene eliminata automaticamente prima di salvare la nuova
- Alla cancellazione articolo: la cover viene eliminata insieme all'articolo

---

## Categorie predefinite

Le categorie iniziali sono state create con il seeder `MagazineCategorySeeder`. Per aggiungerne di nuove in produzione, accedere al database direttamente o aggiornare il seeder e rieseguirlo con `make seed`.

| Categoria | Colore |
|---|---|
| Risparmio | `#10B981` |
| Investimenti | `#6366F1` |
| Budgeting | `#8B5CF6` |
| Pensione | `#F59E0B` |
| Tasse | `#EF4444` |
| Conti e Banche | `#3B82F6` |
| Mindset | `#14B8A6` |
