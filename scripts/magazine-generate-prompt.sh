#!/usr/bin/env bash
# Genera il prompt per Cursor Agent: scrivere un articolo magazine Finanzamente.
#
# Modalità interattiva (default): l'agente pone domande all'utente, poi scrive.
#   ./scripts/magazine-generate-prompt.sh
#   ./scripts/magazine-generate-prompt.sh --interactive
#
# Modalità diretta (brief già compilato):
#   ./scripts/magazine-generate-prompt.sh \
#     --topic "Pensione a 30 anni" \
#     --category Pensione \
#     --angle "Quanto costa aspettare, con esempi numerici"
#
# Opzioni:
#   --interactive   Prompt con fase domande → scrittura (default se mancano topic/category)
#   --topic         Argomento (modalità diretta)
#   --category      Categoria magazine (modalità diretta)
#   --angle         Angolo/tesi editoriale (opzionale)
#   --length        Lunghezza target (default: 900-1400 parole)
#   --links         Slug interni da linkare, separati da virgola (opzionale)
#   --notes         Note aggiuntive per il brief (opzionale)
#   --output        Salva su file invece di stdout
#   --no-db         Salta query DB

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

TOPIC=""
CATEGORY=""
ANGLE=""
LENGTH="900-1400 parole (~5-7 min)"
LINKS=""
NOTES=""
OUTPUT=""
NO_DB=false
INTERACTIVE=false

usage() {
    sed -n '2,24p' "$0" | tail -n +2
    exit "${1:-0}"
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --topic) TOPIC="$2"; shift 2 ;;
        --category) CATEGORY="$2"; shift 2 ;;
        --angle) ANGLE="$2"; shift 2 ;;
        --length) LENGTH="$2"; shift 2 ;;
        --links) LINKS="$2"; shift 2 ;;
        --notes) NOTES="$2"; shift 2 ;;
        --output) OUTPUT="$2"; shift 2 ;;
        --no-db) NO_DB=true; shift ;;
        --interactive) INTERACTIVE=true; shift ;;
        -h|--help) usage 0 ;;
        *) echo "Opzione sconosciuta: $1" >&2; usage 1 ;;
    esac
done

if [[ -z "$TOPIC" || -z "$CATEGORY" ]]; then
    INTERACTIVE=true
fi

# ── Dati dal DB ───────────────────────────────────────────────────────────────
ARTICLES_LIST="(nessun articolo in DB — verifica connessione Docker)"
CATEGORIES_LIST="Risparmio, Investimenti, Budgeting, Pensione, Tasse, Conti e Banche, Mindset"

if [[ "$NO_DB" == false ]]; then
    if docker compose ps db --status running -q 2>/dev/null | grep -q .; then
        RAW="$(docker compose exec -T db mysql --default-character-set=utf8mb4 \
            -ufinanzamente -pfinanzamente finanzamente -N -e "
            SELECT c.name, a.title, a.slug
            FROM magazine_articles a
            JOIN magazine_categories c ON c.id = a.category_id
            ORDER BY a.id;
        " 2>/dev/null || true)"
        if [[ -n "$RAW" ]]; then
            ARTICLES_LIST=""
            while IFS=$'\t' read -r cat title slug; do
                ARTICLES_LIST+="- ${cat} | ${title}"$'\n'"  https://finanzamente.it/magazine/${slug}"$'\n'
            done <<< "$RAW"
            ARTICLES_LIST="${ARTICLES_LIST%$'\n'}"
        else
            ARTICLES_LIST="(query articoli fallita)"
        fi

        CAT_RAW="$(docker compose exec -T db mysql --default-character-set=utf8mb4 \
            -ufinanzamente -pfinanzamente finanzamente -N -e "
            SELECT name FROM magazine_categories ORDER BY sort_order, id;
        " 2>/dev/null || true)"
        if [[ -n "$CAT_RAW" ]]; then
            CATEGORIES_LIST="$(echo "$CAT_RAW" | paste -sd ', ' -)"
        fi
    else
        ARTICLES_LIST="(container db non avviato — esegui: make up)"
    fi
fi

# ── Regole editoriali condivise ─────────────────────────────────────────────────
read -r -d '' EDITORIAL_RULES <<'RULES' || true
# RUOLO
Sei il copywriter editoriale di "Finanzamente", webapp italiana di finanza personale (target 18–45, mobile-first).
Tutto il testo è in ITALIANO. Termini tecnici English (ETF, cashflow) restano in inglese.

# VOCE — non negoziabile
- Prima persona singolare: founder-sviluppatore che ha costruito Finanzamente.
- ANTI-HYPE: "non sono un consulente finanziario", niente ricette magiche, niente toni da guru.
- Rivolgiti al lettore con "tu". Empatico, mai paternalista.
- Filosofia: consapevolezza, controllo, dati oggettivi, serenità.
- Mantra: "non puoi gestire ciò che non misuri".

# QUALITÀ OBBLIGATORIA — ANTI-FUFFA
Non scrivere articoli generici o motivazionali. Ogni sezione deve dare valore pratico.

Regole:
- Ogni esempio numerico deve indicare ipotesi, calcolo e risultato finale in €.
- Se scrivi "conviene", "potrebbe", "aiuta", spiega rispetto a cosa e con quali condizioni.
- Inserisci almeno 2 scenari concreti con cifre in €.
- Inserisci almeno una checklist decisionale o confronto strutturato.
- Distingui sempre tra fatto certo, ipotesi ed esempio didattico.
- Evita frasi tipo "riprendi il controllo" se non seguite da azioni pratiche.

# STRUTTURA (Markdown)
1. HOOK: scenario quotidiano concreto o aneddoto personale. MAI partire da una definizione.
2. Promessa di cosa imparerà il lettore.
3. Corpo: `## H2` numerate, `### H3` dove serve, separatori `---` tra sezioni principali.
4. Almeno un "Esempio pratico" con numeri reali in € e formula passo-passo se utile.
5. Conclusione titolata ("## Conclusioni") su controllo / consapevolezza / serenità.

# STILE
- Almeno una metafora concreta e visiva (inventane di nuove, non riciclare sempre le stesse).
- Spiega inline ogni sigla/termine tecnico alla prima occorrenza.
- Grassetto **solo** sui concetti-chiave.

# AUTOREVOLEZZA (E-E-A-T)
- Cita solo fonti REALI e verificabili, in base al tema:
  ISTAT/Banca d'Italia/BCE/Eurostat, INPS/COVIP, Agenzia Entrate, studi accademici con autore+anno.
- NON inventare statistiche, paper o numeri: se non hai fonte certa, resta qualitativo.
- Link esterni a fonti ufficiali. Interlinking con URL `https://finanzamente.it/magazine/<slug>`.

# COMPLIANCE MODULARE
- Disclaimer SOLO se il tema tocca decisioni finanziarie, fiscali, legali, previdenziali o di investimento.
- Scegli il professionista adatto al tema (consulente, patronato, commercialista, CAF, notaio, avvocato).
- Fondo emergenza, TFR, "investi solo in ciò che comprendi" SOLO se pertinenti. Non inserirli per abitudine.

# PRODOTTO (leggero)
- Cita Finanzamente solo se naturale: "facilitatore di consapevolezza" (Cashflow, Multi-Household, tracciamento spese).
- Quando citi Finanzamente, ricorda che quaderno o Excel vanno bene uguale.
- Emoji solo nei callout (💡).

# CONVENZIONI IT
€, decimali con virgola (42,50 €), migliaia con punto (1.000€).

# REVISIONE FINALE OBBLIGATORIA
Dopo la prima bozza:
1. Rileggi come editor severo.
2. Trova paragrafi vaghi, esempi incompleti, promesse non dimostrate.
3. Riscrivi e consegna SOLO la versione migliorata.

# OUTPUT ARTICOLO (consegna questi campi in blocchi separati)
- title (max ~70 caratteri)
- slug (kebab-case)
- excerpt (1-3 frasi, max ~200 caratteri)
- meta_title (max 60 caratteri, NON vuoto)
- meta_description (150-160 caratteri, NON vuoto)
- content (Markdown)
- category
- author_name: Redazione Finanzamente
- reading_time_minutes (stima a 200 parole/min)
- is_ai_assisted: true
RULES

if [[ "$INTERACTIVE" == true ]]; then
    PROMPT="$(cat <<EOF
Devi scrivere un articolo per il magazine Finanzamente. Procedi in DUE FASI distinte.
NON saltare la Fase 1. NON scrivere l'articolo finché l'utente non conferma il brief.

---

## FASE 1 — COLLECT BRIEF (domande all'utente)

Presentati brevemente e ponigli le domande sotto, una alla volta o in piccoli gruppi logici.
Usa lo strumento **AskQuestion** quando offri scelte predefinite (categoria, lunghezza, tono esempi).

### Domande obbligatorie
1. **Argomento**: di cosa deve parlare l'articolo? (titolo provvisorio o idea grezza)
2. **Categoria**: quale categoria magazine? Opzioni: ${CATEGORIES_LIST}
3. **Angolo/tesi**: qual è il punto di vista? Cosa deve capire o fare il lettore dopo la lettura?
4. **Esempi numerici**: hai cifre reali da usare (stipendio, affitto, risparmio mensile) o preferisci esempi ipotetici ma realistici?
5. **Interlinking**: quali articoli già pubblicati linkare? (mostra elenco sotto; suggerisci 1-3 pertinenti)
6. **Vincoli**: c'è qualcosa da evitare? (es. niente menzione prodotto, niente fondo emergenza, focus solo pratico)

### Domande opzionali (chiedi se servono chiarimenti)
7. **Lunghezza**: default 900-1400 parole (~5-7 min) — va bene o preferisci più corto/lungo?
8. **Pubblico specifico**: dipendente, freelance, coppia, genitori…?
9. **Hook**: hai un'apertura in mente (scenario quotidiano, aneddoto) o lo proponi tu?

### Chiusura Fase 1
- Riassumi il brief in 5-8 bullet point.
- Chiedi esplicitamente: **"Confermi? Posso procedere con la scrittura?"**
- Attendi conferma prima di passare alla Fase 2.

---

## FASE 2 — SCRITTURA (solo dopo conferma brief)

${EDITORIAL_RULES}

Applica il brief raccolto in Fase 1. Lunghezza target: ${LENGTH} (salvo diversa indicazione dell'utente).

# ARTICOLI ESISTENTI (per interlinking — usa slug reali)
${ARTICLES_LIST}
EOF
)"
else
    if [[ -z "$ANGLE" ]]; then
        ANGLE="Approccio pratico con esempi numerici, checklist e zero fuffa motivazionale."
    fi

    LINKS_BLOCK="$LINKS"
    if [[ -z "$LINKS_BLOCK" ]]; then
        LINKS_BLOCK="Scegli 1-3 articoli pertinenti dall'elenco sotto (usa slug reali, non inventarli)."
    fi

    NOTES_BLOCK=""
    if [[ -n "$NOTES" ]]; then
        NOTES_BLOCK="- Note specifiche: $NOTES"
    fi

    PROMPT="$(cat <<EOF
Genera un articolo per il magazine Finanzamente seguendo TUTTE le regole sotto.
Non consegnare la bozza: rileggi, migliora e consegna solo la versione finale.

---

${EDITORIAL_RULES}

# BRIEF DI QUESTO ARTICOLO
- Argomento: ${TOPIC}
- Categoria: ${CATEGORY}
- Angolo/tesi: ${ANGLE}
- Lunghezza target: ${LENGTH}
- Articoli da linkare: ${LINKS_BLOCK}
${NOTES_BLOCK}

# ARTICOLI ESISTENTI (per interlinking — usa slug reali)
${ARTICLES_LIST}
EOF
)"
fi

if [[ -n "$OUTPUT" ]]; then
    printf '%s\n' "$PROMPT" > "$OUTPUT"
    echo "Prompt salvato in: $OUTPUT"
else
    printf '%s\n' "$PROMPT"
fi
