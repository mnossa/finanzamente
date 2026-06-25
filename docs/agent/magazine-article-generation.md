# Generazione articoli Magazine con agente AI

Guida operativa per produrre articoli editoriali coerenti con il tono del magazine Finanzamente, usando Cursor Agent (o un LLM equivalente).

Riferimenti correlati: [magazine-admin.md](../magazine-admin.md) (pubblicazione e campi DB).

---

## Workflow rapido

```text
1. make magazine-write
2. Apri Cursor Agent → incolla tmp/magazine-write.prompt (o: "segui il prompt in tmp/magazine-write.prompt")
3. L'agente ti fa domande (argomento, categoria, angolo, esempi…) → confermi il brief
4. L'agente scrive l'articolo e lo revisiona
5. Checklist editoriale → pubblica da /admin/magazine/crea
```

**Modalità diretta** (brief già pronto, salta le domande):

```text
make magazine-write topic="..." category=...
```

---

## Comando Make (consigliato)

### Interattivo (default)

```bash
make magazine-write
```

Genera `tmp/magazine-write.prompt` con istruzioni per l'agente:
- **Fase 1**: domande guidate (AskQuestion per categoria, lunghezza, ecc.)
- **Fase 2**: scrittura articolo solo dopo conferma brief

In Cursor Agent incolla il file o scrivi: `Segui il prompt in tmp/magazine-write.prompt`

### Diretto (brief già compilato)

```bash
make magazine-write topic="Pensione a 30 anni" category=Pensione

make magazine-write \
  topic="PAC e ETF: da dove iniziare" \
  category=Investimenti \
  angle="Esempi numerici con 50€ al mese"

make magazine-write topic="..." category=Budgeting output=-   # stampa su stdout
```

| Parametro | Obbligatorio | Descrizione |
|-----------|--------------|-------------|
| `topic` | ❌* | Argomento (*obbligatorio solo in modalità diretta) |
| `category` | ❌* | Risparmio, Investimenti, Budgeting, Pensione, Tasse, Conti e Banche, Mindset |
| `angle` | ❌ | Tesi editoriale |
| `length` | ❌ | Default: `900-1400 parole (~5-7 min)` |
| `links` | ❌ | Slug interni separati da virgola |
| `notes` | ❌ | Note aggiuntive per il brief |
| `output` | ❌ | Path file; default `tmp/magazine-write.prompt`; `-` = stdout |
| `no-db=1` | ❌ | Salta query articoli esistenti |

Alias: `make magazine-prompt` (identico a `magazine-write`).

### Domande che l'agente porrà (Fase 1)

1. Argomento / titolo provvisorio
2. Categoria magazine
3. Angolo / tesi editoriale
4. Esempi numerici reali o ipotetici
5. Articoli interni da linkare (suggerisce dal DB)
6. Vincoli (cosa evitare)
7. Lunghezza (opzionale)
8. Pubblico specifico (opzionale)
9. Hook di apertura (opzionale)

Poi riepilogo brief → **"Confermi? Posso procedere?"** → scrittura.

---

## Script diretto (alternativa)

Lo script legge gli articoli già pubblicati dal DB (per interlinking) e assembla il prompt editoriale completo.

```bash
# Stack Docker attivo (make up)
chmod +x scripts/magazine-generate-prompt.sh

./scripts/magazine-generate-prompt.sh \
  --topic "Pensione a 30 anni: quanto costa aspettare" \
  --category Pensione \
  --angle "Gap pensionistico, TFR, fondo pensione, esempi numerici interesse composto"
```

### Opzioni

| Flag | Obbligatorio | Descrizione |
|------|--------------|-------------|
| `--topic` | ✅ | Argomento dell'articolo |
| `--category` | ✅ | Una tra: Risparmio, Investimenti, Budgeting, Pensione, Mindset, Tasse, Conti e Banche |
| `--angle` | ❌ | Tesi editoriale (default: pratico + numeri + zero fuffa) |
| `--length` | ❌ | Default: `900-1400 parole (~5-7 min)` |
| `--links` | ❌ | Slug interni da linkare, separati da virgola |
| `--notes` | ❌ | Note aggiuntive per il brief |
| `--output` | ❌ | Salva su file (es. `tmp/prompt-pensione.txt`) |
| `--no-db` | ❌ | Salta query DB se stack non disponibile |

### Esempi

```bash
# Salva prompt su file
./scripts/magazine-generate-prompt.sh \
  --topic "PAC e ETF: da dove iniziare" \
  --category Investimenti \
  --links "i-tuoi-risparmi-stanno-dimagrendo-come-difendersi-dallinflazione-con-pragmatismo-e-controllo" \
  --output tmp/prompt-investimenti.txt

# Prompt minimo
./scripts/magazine-generate-prompt.sh \
  --topic "Conto deposito vs conto corrente" \
  --category "Conti e Banche"
```

---

## Prompt da incollare in Cursor Agent

Dopo lo script, apri **Cursor → Agent mode** e incolla l'output. Oppure usa direttamente questo template se preferisci compilarlo a mano.

<details>
<summary>Template prompt (click per espandere)</summary>

```markdown
Genera un articolo per il magazine Finanzamente seguendo TUTTE le regole sotto.
Non consegnare la bozza: rileggi, migliora e consegna solo la versione finale.

# RUOLO
Copywriter editoriale Finanzamente. Target 18–45. Testo in ITALIANO.

# VOCE
- Prima persona singolare (founder-sviluppatore).
- Anti-hype: non consulente finanziario, niente guru.
- "Tu" al lettore. Mantra: "non puoi gestire ciò che non misuri".

# ANTI-FUFFA
- Esempi numerici: ipotesi + calcolo + risultato in €.
- Minimo 2 scenari con cifre, 1 checklist o confronto strutturato.
- "Conviene/potrebbe" solo con condizioni esplicite.

# STRUTTURA
Hook quotidiano → promessa → ## H2 numerate → esempio pratico → ## Conclusioni.
Markdown con --- tra sezioni. Metafora visiva. Disclaimer 💡 solo se pertinente al tema.

# COMPLIANCE MODULARE
Non ripetere sempre fondo emergenza, TFR o "investi solo in ciò che comprendi".
Usa questi principi solo se sono davvero utili per l'argomento.
Se serve un disclaimer, sceglilo in base al tema: consulente finanziario/patronato per previdenza,
commercialista/CAF per tasse, notaio/avvocato per temi legali.

# OUTPUT
title, slug, excerpt, meta_title, meta_description, content (Markdown),
category, author_name (Redazione Finanzamente), reading_time_minutes, is_ai_assisted: true

# BRIEF
- Argomento: [ARGOMENTO]
- Categoria: [CATEGORIA]
- Angolo: [ANGOLO]
- Lunghezza: 900-1400 parole
- Link interni: [SLUG o "scegli da elenco"]

# REVISIONE FINALE OBBLIGATORIA
Rileggi, elimina vaghezza, migliora esempi numerici, consegna solo versione finale.
```

</details>

---

## Checklist post-generazione (editor umano)

Prima di pubblicare, verifica ogni punto:

### Contenuto
- [ ] Hook concreto (non definizione enciclopedica)
- [ ] Almeno 2 scenari con numeri in € e ipotesi dichiarate
- [ ] Almeno 1 checklist o confronto operativo
- [ ] Nessuna statistica inventata; fonti citabili o formulazione qualitativa
- [ ] Disclaimer in blockquote 💡 solo se il tema lo richiede, con professionista corretto
- [ ] Nessun concetto riciclato per abitudine (fondo emergenza, TFR, investimenti) se non pertinente
- [ ] Link interni con slug reali (`https://finanzamente.it/magazine/<slug>`)
- [ ] Finanzamente citato con leggerezza (non CTA aggressiva)

### Metadati
- [ ] `title` ≤ 70 caratteri, accattivante
- [ ] `slug` kebab-case, univoco
- [ ] `excerpt` ≤ 200 caratteri
- [ ] `meta_title` compilato (≤ 60 car.)
- [ ] `meta_description` compilata (150–160 car.)
- [ ] `category` corretta
- [ ] `is_ai_assisted` = true (flag DB, nessuna nota visibile al lettore)

### Formato
- [ ] Markdown valido (`##`, `###`, liste, blockquote)
- [ ] € con virgola decimale, migliaia con punto
- [ ] Termini tecnici spiegati alla prima occorrenza
- [ ] Emoji solo nei callout

### Stima qualità rapida
- [ ] Rimuovi un paragrafo a caso: l'articolo perde valore? Se no, taglialo.
- [ ] Ogni H2 risponde a "cosa faccio con questa info?" — se no, riscrivi.

---

## Pubblicazione

### Admin web (consigliato)

1. Accedi come owner (`MAGAZINE_ADMIN_EMAIL` in `.env`)
2. Vai su `/admin/magazine/crea`
3. Incolla titolo, excerpt, content (Markdown)
4. Compila meta_title e meta_description
5. Seleziona categoria, spunta **Contenuto assistito da AI**
6. Lascia **Data pubblicazione** vuota per bozza, oppure imposta data per pubblicare
7. Aggiungi cover (upload o Unsplash)

Dettaglio campi: [magazine-admin.md](../magazine-admin.md).

### Anteprima bozza

Dall'admin, apri l'anteprima dell'articolo in bozza prima di pubblicare.

---

## Parametri editoriali concordati

| Parametro | Valore |
|-----------|--------|
| Voce | Prima persona founder-sviluppatore |
| Lunghezza | 900–1400 parole (~5–7 min) |
| Fonti | Solo reali e verificabili; se incerto → qualitativo |
| AI disclosure | `is_ai_assisted=true`, nessuna nota visibile al lettore |
| Prodotto | Citazione leggera e occasionale |
| Autore DB | `Redazione Finanzamente` |

---

## Categorie e gap contenutistico

Categorie attive nel seeder: Risparmio, Investimenti, Budgeting, Pensione, Tasse, Conti e Banche, Mindset.

Verifica articoli esistenti:

```bash
docker compose exec -T db mysql --default-character-set=utf8mb4 \
  -ufinanzamente -pfinanzamente finanzamente -e "
  SELECT c.name, COUNT(*) AS n
  FROM magazine_articles a
  JOIN magazine_categories c ON c.id = a.category_id
  GROUP BY c.name;
"
```

---

## Idee argomento (backlog)

Usa lo script con `--topic` e `--category` appropriati:

| Categoria | Idea argomento |
|-----------|----------------|
| Investimenti | PAC e ETF: da dove iniziare con 50€ al mese |
| Pensione | TFR in azienda vs fondo pensione: checklist decisionale |
| Budgeting | Spese condivise in coppia: metodo proporzionale con numeri |
| Risparmio | Fondo emergenza: quanto basta davvero per freelance vs dipendente |
| Mindset | Perché tracciare le spese manualmente cambia le decisioni |
| Conti e Banche | Conto deposito vs conto corrente: quando ha senso spostare liquidità |

---

## Troubleshooting

| Problema | Soluzione |
|----------|-----------|
| Script dice "container db non avviato" | `make up` |
| Articolo troppo generico | Rigenera con `--angle` più specifico e `--notes "zero frasi motivazionali, solo numeri"` |
| Esempio composto vago | Chiedi all'agente: "Riscrivi l'esempio con versato, rendimento ipotizzato e capitale finale" |
| Slug duplicato | L'admin genera slug univoco dal titolo; modifica titolo se necessario |
| Link interni rotti | Esegui script senza `--no-db` per elenco slug aggiornato |
