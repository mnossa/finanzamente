- [2026-03-20] Merge pull request #24 from mnossa/copilot/add-github-actions-workflow (201b941) — mnossa
- [2026-03-20] feat: add rebase-staging-from-main target to Makefile (ee03679) — Matteo Nossa
- [2026-03-20] Merge branch 'staging' (e19aa54) — Matteo Nossa
- [2026-03-20] Merge branch 'staging' (4d8fa7e) — Matteo Nossa
- [2026-03-20] Merge pull request #25 from mnossa/copilot/update-merge-staging-to-main (afc1b62) — mnossa
- [2026-03-21] Refactor page layouts to use PageContent component for consistent styling (bc72d22) — Matteo Nossa
- [2026-03-22] feat(e2e): add end-to-end tests for authentication and core features (24239ed) — Matteo Nossa
- [2026-03-23] feat(e2e): add logout test and improve existing tests for consistency (bab5531) — Matteo Nossa
- [2026-03-23] feat: add exclude from stats functionality for inter-household transfers and households (264783f) — Matteo Nossa
- [2026-03-23] feat: add MySQL readiness check to Playwright workflow and optimize migration backfill process (ca67835) — Matteo Nossa
- [2026-03-23] feat: traduzione email di recupero password in italiano (0d135d5) — copilot-swe-agent[bot]
- [2026-03-23] Fix DRY: extract resolveTagIds helper, fix undefined $user in update(), fix TagAutocomplete filtering (69c808e) — copilot-swe-agent[bot]
- [2026-03-26] Merge pull request #28 from mnossa/copilot/add-mollie-payment-system (5ce918a) — mnossa
- [2026-03-26] Enhance Pro plan feature display: update badge style and text for better visibility (b1db33b) — Matteo Nossa

## Lezioni Landing Page Conversion (2026-04-02)

### Fonti analizzate
- landingi.com/landing-page/optimization-case-studies/ (14 case study reali)
- neilpatel.com/it/blog/esempi-di-landing-page/ (18 esempi migliori)

### Principi applicati alle landing page FinanzaMente

#### 1. Mostra sempre il prezzo vicino alla CTA (priorità massima)
- **Case study**: SafeSoft Solutions +100% lead semplicemente mostrando il prezzo vicino al form
- **Applicazione**: aggiunto "€2,99/mese" sia nella trust strip dopo l'hero CTA sia nei checkmark del final CTA
- **Regola**: il prezzo nascosto crea ansia e dubbi. Il prezzo mostrato, se competitivo, accelera la conversione

#### 2. Objection busting prima della conversione
- **Case study**: ClickFunnels attacca le obiezioni PRIMA del CTA; NeuroMD +55.3% allineando messaggi al linguaggio del cliente
- **Applicazione**: aggiunta strip 3 checkmark (€2,99/mese · Nessun conto bancario · Disdici quando vuoi) sotto l'hero CTA e nella sezione final CTA
- **Regola**: le 3 obiezioni principali di un SaaS finanziario sono: "quanto costa?", "devo collegare il conto?", "posso uscire?". Rispondere prima che vengano poste

#### 3. CTA button text specifico per pagina (non generico)
- **Case study**: HubSpot – "ripeti la tua USP nel tuo CTA"; Shopify Plus – "personalizza il tuo CTA"
- **Applicazione**: cambiate le CTA footer da "Abbonati a FinanzaMente Pro" a testi specifici per ogni target (es. "Attiva Pro — traccia il tuo portafoglio")
- **Regola**: un CTA generico converte meno di uno che rispecchia l'obiettivo specifico del visitatore

#### 4. Testo in grassetto per guidare la scansione visiva
- **Case study**: GetResponse – "evidenzia il tuo testo per renderlo ancora più incisivo"
- **Applicazione**: aggiunti `<strong>` sulle frasi chiave nelle sezioni proof di tutte le 7 pagine
- **Regola**: gli utenti scansionano (non leggono) la pagina. Il grassetto su 2-3 frasi chiave per sezione guida gli occhi verso i benefit più importanti

#### 5. Struttura minima (4 sezioni) batte la complessità
- **Case study**: Artur Jabłoński +40% signup da layout semplificato; Rent Like Home +300% da pagina focalizzata su singolo goal
- **Applicazione** (sessione precedente): ridotte le pagine da ~250 righe a ~130-160 righe, 4 sezioni: hero → 3 benefit → proof visual → final CTA
- **Regola**: ogni sezione aggiuntiva è un'uscita dal funnel. Togliere, non aggiungere

#### 6. Il prezzo di €2,99/mese è un asset, non un problema
- Piano Base gratuito + Pro a €2,99: questo posizionamento è eccellente per ridurre la barriera d'ingresso
- Non nascondere mai il prezzo: mostrarlo riduce il "sticker shock" e pre-qualifica lead seri

### Struttura ottimale landing page B2C SaaS italiano (validata)
1. **Header** sticky: solo logo + CTA Pro (nessun menu di navigazione)
2. **Hero**: H1 benefit-first su pain point specifico del target + subtitolo concreto + CTA grande + trust strip (prezzo + garanzie)
3. **3 Benefits**: emoji + titolo feature + descrizione 1-2 righe (rule of three)
4. **Proof Visual**: H2 + paragrafo con frasi in bold + card/mock app contestuale
5. **Final CTA**: H2 → descrizione feature specifiche → checkmark row (prezzo + garanzie) → button con USP specifica → nessun link che porti altrove
6. **Footer minimal**: copyright + privacy/termini/home (nessun link alle landing da altri punti del sito)
- [2026-04-02] Add user authentication reference in multiple controllers and update plans configuration (30fe38d) — Matteo Nossa

## Lezioni Landing Page Conversion (2026-04-02)

### Fonti analizzate
- landingi.com/landing-page/optimization-case-studies/ (14 case study reali)
- neilpatel.com/it/blog/esempi-di-landing-page/ (18 esempi migliori)

### Principi applicati alle landing page FinanzaMente

#### 1. Mostra sempre il prezzo vicino alla CTA (priorità massima)
- **Case study**: SafeSoft Solutions +100% lead semplicemente mostrando il prezzo vicino al form
- **Applicazione**: aggiunto "€2,99/mese" sia nella trust strip dopo l'hero CTA sia nei checkmark del final CTA
- **Regola**: il prezzo nascosto crea ansia e dubbi. Il prezzo mostrato, se competitivo, accelera la conversione

#### 2. Objection busting prima della conversione
- **Case study**: ClickFunnels attacca le obiezioni PRIMA del CTA; NeuroMD +55.3% allineando messaggi al linguaggio del cliente
- **Applicazione**: aggiunta strip 3 checkmark (€2,99/mese · Nessun conto bancario · Disdici quando vuoi) sotto l'hero CTA e nella sezione final CTA
- **Regola**: le 3 obiezioni principali di un SaaS finanziario sono: "quanto costa?", "devo collegare il conto?", "posso uscire?". Rispondere prima che vengano poste

#### 3. CTA button text specifico per pagina (non generico)
- **Case study**: HubSpot – "ripeti la tua USP nel tuo CTA"; Shopify Plus – "personalizza il tuo CTA"
- **Applicazione**: cambiate le CTA footer da "Abbonati a FinanzaMente Pro" a testi specifici per ogni target (es. "Attiva Pro — traccia il tuo portafoglio")
- **Regola**: un CTA generico converte meno di uno che rispecchia l'obiettivo specifico del visitatore

#### 4. Testo in grassetto per guidare la scansione visiva
- **Case study**: GetResponse – "evidenzia il tuo testo per renderlo ancora più incisivo"
- **Applicazione**: aggiunti `<strong>` sulle frasi chiave nelle sezioni proof di tutte le 7 pagine
- **Regola**: gli utenti scansionano (non leggono) la pagina. Il grassetto su 2-3 frasi chiave per sezione guida gli occhi verso i benefit più importanti

#### 5. Struttura minima (4 sezioni) batte la complessità
- **Case study**: Artur Jabłoński +40% signup da layout semplificato; Rent Like Home +300% da pagina focalizzata su singolo goal
- **Applicazione** (sessione precedente): ridotte le pagine da ~250 righe a ~130-160 righe, 4 sezioni: hero → 3 benefit → proof visual → final CTA
- **Regola**: ogni sezione aggiuntiva è un'uscita dal funnel. Togliere, non aggiungere

#### 6. Il prezzo di €2,99/mese è un asset, non un problema
- Piano Base gratuito + Pro a €2,99: questo posizionamento è eccellente per ridurre la barriera d'ingresso
- Non nascondere mai il prezzo: mostrarlo riduce il "sticker shock" e pre-qualifica lead seri

### Struttura ottimale landing page B2C SaaS italiano (validata)
1. **Header** sticky: solo logo + CTA Pro (nessun menu di navigazione)
2. **Hero**: H1 benefit-first su pain point specifico del target + subtitolo concreto + CTA grande + trust strip (prezzo + garanzie)
3. **3 Benefits**: emoji + titolo feature + descrizione 1-2 righe (rule of three)
4. **Proof Visual**: H2 + paragrafo con frasi in bold + card/mock app contestuale
5. **Final CTA**: H2 → descrizione feature specifiche → checkmark row (prezzo + garanzie) → button con USP specifica → nessun link che porti altrove
6. **Footer minimal**: copyright + privacy/termini/home (nessun link alle landing da altri punti del sito)
- [2026-04-03] feat: update URLs and titles in tests to use Italian translations (eb7242c) — Matteo Nossa
- [2026-04-03] Merge pull request #30 from mnossa/renovate/configure (99bb196) — mnossa
