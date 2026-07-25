# Redesign homepage — ton of voice + posizionamento

## 1. Analisi competitor (fatta prima del redesign)

| Competitor | Posizionamento homepage | Ton of voice | Cosa impariamo |
|---|---|---|---|
| **Wallet (BudgetBakers)** | Completezza multi-conto, coppie/famiglie | Neutro, globale, tradotto dall'inglese | Feature-list piatta: poco memorabile |
| **Spendee** | UI colorata, portafogli condivisi | Friendly, giovane | Il visual fa più della copy |
| **Fast Budget** | "Gestisci le tue finanze personali pianificando le tue spese e i tuoi budget" + badge 🇮🇹 | Funzionale, elenco di moduli | Il badge Italia funziona; la headline generica no |
| **Monefy / Money Manager** | Semplicità estrema, 2 tocchi | Minimalista | Il segmento "solo spese" è saturo |
| **Monavio** | "Prendi il controllo dei tuoi soldi. Tutti quanti." Carichi un file, l'IA fa il resto | Assertivo, frasi brevissime, security-heavy | Ottima cadenza; però fa dell'assenza di integrazioni un vessillo difensivo (da NON copiare) |
| **TIMONE** (competitor diretto IT, 2025) | "Tieni il timone delle tue finanze" — 2 minuti al giorno, coach, mutui/tasse Italia | Metafora nautica + metodo + coach | Il rivale più vicino: presidia "metodo + Italia". Dobbiamo differenziarci su profondità, non su semplicità |
| **Monarch Money** (US) | "Your home base for money clarity" — verticale sulle coppie | Confident, adulto, benefit-driven | Il posizionamento stretto converte più della feature-parity |
| **YNAB** | "Get good at money" — metodo + outcome emotivi | Provocatorio ("Bad at money?"), colpevolizzante | Efficace in USA, **inadatto** al target IT 18–45: da evitare il registro paternalistico |
| **Portmoneo** | OCR scontrini + pronto per il fisco | AI-first | Il fisco è un angolo vendibile |

### Conclusioni
**Table stakes** (li ha chiunque, non differenziano): spese per categoria, budget, grafici, ricorrenti, dashboard, freemium, GDPR.

**Due poli occupati:** semplicità estrema (Monefy/TIMONE) e automazione/IA (Wallet/Monavio/Portmoneo).
**Polo libero:** *profondità* — chi ha già l'abitudine di tracciare e vuole arrivare a patrimonio, investimenti e fisco senza cambiare strumento.

**Errore ricorrente del mercato:** headline generiche ("gestisci le tue finanze") che non dicono per chi è.

## 2. Confronto con ciò che offriamo davvero (verificato nel codice)

### Differenziatori reali, nessun competitor li ha tutti
- **Patrimonio unificato + investimenti veri**: `Investment`, `InvestmentPac`, `InvestmentAsset`, `AssetAllocation` con indice di rischio sintetico 1–7, `InvestmentAnalysis` (ROI fotovoltaico/auto). TIMONE si ferma a P&L.
- **Dashboard componibile con widget a formula** (`FormulaWidget`, `FinancialVariable`, marketplace template, `DashboardLayout`). **Unico sul mercato.**
- **Fisco italiano operativo**: `TaxDeductions` con export PDF + ZIP allegati per il 730 (non solo "stima tasse").
- **Nuclei con due modalità**: `isDebtBalancingMode()` (percentuali + suggeritore turni su spese fisse) vs `isSharedWalletMode()`, più trasferimenti tra nuclei con approvazione.
- **Buoni pasto a lotti** (`MealVoucherLot*` con valore unitario).
- **Simulazioni FIRE/crisi aperte senza account** (`/simulazioni`) → lead magnet già esistente e mai valorizzato in home.
- **Lifestyle Inflation Score** — metrica proprietaria.
- Rilevamento ricorrenze e duplicati, multivaluta con FX, split su più conti, PWA installabile.

### Da NON affermare (non esiste o è condizionato)
- Integrazioni automatiche con istituti / aggregatori → **non esistono**
- OCR scontrini come feature standard → solo via Telegram+Inbox Pro con `MISTRAL_API_KEY`
- Gestione IVA → solo placeholder in `ModuleAccessService`
- Import Google Drive → richiede credenziali Google
- Prezzi di mercato live → dipendono da API key

### Vincolo di copy richiesto
Nessuna allusione a operazioni bancarie **e** nessuna formula difensiva tipo "senza operazioni bancarie" / "nessun conto da collegare". Il tema non va nominato: si parla in positivo di *controllo su cosa entra nell'app*.

## 3. Ton of voice definito
- **Adulto, competente, concreto.** "Tu" singolare, presente indicativo, frasi brevi.
- **Zero paternalismo** (no YNAB "sei scarso coi soldi") e **zero allarmismo**.
- **Numeri e nomi concreti** al posto degli aggettivi: "indice di rischio 1–7", "export PDF per il 730", non "analisi potentissime".
- **Nessun hype IA**, nessun superlativo, nessuna promessa di rendimento.
- **Onestà sui limiti**: badge Pro espliciti sulle funzioni a pagamento → costruisce fiducia.
- **Framing positivo sul dato**: "decidi tu cosa tracciare" invece di negare integrazioni.

## Plan
- [x] Analisi competitor (9 player, IT + US)
- [x] Inventario feature reali dal codice, separando implementato / condizionato / assente
- [x] Definizione ton of voice e nuova struttura
- [x] Riscrittura `resources/views/welcome.blade.php`: hero, 4 pilastri, highlight patrimonio, dashboard componibile, Italia/fisco, nuclei, simulazioni, FAQ, CTA
- [x] Badge Pro sulle funzioni a pagamento (onestà)
- [x] Nuova FAQ + JSON-LD `FAQPage` in `StructuredDataService`
- [x] SEO meta/OG/Twitter riscritti coerenti col nuovo posizionamento
- [x] `config/plans.php`: rimossa terminologia bancaria dalle feature list mostrate in home
- [x] Mantenuti invariati **tutti** i controlli condizionali: `preLaunchMode`, `waitlistEnabled`, `proEnabled`, `Route::has()`, magazine
- [x] Test: Feature test copy/flag homepage + aggiornamento E2E specs
- [x] Gate `make test` (equivalente host: PHPUnit su SQLite)
- [x] Gate `make pint-check`
- [ ] Gate `make playwright` — non eseguibile: VM senza Docker (vedi Review)

## Review
### Cosa è cambiato
- **`resources/views/welcome.blade.php`** riscritto. Struttura: hero → 4 pilastri (Registra / Pianifica / Patrimonio / Insieme) → highlight patrimonio con demo asset allocation → highlight dashboard componibile (differenziatore unico) → sezione Italia (730, buoni pasto, formati) → nuclei con le due modalità → simulazioni aperte → FAQ → pricing → CTA finale. Le 9 card indistinte sono diventate 4 pilastri leggibili su mobile.
- **Ton of voice**: rimosse tutte le allusioni bancarie *e* le negazioni difensive. Il controllo sul dato è raccontato in positivo ("decidi tu cosa entra").
- **Onestà**: badge `Pro` inline sulle funzioni a pagamento; nessun claim su OCR, IVA o integrazioni.
- **SEO**: title/description/OG/Twitter riscritti; aggiunto schema `FAQPage` accanto a `WebSite` + `SoftwareApplication`.
- **Flag preservati**: `$preLaunchMode`, `$waitlistEnabled`, `$proEnabled`, `Route::has('plan.select'|'register'|'login')`, magazine nav/footer — invariati nella logica, solo copy dei label toccata dove necessario.

### Verifica
- PHPUnit: verde sull'host (SQLite in-memory come da `.env.testing`).
- Pint: verde.
- Playwright: la VM di questo agente non ha Docker né PHP-FPM/Nginx, quindi l'istanza E2E su :8081 non è avviabile. Gli spec sono stati aggiornati per il nuovo copy (`e2e/public/welcome.spec.ts`, `e2e/public/simulations.spec.ts`) e la copertura è stata duplicata a livello di Feature test PHPUnit, che gira in CI insieme al workflow Playwright.
