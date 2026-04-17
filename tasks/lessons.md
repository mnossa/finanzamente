## Lezioni Landing Page Conversion (2026-04-02)

### Fonti analizzate
- landingi.com/landing-page/optimization-case-studies/ (14 case study reali)
- neilpatel.com/it/blog/esempi-di-landing-page/ (18 esempi migliori)

### Principi applicati alle landing page Finanzamente

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
- **Applicazione**: cambiate le CTA footer da "Abbonati a Finanzamente Pro" a testi specifici per ogni target (es. "Attiva Pro — traccia il tuo portafoglio")
- **Regola**: un CTA generico converte meno di uno che rispecchia l'obiettivo specifico del visitatore

#### 4. Testo in grassetto per guidare la scansione visiva
- **Case study**: GetResponse – "evidenzia il tuo testo per renderlo ancora più incisivo"
- **Applicazione**: aggiunti `<strong>` sulle frasi chiave nelle sezioni proof di tutte le 7 pagine
- **Regola**: gli utenti scansionano (non leggono) la pagina. Il grassetto su 2-3 frasi chiave per sezione guida gli occhi verso i benefit più importanti

#### 5. Struttura minima (4 sezioni) batte la complessità
- **Case study**: Artur Jabłoński +40% signup da layout semplificato; Rent Like Home +300% da pagina focalizzata su singolo goal
- **Applicazione**: ridotte le pagine da ~250 righe a ~130-160 righe, 4 sezioni: hero → 3 benefit → proof visual → final CTA
- **Regola**: ogni sezione aggiuntiva è un'uscita dal funnel. Togliere, non aggiungere

#### 6. Il prezzo di €2,99/mese è un asset, non un problema
- Piano Base gratuito + Pro a €2,99: posizionamento eccellente per ridurre la barriera d'ingresso
- Non nascondere mai il prezzo: mostrarlo riduce lo sticker shock e pre-qualifica i lead seri

### Struttura ottimale landing page B2C SaaS italiano (validata)
1. **Header** sticky: solo logo + CTA Pro (nessun menu di navigazione)
2. **Hero**: H1 benefit-first su pain point specifico del target + subtitolo concreto + CTA grande + trust strip (prezzo + garanzie)
3. **3 Benefits**: emoji + titolo feature + descrizione 1-2 righe (rule of three)
4. **Proof Visual**: H2 + paragrafo con frasi in bold + card/mock app contestuale
5. **Final CTA**: H2 → descrizione feature specifiche → checkmark row (prezzo + garanzie) → button con USP specifica → nessun link che porti altrove
6. **Footer minimal**: copyright + privacy/termini/home (nessun link alle landing da altri punti del sito)

---

## Lezioni UX Pricing & Pre-lancio (2026-04-09)

### Pricing card — feature collapsibili

#### Problema: `{{ }}` in attributi HTML inline produce HTML escaping
- `data-extra="{{ $scope }}"` dentro un `@foreach` produceva `&quot;` nel DOM
- **Fix**: `@if($i >= 6) data-extra="{{ $scope }}"@endif` come blocco separato
- **Regola**: quando `data-*` attributi sono selettori JS, verificare il DOM renderizzato, non solo il template Blade

#### Problema: JS toggle seleziona l'elemento sbagliato
- `data-extra` sul primo elemento già visibile → `isExpanded` sempre `false` → tutto si nascondeva
- **Fix**: `data-extra` solo su elementi nascosti (`$i >= 6`); check con `isExpanded = !extras[0].classList.contains('hidden')`
- **Regola**: applicare attributi di controllo JS SOLO agli elementi che devono essere controllati, mai a quelli sempre visibili

#### Problema: Tally `querySelector('#')` errore JS
- `href="#"` come trigger causava `querySelector('#')` → eccezione
- **Fix**: `href="javascript:void(0)"`
- **Regola**: i link trigger per SDK di terze parti non usano `href="#"` — usare `javascript:void(0)` o `<button type="button">`

---

### Label fuorvianti — "730" e aspettative utente

- "Detrazioni fiscali e 730" implicava che l'app generasse la dichiarazione dei redditi
- La funzionalità è un tracker (tag transazioni come detraibili + esporta PDF per il CAF)
- **Fix**: rinominato ovunque in "Tracker spese detraibili (mediche, mutuo…)" — sidebar, titolo pagina, `ModuleAccessService`, landing `lavoratori.blade.php`
- **Regola**: il nome di un modulo descrive COSA FA l'utente nell'app, non il contesto burocratico esterno

---

### Tally.so — integrazione e best practice

- SDK caricato via `@push('scripts')` per garantire un solo `<script>` anche se il partial è incluso più volte
- Il partial `tally-survey.blade.php` è no-op se `TALLY_SURVEY_FORM_ID` è vuoto — sicuro da includere sempre
- **Regola**: i partial che dipendono da config esterni devono avere un guard `@if(config(...))` come prima riga

---

### Webhook Tally → Brevo

- Autenticato via HMAC-SHA256: `base64(hmac(rawBody, TALLY_WEBHOOK_SECRET))`
- Escluso da CSRF con `validateCsrfTokens(except: ['/webhooks/*'])` in `bootstrap/app.php` (Laravel 11 style)
- Se `TALLY_WEBHOOK_SECRET` è vuoto → 501, mai accettare payload non firmati
- Struttura payload Tally: `data.fields[].type === 'INPUT_EMAIL'` per trovare l'email
- Riusa `WaitlistService::subscribe()` → stesso double opt-in Brevo della waitlist diretta
- **Regola**: webhook di terze parti vanno sempre firmati e verificati prima di processare dati

---

### Pagina pre-lancio `/in-arrivo`

- Redirect della CTA "Inizia gratis" piano Base quando `prelaunch.enabled` o `waitlist_enabled` sono `true`
- `noindex` nell'head: pagina temporanea, non deve essere indicizzata
- Testo al singolare (progetto indie, sviluppatore unico) — evitare "stiamo", "ci", "noi"
- **Regola**: nelle pagine pre-lancio non usare "coming soon" generico — spiegare onestamente e dare azioni concrete

---

### Ordine feature pricing card (conversion-first)

- Le prime 6 feature visibili determinano la conversione; le successive sono per chi è già convinto
- **Base** (6 visible): Dashboard, Transazioni illimitate, Budget mensile, Import CSV, Categorie personalizzate, Fino a 5 conti
- **Pro** (6 visible): Tutto del Base, Investimenti e portafoglio, Tracker spese detraibili, Household illimitate, Simulazioni finanziarie, Lifestyle Inflation Score
- **Regola**: mettere prima le feature che risolvono il pain point principale del target, non quelle tecnicamente più complesse

---

### Comunicazione prodotto — voce al singolare

- Il progetto è sviluppato da un singolo dev → tutta la comunicazione usa "io/sto costruendo/dimmi"
- Evitare il plurale majestatis ("stiamo costruendo", "ci" in `.blade.php`) a meno di contesti dove il brand parla come entità
- **Regola**: nelle pagine operative e pre-lancio usare sempre il singolare; è più autentico e si addice a un indie project

---

### Strategia di lancio scelta

- Non mostrare una data lontana tipo "lancio 2027": abbassa urgenza e fiducia, e trasmette prodotto troppo acerbo
- Non usare una waitlist passiva con sola email: raccoglie contatti deboli e poco segmentati
- **Scelta**: waitlist qualificata con Tally + iscrizione Brevo con double opt-in
- Il flusso corretto è: interesse → breve survey Tally → email raccolta → iscrizione Brevo → nurturing → offerta early access al lancio
- **Regola**: in pre-lancio bisogna massimizzare la qualità del segnale, non il numero grezzo di email

### Nurturing pre-lancio

- Niente email continue troppo presto: prima si raccoglie domanda reale, poi si comunica vicino al lancio
- Finestra consigliata: iniziare il nurturing serio circa 6 mesi prima del lancio effettivo
- Obiettivo delle email: validare pain point, mostrare progresso, far crescere desiderio, preparare l'offerta early bird
- **Regola**: se il lancio non è imminente, meglio meno email ma più rilevanti

## Lezioni Recurrence Detection & Test Maintenance (2026-04-17)

### Allineamento test-business logic
- Quando la logica di business evolve (es. da errore a warning su descrizione mancante), aggiornare immediatamente i test per riflettere il nuovo comportamento.
- **Regola**: Non lasciare test obsoleti; ogni modifica alla logica va accompagnata da revisione dei test correlati.

### Gestione memoria PHPUnit
- Test suite ampie possono esaurire la memoria PHP (es. memory_limit 128M non sufficiente).
- **Fix**: Impostare memory_limit esplicitamente sia in Makefile che in phpunit.xml (es. 256M o superiore per suite grandi).
- **Regola**: Se i test falliscono per memory exhausted, controllare e aumentare memory_limit in TUTTI i punti di entry (Makefile, phpunit.xml, Dockerfile se serve).

### Coerenza logica import/export
- Se la logica di importazione (es. segno amount in base a category type) cambia, aggiornare sia il codice che i test per garantire coerenza.
- **Regola**: Ogni modifica a una regola di business che impatta i dati (import, export, calcoli) richiede revisione dei test e dei dati di esempio.

### Debugging test falliti
- Analizzare sempre il motivo del fallimento: leggere il messaggio, cercare la logica aggiornata, confrontare con i test.
- Quando esegui delle migrazioni, riportarle anche verso il db e2e
- **Regola**: Non correggere "alla cieca"; capire la causa, poi aggiornare test o codice.

### Best practice generale
- Ogni fix a test o configurazione va documentato in lessons.md per evitare ricadute future.
- **Regola**: lessons.md va aggiornato ogni volta che si risolve un problema non banale di test, configurazione o logica condivisa.
