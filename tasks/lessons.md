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

---

## Lezioni Widget Distribuzione Spese & UX Dashboard (2026-04-20)

### Sidebar layout — `lg:static lg:block` sovrascrive `flex flex-col`
- Strutturare la sidebar come `flex flex-col` non funziona se su desktop è dichiarato `lg:block`.
- `display: block` di Tailwind sovrascrive `display: flex`, rendendo inutile tutta la struttura flex.
- **Fix**: sostituire `lg:block` con `lg:flex` per preservare il layout flex su tutti i breakpoint.
- **Regola**: quando un elemento usa `flex flex-col` per ancorare contenuto in fondo (`shrink-0`), verificare che nessun breakpoint sovrascriva `display` con `block`.

### Sidebar — `absolute bottom-0` vs flex flow
- Il pattern `absolute bottom-0` per ancorare elementi in fondo alla sidebar causa sovrapposizione con la nav se la nav ha un'altezza fissa `h-[calc(...)]`.
- **Fix corretto**: sidebar `flex flex-col`, nav `flex-1 min-h-0 overflow-y-auto`, footer `shrink-0` in flusso normale.
- **Regola**: preferire il flusso flex naturale a `absolute` per elementi fissi in sidebar; è più robusto al variare del contenuto.

### DebtCredit — `amount` vs saldo residuo
- Il campo `amount` del modello `DebtCredit` è l'importo originale del debito, non il saldo attuale.
- Il saldo residuo si calcola con `getRemainingAmount()` = `initial_amount - paid_amount`.
- In dashboard, usare `sum('amount')` mostrava il debito originale invece del residuo, creando incoerenza visiva con la pagina Debiti/Crediti.
- **Fix**: nei `map()` usare `$dc->getRemainingAmount()`; per i totali aggregati usare `SUM(COALESCE(initial_amount, amount) - COALESCE(paid_amount, 0))` via `selectRaw`.
- **Regola**: ogni volta che si mostrano importi di DebtCredit, distinguere sempre tra importo originale (`amount`) e saldo residuo (`getRemainingAmount()`). La dashboard deve sempre mostrare il saldo residuo.

### PHP `json_encode(round(...))` — int vs float nelle asserzioni
- `round(1000.0, 2)` in PHP produce `1000` (int) che `json_encode()` serializza come `1000`, non `1000.0`.
- I test PHPUnit che assertivano `1000.0` fallivano perché il JSON conteneva `1000`.
- **Fix**: usare interi nelle asserzioni (`1000`, `50`) quando i valori non hanno decimali significativi.
- **Regola**: nelle asserzioni su JSON response, usare il tipo che PHP effettivamente serializza — non assumere che i float rimangano float.

### GitHub Actions — versioni azioni inesistenti
- `actions/checkout@v6`, `actions/setup-node@v6`, `actions/upload-artifact@v7` non esistono.
- Causano fallimenti CI silenziosi difficili da diagnosticare.
- **Fix**: usare `@v4` per `checkout`, `setup-node`, `upload-artifact`.
- **Regola**: quando si scrive un workflow CI, verificare sempre le versioni delle action su marketplace.github.com prima di committare.

### `multi_replace_string_in_file` — rischio di merge accidentale
- Sostituzioni multiple sullo stesso file possono causare merge errati se le stringhe di contesto si sovrappongono.
- In un caso è stata persa la chiusura `];` di un FormRequest, causando un ParseError.
- **Fix**: dopo ogni `multi_replace`, verificare con `php -l` o `get_errors` che la sintassi sia corretta.
- **Regola**: dopo modifiche a file PHP con `replace_string_in_file`, eseguire sempre una verifica sintattica.

### UX — badge "Mai usata" nelle categorie
- Mostrare `transactions_count` su ogni card di categoria permette di identificare immediatamente le categorie inutilizzate.
- Usare `withCount('transactions')` sul query builder è la soluzione ottimale (1 query invece di N).
- **Regola**: quando si mostra una lista di entità che hanno relazioni contabili, includere sempre il conteggio per dare contesto all'utente.

### UX — box riepilogo ridondante
- I 3 box "Entrate 9 / Uscite 39 / Spese Fisse 11" in cima alla pagina categorie non aggiungevano valore: le stesse info erano visibili nei titoli di sezione.
- Su mobile occupavano spazio utile visibile al primo accesso.
- **Regola**: prima di aggiungere un riepilogo, verificare se le stesse informazioni sono già accessibili nell'interfaccia. In caso affermativo, eliminare la ridondanza.

# Lessons Learned - Social Share UI

- Tutti i pulsanti di condivisione social devono avere lo stesso peso visivo: stesso padding, dimensioni, font-weight, nessun pulsante evidenziato rispetto agli altri (no background più scuro o contrasto eccessivo su uno solo).
- Evitare di usare uno stile "selected" o "active" su un solo social (es. X/Twitter) se non è effettivamente selezionato dall’utente.
- Usare colori coerenti con il brand di ciascun social solo per icona e testo, non per lo sfondo (o usare uno sfondo neutro per tutti).
- L’ordine dei pulsanti deve essere neutro o ragionato per target, ma senza dare priorità visiva a uno specifico social.
- Se si usa uno sfondo scuro per X, va usato uno sfondo equivalente per tutti gli altri, oppure tutti su sfondo chiaro/neutro.
- Testare sempre la resa mobile: i pulsanti devono essere facilmente cliccabili e non "sbilanciati" visivamente.

# Lessons Learned - Image Optimization

- Le immagini di copertina degli articoli devono essere convertite in WebP e ridimensionate a max 1200px per ottimizzare performance e spazio.
- Il supporto GD per JPEG e WebP va abilitato a livello di Dockerfile con le opzioni --with-jpeg --with-webp e i pacchetti dev corretti.
- Per immagini molto grandi (es. 6000px), aumentare il memory_limit PHP durante la conversione batch.
- Aggiornare sempre il path nel DB dopo la conversione batch per evitare riferimenti a file non più esistenti.
- Usare Intervention Image v3 per compatibilità Laravel 12+ e PHP 8.2+.
- Testare la conversione sia su upload singolo che su batch di immagini esistenti.

- In Tailwind CSS v4, tutte le utility devono essere dopo @import "tailwindcss"; nessuna regola custom prima, altrimenti @apply non funziona.
- Se si usa una palette custom (es. solo primary, accent, surface), tutte le utility devono essere mappate su questi colori: niente bg-white, slate, emerald, ecc.
- Per Alpine.js, aggiungere sempre [x-cloak] { display: none !important; } subito dopo gli import CSS per evitare flicker su x-show.
- Se una classe Tailwind non viene riconosciuta, controllare che non sia un colore di default non incluso nella palette custom.
- Per evitare errori PHP con classi final (es. LinkRenderer di league/commonmark), usare il pattern di delega anonima invece dell’estensione diretta.
- Quando si crea un wrapper/delegante anonimo per un renderer di librerie esterne, verificare SEMPRE quali interfacce implementa la classe originale (es. `ConfigurationAwareInterface` di `League\Config`) e implementarle tutte nel wrapper, delegando i metodi. Altrimenti la libreria non chiamerà `setConfiguration()` e il renderer lancerà un'eccezione su proprietà non inizializzata in produzione.
- Ogni macro `Str::` o helper registrato in `AppServiceProvider` deve avere un test `Unit` dedicato (vedi `MarkdownWithNofollowTest`) che ne verifichi: rendering base, logica specifica e assenza di eccezioni.
- Quando si aggiorna la versione PHP (es. 8.5), correggere tutte le costanti deprecate (es. PDO::MYSQL_ATTR_SSL_CA → \Pdo\Mysql::ATTR_SSL_CA) nei config.
- Testare sempre la pipeline di build Docker dopo modifiche a GD o estensioni PHP: una build parziale può lasciare il container senza supporto immagini.