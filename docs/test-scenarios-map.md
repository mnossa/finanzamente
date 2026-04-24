# Mappa scenari di test implementati

## Area: Rate Limiting e Sicurezza
- Blocca dopo tentativi massimi di registrazione
- Applica delay progressivo dopo tentativi ripetuti
- Logga IP come hash (mai in chiaro)

## Area: Modalità Applicazione (AppMode)
- Homepage accessibile in modalità normale
- Pagina registrazione accessibile in modalità normale
- Registrazione consentita in modalità normale
- Flag prelaunch e waitlist esposti correttamente
- Login mostra/nasconde link registrazione a seconda della modalità
- Dashboard accessibile solo se autenticato e in modalità corretta
- Modalità prelaunch: redirect, blocco, accesso owner, case-insensitive, owner vuoto
- Modalità waitlist: flag waitlist, registrazione consentita

## Area: Quiz Profilazione
- Accesso quiz dopo registrazione
- Salvataggio risposte quiz
- Modifica impostazioni quiz
- Middleware: redirect se quiz non completato

## Area: Termometro Tasse
- Calcolo entrate lorde da transazioni
- Calcolo imposta sostitutiva e INPS da profilo
- Calcolo margine netto e percentuale accantonamento
- Visibilità solo per utenti con partita IVA
- Validazione input aliquote

## Area: Debiti/Crediti e Collegamento Transazioni
- Collegamento transazioni/debiti
- Calcolo saldo rimanente, interessi, stato debito
- Pagamento debito tramite transazione
- Listener aggiornamento saldo debito
- Validazione debt_credit_id in transazioni

## Area: Transazioni Ricorrenti
- Generazione automatica transazioni da ricorrenza
- Tracking ultima generazione
- Generazione giornaliera via comando artisan
- Service layer: metodi principali (generate, next, isActive)
- Frequenze supportate: daily, weekly, monthly, yearly

## Area: Trasferimenti Inter-Household
- Trasferimento solo tra proprie households
- Creazione immediata, nessuna approvazione
- Tracciabilità e stato transfer
- Gestione valute multiple, tasso di cambio, fee
- Policy di accesso (visualizza, crea, elimina)

## Area: Dashboard, Profilo, Notifiche
- Accesso dashboard autenticato
- Modifica profilo e impostazioni
- Notifiche e inbox item

## Area: Import/Export, Tag, Allegati
- Import transazioni/investimenti
- Export detrazioni fiscali
- Gestione tag transazioni
- Gestione allegati

## Area: Logica fiscale
- Calcolo tasse, INPS, margine netto, accantonamento
- Test edge case (zero income, custom rates, ordinary regime)

## Area: Altro
- Asset allocation, analisi investimenti
- Lifestyle score
- Waitlist
- Layout dashboard

---

> Questa mappa è stata generata analizzando i nomi e le descrizioni dei metodi di test in Feature/ e Unit/, e incrociata con la documentazione delle feature in docs/features/.

