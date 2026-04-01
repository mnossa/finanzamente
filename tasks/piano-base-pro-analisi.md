# Analisi Piano Base vs Pro — Finanzamente

> Revisione strategica della suddivisione funzionale tra piano Base e piano Pro.  
> Contesto: 1 sviluppatore, nessun supporto diretto, FAQ come unico canale di aiuto, target utenti italiani 18–45 anni.
>
> **Principio guida:** il piano Base deve essere abbastanza ricco da trattenere utenti attivi a lungo termine; il piano Pro deve essere abbastanza specifico e potente da giustificare il costo mensile per chi ha quei bisogni. La conversione ideale avviene quando l'utente sente un limite naturale — non una frustrazione artificiale.

---

## 1. Stato della suddivisione — prima delle modifiche del 01/04/2026

> **Nota:** questa sezione descrive lo stato iniziale del progetto, usato come base per l'analisi. Per lo stato attuale implementato, vedi §1.1.

### Piano Base (gratuito) — stato iniziale
| Funzionalità | Note |
|---|---|
| Dashboard | Solo lettura aggregata |
| Conti bancari | **Max 3** |
| Transazioni | Illimitate |
| Categorie personalizzate | CRUD completo |
| Tag per transazioni | CRUD completo |
| Trasferimenti tra conti | Stessa household |
| Budget mensile | Per categoria |
| Import CSV/XLS | Con layout salvati |
| 1 sola household | Nessun membro invitabile |

### Piano Pro (9,90 €/mese — stato iniziale)
| Funzionalità | Note |
|---|---|
| Conti illimitati | Base ha limite 3 |
| Household illimitate + membri | Inviti, ruoli, permessi |
| Transazioni ricorrenti | Generazione automatica |
| Rimborsi | Tracciamento rimborso spese |
| Debiti e crediti | Con stato aperto/chiuso |
| Obiettivi finanziari | Con target e progress |
| Investimenti + portafoglio | Richiede `tracks_investments` |
| Asset Allocation | Chef de file strategico |
| Asset gestiti manualmente | Inserimento ISIN/ticker |
| Analisi investimenti | Dashboard avanzata |
| Simulazioni finanziarie | Proiezioni scenari |
| Integrazione Telegram | Bot + quick add |
| Inbox Telegram | Conferma transazioni da bot |
| Detrazioni fiscali / 730 | Solo se detrazioni presenti |
| Gestione IVA | Solo `partita_iva` |
| Trasferimenti tra household | Cross-household |
| Lifestyle Inflation Score | Punteggio stile vita |
| Export PDF/XLS avanzati | *(non ancora implementato)* |

---

## 1.1 Stato implementato al 01/04/2026 ✅

> Tutte le modifiche proposte nei §3.1–3.5 e nel §5 sono state implementate.

### Piano Base (gratuito) — stato attuale
| Funzionalità | Note |
|---|---|
| Dashboard | Solo lettura aggregata |
| Conti bancari | **Max 5** (era 3) ✅ |
| Transazioni | Illimitate |
| Categorie personalizzate | CRUD completo |
| Tag per transazioni | CRUD completo |
| Trasferimenti tra conti | Stessa household |
| Budget mensile | Per categoria |
| Import CSV/XLS | Con layout salvati |
| Transazioni ricorrenti | **Max 5 attive** (NUOVO) ✅ |
| Rimborsi | **Max 10 attivi** (NUOVO) ✅ |
| Debiti e crediti | **Max 5 attivi** (NUOVO) ✅ |
| Obiettivi finanziari | **Max 1 attivo** (NUOVO) ✅ |
| 1 sola household | Nessun membro invitabile |

### Piano Pro (2,99 €/mese — sconto 20% annuale = ~2,39 €/mese) — stato attuale
| Funzionalità | Note |
|---|---|
| Tutto il Base, senza limiti | |
| Conti illimitati | |
| Household illimitate + membri | Inviti, ruoli, permessi |
| Transazioni ricorrenti illimitate | |
| Rimborsi illimitati | |
| Debiti e crediti illimitati | |
| Obiettivi finanziari illimitati | |
| Investimenti + portafoglio | Richiede `tracks_investments` |
| Asset Allocation | |
| Asset gestiti manualmente | Inserimento ISIN/ticker |
| Analisi investimenti | Dashboard avanzata |
| Simulazioni finanziarie | Proiezioni scenari |
| Integrazione Telegram | Bot + quick add |
| Inbox Telegram | Conferma transazioni da bot |
| Detrazioni fiscali / 730 | Solo se detrazioni presenti |
| Gestione IVA | Solo `partita_iva` |
| Trasferimenti tra household | Cross-household |
| Lifestyle Inflation Score | Punteggio stile vita |
| Export PDF/XLS avanzati | *(prossimamente — non ancora sviluppato)* |

### Piano Business — stato attuale
- **Non attivo.** Struttura predisposta in `config/plans.php` con `coming_soon: true`
- Prezzo ipotetico: 4,99 €/mese
- Nessuna rotta, middleware o UI attivata
- Vedi §11 per l'analisi completa sul se e come attivarlo

---

## 2. Punti forti della suddivisione attuale

### ✅ Il piano Base è davvero usabile

Un utente Base può:
- Tenere traccia di **tutte le entrate e uscite** senza limiti di transazioni
- **Categorizzare e taggare** con libertà totale
- **Importare dal CSV** della propria banca — feature rara nel freemium italiano
- Seguire **budget mensili per categoria** — cuore della gestione finanziaria attiva
- Gestire **trasferimenti tra propri conti** (es. da conto corrente a risparmio)

Questo è il punto più importante: **un utente attento alle proprie finanze può farlo davvero gratuitamente**, senza sentirsi costretto al Pro per le funzioni fondamentali. Questo genera fiducia, adozione e passaparola.

### ✅ Il Pro ha massa critica

Il Pro offre una suite completa: funzionalità illimitate per tutte le aree Base, più funzionalità esclusive (investimenti, simulazioni, household multi-membro, Telegram, IVA, detrazioni, lifestyle score). Un utente che usa già il Base e vuole crescere trova nel Pro una suite coerente, non solo qualche feature isolata.

### ✅ Limiti soft sul Base anziché hard paywall

Il limite di **5 conti** e 1 household non blocca l'uso: la maggior parte degli utenti target (18–45 anni, singolo o coppia) ha 2–4 conti. Il muro si sente solo per utenti più sofisticati — che sono esattamente i candidati naturali al Pro.

---

## 3. Criticità e rischi

### ✅ 3.1 — Il limite di 3 conti era basso per alcune situazioni comuni

**Scenario tipico che sfora il limite:** conto corrente principale + conto risparmio + carta prepagata (Satispay, N26) + conto titoli. Quattro conti è la norma per chiunque abbia iniziato a diversificare, anche solo leggermente.

**Rischio:** l'utente si scontra con il muro già nelle prime settimane, prima ancora di aver valutato il Pro. Questo crea frustrazione, non desiderio.

**Alternativa consigliata:** portare il limite a **5 conti** nel Base. 3 è aggressivo per un'app finanziaria — Toshl, Money Manager, YNAB free tier usano tutti limiti più alti o illimitati. 5 copre praticamente tutti i casi base senza perdere appeal per il Pro (che rimane illimitati).

> ✅ **Risolto al 01/04/2026:** limite portato a 5 conti in `config/plans.php` (`base_limits.max_accounts = 5`). `AccountController::store()` controlla il limite e mostra un banner contestuale in `Accounts/Create.tsx`.

---

### ✅ 3.2 — Rimborsi e Debiti/Crediti: spostati nel Base con limiti

**Problema:** un utente che divide le spese con un coinquilino o tiene traccia di un prestito fatto a un amico è un utente **normale**, non avanzato. Mettere questi strumenti dietro paywall significa che chi non paga gestirà queste cose fuori dall'app (fogli Excel, note del telefono), riducendo la coerenza dei dati.

**Rimborsi** in particolare sembrano un completamento naturale delle transazioni: "ho pagato per conto di qualcuno e aspetto il rimborso" è uno scenario frequente già per studenti universitari.

**Rischio:** perdere il target più giovane (18–28) che gestisce molte spese condivise.

**Alternativa consigliata:**
- **Rimborsi semplici** → Base (singola transazione in attesa di rimborso, max 10 attivi)
- **Rimborsi avanzati** (collegamento transazioni, storico, report) → Pro
- **Debiti/Crediti Base** (solo tracciamento, senza report) → Base con limite (es. max 5 attivi)
- **Debiti/Crediti Pro** (illimitati, report, integrazione con goals) → Pro

Alternativa più semplice da implementare: **spostare entrambi nel Base con limiti quantitativi**.

> ✅ **Risolto al 01/04/2026:** rimborsi spostati nel Base (max 10 attivi), debiti/crediti spostati nel Base (max 5 attivi). Limiti verificati in `RefundController::store()` e `DebtCreditController::store()`.

---

### ✅ 3.3 — Transazioni ricorrenti: spostate nel Base con limite

**Scenario:** affitto, abbonamento Netflix, palestra. Un utente Base non può automatizzare queste voci e deve inserirle manualmente ogni mese. Questo è **lavoro ripetitivo frustrante** che non aggiunge valore percepito al piano Base.

**Rischio:** abbandono dell'app per stanchezza di inserimento manuale. Le ricorrenti sono spesso il motivo per cui si adotta un'app di finanza personale.

**Alternativa consigliata:**
- **Ricorrenti Base** → max 5 ricorrenti attive (copre i casi standard: affitto, utenze, abbonamenti principali)
- **Ricorrenti Pro** → illimitate + notifiche avanzate + previsione cashflow

> ✅ **Risolto al 01/04/2026:** transazioni ricorrenti spostate nel Base con limite di 5 attive. Limite verificato in `RecurringTransactionController::store()`.

---

### ✅ 3.4 — Obiettivi finanziari: 1 obiettivo spostato nel Base

**Problema:** "voglio risparmiare 3.000€ per le vacanze" è un goal che moltissimi utenti 18–35 anni hanno in testa già al momento del download. Se questa funzione non è disponibile nel Base, l'app perde il suo gancio emotivo più forte.

**Rischio:** l'utente usa il Base solo come registro passivo, non come strumento attivo di cambiamento finanziario. Il Pro sembrerà "troppo" per chi è già soddisfatto del registro.

**Alternativa consigliata:**
- **1 obiettivo attivo** → Base (es. "fondo emergenza" o "vacanze")
- **Obiettivi illimitati + proiezioni + collegamento a conti dedicati** → Pro

> ✅ **Risolto al 01/04/2026:** 1 obiettivo attivo spostato nel Base. Limite verificato in `FinancialGoalController::store()`.

---

### ℹ️ 3.5 — Il Pro copre segmenti diversi: analisi per il futuro

Le funzionalità Pro coprono utenti molto diversi:
- **Investitore** → Asset Allocation, Analisi, Investimenti
- **Lavoratore autonomo** → IVA, 730
- **Famiglia/coppia** → Household multi-membro, trasferimenti inter-household
- **Tech-savvy** → Telegram, Inbox
- **Pianificatore** → Simulazioni, Lifestyle Score, Goals

Un utente che vuole solo tracciare gli investimenti non ha bisogno del bot Telegram. Un freelance che vuole gestire l'IVA non vuole household multipli. Tutti pagano lo stesso prezzo.

**Rischio:** il Pro sembra "gonfio" — molte feature che non userò mai incluse nel prezzo. Questo riduce la percezione di valore per chiunque non usi almeno 60–70% delle feature Pro.

**Alternativa consigliata (a medio termine):**

| Piano | Prezzo | Contenuto |
|---|---|---|
| **Base** | Gratuito | Gestione quotidiana + limiti moderati |
| **Pro** | 2,99 €/mese | Tutto Base + planning avanzato + investimenti + household |
| **Business** *(futuro)* | 4,99 €/mese | Tutto Pro + feature professionali **nuove** (IVA avanzata, fatturazione, report contabili) |

Per ora, con 1 sviluppatore, mantenere 2 piani è la scelta giusta. Questa è un'indicazione per il futuro. Vedi §11 per l'analisi completa del dilemma 2 vs 3 piani.

> ℹ️ **Nota al 01/04/2026:** decisione presa di mantenere 2 piani (Base + Pro). Il Business è predisposto in `config/plans.php` con `coming_soon: true` ma non è attivo. Vedi la decisione finale in §11.

---

### ✅ 3.6 — Export PDF/XLS: etichettato come prossimamente

**Rischio reputazionale:** mostrare una feature in vendita che non esiste ancora (anche solo nel badge Pro del menu) crea aspettative non mantenute. Se un utente Pro si aspetta di esportare e non trova nulla, è delusione.

**Azione consigliata:** rimuovere la voce dall'elenco features Pro in `config/plans.php` finché non è implementata, oppure aggiungere una nota "(prossimamente)" in modo esplicito nella pagina di pricing.

> ✅ **Risolto al 01/04/2026:** la voce Export è etichettata `prossimamente` in `config/plans.php`. Non è presentata come feature già disponibile.

---

### ✅ 3.7 — Il prezzo è stato allineato ai competitor

**Benchmark italiano e internazionale:**

| App | Prezzo free tier | Prezzo Pro/Plus | Supporto |
|---|---|---|---|
| Toshl Finance | Limitato (3 conti) | 2,99 €/mese | Email + forum |
| Spendee | Limitato | 2,99 €/mese | Email |
| Money Manager | Gratuito | App/acquisto unico | Nessuno |
| Wallet by BudgetBakers | Limitato | 2,99 €/mese | Email |
| YNAB | No free | 14,99 $/mese | Chat + guide estese |
| Notion/Obsidian (DIY) | Gratuito | — | Community |

**Contesto:** 2,99 €/mese allinea Finanzamente ai competitor diretti di nicchia (Toshl, Spendee, Wallet). Questo prezzo riduce l'attrito psicologico e massimizza le conversioni. La competitività non si gioca più sul prezzo ma su:
1. Un'**UX superiore** pensata per l'utente italiano
2. **Feature verticali** (IVA, 730, integration Telegram, import bancari italiani)
3. Una **storia convincente** nel momento dell'upgrade — non solo un elenco di feature

**Notare che il mercato italiano è sottoservito:** quasi nessun competitor offre un'app di finanza personale pensata per l'utente italiano (IVA, 730, euro come valuta primaria, import bancari italiani). Questo è un vantaggio reale che può giustificare un premium rispetto ai competitor generici — ma solo se comunicato bene.

**Rischio concreto:** se l'utente Base è contento (ha abbastanza feature), il confronto mentale sarà "2,99 €/mese vs soddisfazione attuale = non vale". Il salto deve essere motivato da un bisogno concreto emergente, non da una feature wall artificiale.

> ✅ **Risolto al 01/04/2026:** il prezzo Pro è 2,99 €/mese (era 9,90 €). Allineato ai competitor principali. La competitività ora si gioca su UX, feature verticali italiane e comunicazione del valore — non sul prezzo.

---

### ⚠️ 3.8 — Feature Pro complesse senza supporto sono un rischio per la soddisfazione

**Problema specifico per il contesto del progetto:** alcune funzionalità Pro sono tecnicamente complesse o richiedono configurazione non banale:
- **Integrazione Telegram**: richiede trovare il bot, dare il comando corretto, linkare l'account. Senza una guida chiara (o supporto) un utente medio si blocca.
- **Gestione IVA**: logica contabile che richiede comprensione del regime fiscale. Un errore può avere implicazioni reali.
- **Import CSV/XLS con mapping colonne**: già nel Base, richiede attenzione al formato.

Un utente che paga 2,99 €/mese e non riesce a usare le feature per cui ha pagato **cancella subito**. Senza supporto diretto, la FAQ deve essere eccezionale per queste specifiche aree.

**Raccomandazione:** prima di abilitare definitivamente queste feature nel Pro, assicurarsi che esistano nella FAQ:
- Tutorial paso-passo per Telegram (con screenshot)
- Guida alla gestione IVA con esempi pratici italiani
- Template CSV per le principali banche italiane (Intesa, UniCredit, Fineco, BancaSella, N26)

---

## 4. Il momento dell'upgrade: psicologia del paywall

Attualmente l'app mostra le voci Pro nel menu con un badge dorato e redirige all'upgrade. Questo è un approccio corretto — meglio del "nascondi tutto" perché:
- L'utente **sa cosa esiste** e può desiderarlo
- Il badge crea **aspirazione**, non frustrazione
- Il redirect è immediato e senza attrito

Tuttavia, ci sono due momenti critici da gestire bene:

**Momento 1 — Primo contatto con il limite dei conti (5)**  
L'utente sta creando il sesto conto. Il banner attuale è già implementato in `Accounts/Create.tsx`. ✅ Questo è il momento giusto per l'upgrade — il bisogno è concreto e immediato. Lo stesso vale per gli altri limiti Base (ricorrenti, rimborsi, debiti/crediti, obiettivi).

**Momento 2 — Utente che clicca su una voce Pro nel menu**  
Attualmente viene rediretto a `/profile/subscription` senza contesto. L'esperienza ideale sarebbe: una pagina o modal che mostra **"Stai cercando [nome feature]. Ecco cosa include il Pro e perché vale"** — non solo un form di pagamento.

**Raccomandazione:** nella pagina `/profile/subscription`, aggiungere un parametro opzionale `?from=ricorrenti` (o simile) che mostri in evidenza la specifica feature che l'utente stava cercando, con una micro-descrizione del valore.

> ✅ **Implementato al 01/04/2026:** la pagina `/profile/subscription` accetta il parametro `?from=<moduleId>`. Quando un utente clicca su una voce Pro del menu, `AuthenticatedLayout.tsx` aggiunge automaticamente `?from=${item.moduleId}` al redirect. `SubscriptionController::show()` legge il parametro e lo passa come prop `fromFeature`. In `Subscription.tsx`, se `fromFeature` è valorizzato, appare un banner contestuale amber con il nome e la descrizione della feature. Moduli supportati: `simulations`, `inter_household_transfers`, `inbox`, `tax_refund_730`, `investments`, `asset_allocation`, `investment_assets`, `investment_analyses`, `lifestyle_score`.

---

## 5. Funzionalità Pro spostate nel Base — tutte implementate ✅

> **Nota al 01/04/2026:** tutte le proposte in questa tabella sono state implementate. Vedi §1.1 per lo stato attuale del piano Base.

| Funzionalità | Proposta | Motivazione | Impatto tecnico | Stato |
|---|---|---|---|---|
| Conti | Da 3 a **5** | 3 è aggressivo per chi ha conto + risparmio + prepagata | Cambia 1 costante in `config/plans.php` + controller | ✅ |
| Transazioni ricorrenti | Base: max **5 attive** | Affitto, Netflix, palestra: 5 copre il 90% dei casi | Check in `RecurringTransactionController::store()` | ✅ |
| Rimborsi | Base: max **10 attivi** | Target 18–30 anni ha molte spese condivise | Check in `RefundController::store()` | ✅ |
| Debiti e crediti | Base: max **5 attivi** | Prestito tra amici: caso normalissimo | Check in `DebtCreditController::store()` | ✅ |
| Obiettivi finanziari | Base: **1 attivo** | Gancio emotivo chiave per l'adozione iniziale | Check in `FinancialGoalController::store()` | ✅ |

---

## 6. Funzionalità che giustificano pienamente il Pro

Queste non andrebbero mai spostate nel Base — il loro valore è alto, specifico, e spinge alla conversione chi ne ha davvero bisogno:

| Funzionalità | Perché rimane Pro | Target specifico |
|---|---|---|
| Investimenti + Asset Allocation | Complessità alta, nicchia ad alto reddito | Investitore |
| Analisi investimenti | Dipende da dati storici e dati mercato | Investitore |
| Simulazioni finanziarie | Feature "wow", differenziante, costosa da mantenere | Pianificatore avanzato |
| Household multi-membro + inviti | Uso familiare/coppia, valore chiaro | Famiglia / coppia |
| Trasferimenti inter-household | Solo per chi ha più household | Famiglia estesa |
| Telegram + Inbox | Integrazione tecnica avanzata, setup non banale | Tech-savvy |
| Detrazioni fiscali / 730 | Valore alto per chi la usa, giustifica costo da sola | Lavoratore dipendente |
| Gestione IVA | Partita IVA only, altissimo valore specifico | Freelance / P.IVA |
| Lifestyle Inflation Score | Feature originale, difficile da copiare | Orientati alla crescita |
| Ricorrenti illimitate | Il salto naturale da max 5 Base | Chi ha molti abbonamenti |
| Obiettivi illimitati + proiezioni | Il salto naturale da 1 Base | Pianificatore attivo |
| Rimborsi illimitati | Il salto naturale da max 10 Base | Chi gestisce team/gruppo |

---

## 7. Proposta di suddivisione rivista

> ✅ **Questa sezione descrive la proposta — tutte le modifiche sono state implementate al 01/04/2026.** Vedi §1.1 per lo stato attuale.

### Piano Base rivisto ✅ implementato
- Dashboard e panoramica finanziaria
- **Fino a 5 conti** (era 3) ✅
- Transazioni illimitate
- Categorie e tag illimitati
- Trasferimenti tra conti
- Budget mensile per categoria
- Import CSV/XLS con layout salvati
- **Fino a 5 transazioni ricorrenti attive** ✅
- **Fino a 10 rimborsi attivi** ✅
- **Fino a 5 debiti/crediti attivi** ✅
- **1 obiettivo finanziario attivo** ✅
- 1 sola household, nessun membro invitabile

### Piano Pro rivisto (2,99 €/mese — sconto annuale 20%) ✅ implementato
- Tutto il Base, senza nessun limite quantitativo
- Conti illimitati
- Household illimitate con membri (inviti, ruoli, permessi)
- Trasferimenti inter-household
- Transazioni ricorrenti illimitate
- Rimborsi illimitati
- Debiti e crediti illimitati
- Obiettivi finanziari illimitati + proiezioni cashflow
- Investimenti + Asset Allocation + Gestione asset
- Analisi investimenti avanzata
- Simulazioni finanziarie
- Lifestyle Inflation Score
- Integrazione Telegram + Inbox
- Detrazioni fiscali / 730
- Gestione IVA (solo Partita IVA)
- Export avanzati PDF/XLS *(quando implementati)*

---

## 8. Considerazioni operative per il singolo sviluppatore

### Costo di implementazione del piano rivisto — ✅ tutto completato

| Modifica | File da toccare | Complessità | Stato |
|---|---|---|---|
| Limite conti da 3 a 5 | `config/plans.php` | Minima — 1 riga | ✅ |
| Max 5 ricorrenti | `RecurringTransactionController::store()` | Bassa — pattern già in AccountController | ✅ |
| Max 10 rimborsi | `RefundController::store()` | Bassa | ✅ |
| Max 5 debiti/crediti | `DebtCreditController::store()` | Bassa | ✅ |
| Max 1 goal | `FinancialGoalController::store()` | Bassa | ✅ |
| Banner UI per ogni limite | Ogni pagina `Create.tsx` corrispondente | Bassa — componente già esistente | ✅ |
| Togliere requires-pro dalle rotte | `routes/web.php` | Minima | ✅ |
| Togliere requiresPro dal menu | `AuthenticatedLayout.tsx` | Minima | ✅ |
| Aggiornare `config/plans.php` features | `config/plans.php` | Minima | ✅ |
| Contestualizzare pagina upgrade (`?from=`) | `AuthenticatedLayout.tsx`, `SubscriptionController`, `Subscription.tsx` | Bassa | ✅ |

**Totale stimato:** circa 6–8 file, lavoro superficiale (pattern già stabilito). Nessuna migrazione DB necessaria.

### FAQ come unico supporto: aree critiche da documentare subito

Indipendentemente dalla suddivisione dei piani, queste tre aree Pro generano il maggior rischio di churn per problemi d'uso senza supporto:

1. **Telegram** — Tutorial con screenshot, passo per passo, con i comandi esatti del bot
2. **Import CSV/XLS** — Template precompilati per banche italiane (Intesa, Fineco, UniCredit, N26, Illimity, BancaSella)
3. **IVA/730** — Guida con esempi concreti; errori di inserimento qui hanno impatto reale

### Rischio churn Pro senza supporto

Con 2,99 €/mese ogni utente Pro rappresenta ~119 €/anno. Un utente che non riesce ad usare una feature Pro e non trova risposta in FAQ cancella in meno di 30 giorni. Priorità: documentare le feature complesse **prima** di acquisire utenti Pro.

---

## 9. Errori da non fare (anti-pattern freemium)

| Anti-pattern | Perché è sbagliato | Alternativa |
|---|---|---|
| Nascondere le voci Pro nel menu | L'utente non sa cosa esiste, non può desiderarlo | Lasciare visibili con badge (attuale ✅) |
| Paywall senza contesto | "Upgrade" senza spiegare perché → abbandono | Mostrare il valore specifico della feature bloccata |
| Troppi limiti artificiali | L'utente si sente manipolato | Limitare solo dove il bisogno di più è genuinamente avanzato |
| Feature Pro mai usata = spreco | Aumenta percezione di gonfiamento | Ogni feature Pro deve essere usata da almeno un segmento preciso |
| Promettere feature non implementate | Export PDF attualmente assente | Rimuovere o marcare chiaramente "prossimamente" |
| Nessun "momento upgrade naturale" | La conversione non avviene spontaneamente | Aggiungere contesto (da quale feature viene l'utente) alla pagina subscription |

---

## 10. Riepilogo decisioni prioritizzate

| # | Decisione | Impatto | Urgenza | Stato |
|---|---|---|---|---|
| 1 | Aumentare limite conti da 3 a **5** | Alto | Immediata | ✅ implementato |
| 2 | Ricorrenti nel Base (max 5) | Alto | Alta | ✅ implementato |
| 3 | Rimuovere Export da feature list Pro | Medio | Immediata | ✅ etichettato "prossimamente" |
| 4 | Rimborsi nel Base (max 10) | Medio | Media | ✅ implementato |
| 5 | 1 Goal nel Base | Medio | Media | ✅ implementato |
| 6 | Debiti/Crediti nel Base (max 5) | Medio | Media | ✅ implementato |
| 7 | Contestualizzare la pagina upgrade | Alto | Media | ✅ implementato |
| 8 | FAQ Telegram + CSV + IVA | Alto | Alta | 🔴 da fare |
| 9 | Prezzo Pro abbassato a 2,99 € | Strategico | — | ✅ implementato (era 9,90 €) |
| 10 | Piano Business separato — struttura predisposta | Strategico | Futura | ✅ in `config/plans.php` (coming_soon) |

---

## 11. Decisione definitiva: 2 piani (Base + Pro)

> ✅ **Decisione presa al 01/04/2026: Opzione A scelta definitivamente.** Mantenere Base + Pro come unici piani attivi. Il Business rimane in `config/plans.php` con `coming_soon: true` come riferimento per il futuro, senza attivazione pianificata.

### Il problema concreto

Se in futuro lanci un piano Business a 4,99 €/mese e per farlo **sposti** alcune feature attualmente nel Pro (es. IVA, 730, simulazioni) nel Business-only, stai togliendo feature a chi ha già pagato per averle.

**Scenario pericoloso:**
- Utente Pro paga 2,99 €/mese oggi e usa IVA + 730
- Lanci Business a 4,99 €/mese con IVA + 730 rafforzate
- Sposti IVA + 730 nel Business-only
- **Risultato:** l'utente Pro perde feature che aveva → o cancella o è costretto a scalare al Business → churn garantito + danno reputazionale

Questa operazione è anche un rischio legale: se hai venduto il Pro come "tutte le funzionalità", modificare ciò che include è una modifica di contratto.

---

### ✅ Opzione A — SCELTA — Restare a 2 piani (Base + Pro)

> **Questa è la scelta definitiva per il progetto nella fase attuale.**

**Pro:**
- Zero rischio migrazione — chi è Pro rimane Pro con tutte le feature, per sempre
- Semplicità totale — 1 piano a pagamento, 1 pricing page, 0 confusione
- Facile da comunicare — "Base gratis, Pro a 2,99 €/mese, tutto incluso"
- Nessun lock-in percepito — l'utente non teme che un futuro Piano X gli tolga qualcosa
- Adatto alla fase attuale — 1 sviluppatore, base utenti piccola, nessun supporto dedicato

**Contro:**
- Mancata cattura di valore dal segmento P.IVA/Freelance — un professionista pagherebbe facilmente 10–30 €/mese per un tool contabile; a 2,99 € stai lasciando soldi sul tavolo
- Il Pro diventa sempre più eterogeneo — aggiungendo feature nel tempo, il piano Pro diventa un calderone per target molto diversi
- Marketing poco focalizzato — "per chi è il Pro?" diventa una risposta lunga; un piano con 18+ feature per segmenti diversi non ha un messaggio chiaro

**Quando sceglierla:** oggi e per i prossimi 6–12 mesi. È la scelta giusta nella fase early.

---

### Opzione B — (non scelta, a titolo di riferimento) Business come piano strettamente AGGIUNTIVO

La parola chiave è **additivo**: Business deve contenere solo feature che **non esistono ancora nel Pro**. Nessuna feature Pro viene mai spostata a un tier superiore.

**Struttura corretta:**

| Piano | Prezzo | Contenuto |
|---|---|---|
| **Base** | Gratis | Come ora — con limiti |
| **Pro** | 2,99 €/mese | Come ora — invariato per sempre |
| **Business** | 4,99 €/mese | Tutto il Pro (identico) + nuove feature professionali non ancora sviluppate |

Esempi di feature Business legittime (cose che **non esistono** nel Pro attuale):
- Fatturazione elettronica verso clienti
- Prima nota / libro mastro per il commercialista
- IVA trimestrale e liquidazione automatica (l'attuale in Pro è solo tracciamento manuale)
- Report PDF strutturato per commercialista
- Multi-partita IVA
- Supporto prioritario via email con SLA

**Pro:**
- Zero rischio per gli utenti Pro attuali — hanno tutto quello che hanno sempre avuto, senza cambiamenti
- Cattura di valore reale dal segmento professionale
- Messaggio più chiaro — "Pro = finanza personale avanzata; Business = gestione fiscale professionale"

**Contro:**
- Richiede sviluppare feature **nuove** prima di lanciarlo — non puoi lanciare Business spostando feature già presenti nel Pro
- Maggiore complessità operativa — 2 piani a pagamento, 2 prezzi Mollie, 2 flussi di cancellazione
- Il "supporto prioritario" è una promessa operativa — non puoi offrirlo finché non hai capacità reale
- Rischio confusione — "IVA è nel Pro ma Business ha IVA avanzata: qual è la differenza?" richiede una comunicazione molto precisa

**Quando sceglierla:** quando hai >500 utenti Pro attivi, almeno 2–3 delle feature Business sopra sviluppate, e una qualche forma di supporto (anche solo email con SLA 24h).

---

### Opzione C — (da evitare sempre) Business sposta feature Pro al tier superiore

Ridurre il Pro rimuovendo feature (es. IVA/730) e spostarle nel Business per recuperare margine. **Non farlo mai.**

- Perdita di fiducia immediata da parte di chi ha già il Pro
- Rischio legale (modifica delle condizioni già vendute)
- Nel mercato italiano di nicchia, una mossa del genere si commenta su ogni forum di finanza personale

**Non essere Netflix.**

---

### Risposta diretta alla tua domanda

> *"Non è forse meglio avere solo i due piani Base e Pro, anche se un utente Pro non userà tutte quelle feature?"*

**Sì — per ora è la scelta migliore**, e lo resterà finché non avrai:
1. Un numero di utenti Pro tale da giustificare la complessità di un terzo piano (soglia indicativa: ~500 paganti)
2. Feature **nuove e aggiuntive** (non redistribuite dal Pro) da mettere nel Business
3. Una qualche forma di supporto reale per giustificare il premium del Business

Il fatto che un utente Pro non usi tutte le 18 feature è normale in ogni SaaS. Il valore non è l'utilizzo di tutto — è l'**accesso garantito** a ciò di cui si ha bisogno nel momento in cui serve.

La struttura Business è già predisposta in `config/plans.php` con `coming_soon: true`. Quando arriverà il momento giusto, il lancio sarà additivo: gli utenti Pro non vengono toccati, e il Business sarà rivolto esplicitamente a chi vuole feature professionali nuove.

**Comunicazione al lancio del Business (quando arriverà):**
> *"Il Piano Pro rimane invariato — hai tutto quello che hai sempre avuto. Il nuovo Piano Business aggiunge [lista feature nuove] per chi gestisce una P.IVA o lavora con un commercialista. Non devi fare nulla se sei soddisfatto del Pro."*

Questo trasforma un potenziale momento di paura in un momento di fiducia.

---

*Documento aggiornato: 01/04/2026 — decisione Opzione A formalizzata, tutte le sezioni allineate allo stato implementato.*
