# Gestione Detrazioni Fiscali (730)

## Descrizione
Sistema completo per gestire le spese detraibili (es. spese mediche, veterinarie, istruzione, mutuo) con possibilità di allegare documenti e generare report pronti per il CAF o la dichiarazione dei redditi.

## Funzionalità Implementate

### 1. Database e Modelli

#### Migration `add_tax_deduction_fields_to_transactions_table`
Campi aggiunti alla tabella `transactions`:
- `is_tax_deductible` (boolean): Indica se la transazione è detraibile
- `tax_deduction_rate` (decimal 5,2): Percentuale di detrazione (es. 19.00 per 19%)
- `tax_deduction_type` (string 50): Tipo di detrazione (mediche, veterinarie, istruzione, mutuo, ristrutturazione, assicurazioni, previdenza, donazioni, altro)
- `tax_year` (year): Anno fiscale di riferimento

#### Migration `add_attachable_polymorphic_to_attachments_table`
Campi aggiunti alla tabella `attachments`:
- `attachable_type` e `attachable_id` (morphs): Relazione polimorfica per collegare allegati a diverse entità
- `filename` (string): Nome originale del file
- `mime_type` (string): Tipo MIME del file
- `file_size` (bigInteger): Dimensione del file in bytes

#### Modelli Aggiornati

**Transaction.php**
- Aggiunta relazione `attachments()` (morphMany)
- Metodo `getTaxDeductibleAmount()`: Calcola l'importo detraibile in base alla percentuale
- Metodo `isDeductibleForYear(int $year)`: Verifica se la transazione è detraibile per un anno specifico

**Attachment.php**
- Aggiunta relazione `attachable()` (morphTo)
- Campi fillable aggiornati

### 2. Backend e Controller

#### AttachmentController
- `store(Request $request)`: Upload di allegati per transazioni (max 5MB, formati: jpg, jpeg, png, pdf, doc, docx)
- `download(Attachment $attachment)`: Download sicuro di allegati con controllo autorizzazioni
- `destroy(Attachment $attachment)`: Eliminazione di allegati con rimozione del file dal disco

#### TaxDeductionExportController
- `index(Request $request)`: Pagina di gestione detrazioni fiscali con filtro per anno
  - Elenco transazioni detraibili
  - Riepilogo totali per tipo di detrazione
  - Conteggio allegati per transazione
- `exportPdf(Request $request)`: Export report PDF/HTML con tutte le detrazioni dell'anno
- `exportAttachments(Request $request)`: Export ZIP con tutti gli allegati organizzati per tipo di detrazione

#### TransactionController (Aggiornato)
- `index()`: Aggiunto filtro per transazioni detraibili e conteggio allegati
- `show()`: Include allegati con uploader
- `create()` e `edit()`: Preparati per gestire campi detrazioni
- `store()` e `update()`: Gestione campi detrazioni fiscali

### 3. Validazione

**StoreTransactionRequest** e **UpdateTransactionRequest** (Aggiornate)

Regole di validazione:
```php
'is_tax_deductible' => ['boolean'],
'tax_deduction_rate' => ['nullable', 'required_if:is_tax_deductible,true', 'numeric', 'min:0.01', 'max:100'],
'tax_deduction_type' => ['nullable', 'required_if:is_tax_deductible,true', 'string', 'max:50', 
    Rule::in(['mediche', 'veterinarie', 'istruzione', 'mutuo', 'ristrutturazione', 'assicurazioni', 'previdenza', 'donazioni', 'altro'])],
'tax_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
```

Messaggi di errore localizzati in italiano.

### 4. Rotte

#### Upload e gestione attachments (richiede permesso `can-modify`)
- `POST /attachments` → `attachments.store`
- `DELETE /attachments/{attachment}` → `attachments.destroy`

#### Download allegati (sola lettura)
- `GET /attachments/{attachment}/download` → `attachments.download`

#### Gestione detrazioni fiscali
- `GET /tax-deductions` → `tax-deductions.index`
- `GET /tax-deductions/export-pdf?year=2024` → `tax-deductions.export-pdf`
- `GET /tax-deductions/export-attachments?year=2024` → `tax-deductions.export-attachments`

### 5. Storage e File System

Configurazione disco `private` in `config/filesystems.php`:
```php
'private' => [
    'driver' => 'local',
    'root' => storage_path('app/private'),
    'serve' => false,
],
```

Gli allegati vengono salvati in `storage/app/private/attachments/` con nomi univoci.

### 6. Template PDF

View Blade `resources/views/pdf/tax-deductions.blade.php`:
- Design professionale e print-friendly
- Intestazione con logo e informazioni utente
- Tabelle organizzate per tipo di detrazione
- Riepilogo totali con statistiche
- Footer informativo

## Tipi di Detrazione Supportati

1. **Mediche** (es. visite mediche, farmaci, analisi)
2. **Veterinarie** (es. visite veterinarie, farmaci per animali)
3. **Istruzione** (es. tasse scolastiche, università)
4. **Mutuo** (es. interessi passivi mutuo prima casa)
5. **Ristrutturazione** (es. lavori edilizi detraibili)
6. **Assicurazioni** (es. assicurazioni vita, infortuni)
7. **Previdenza** (es. contributi previdenza complementare)
8. **Donazioni** (es. donazioni ONLUS, enti religiosi)
9. **Altro** (per spese non categorizzate)

## Percentuali di Detrazione Standard

Le percentuali più comuni in Italia:
- Spese mediche: 19%
- Spese veterinarie: 19% (max €550)
- Istruzione: 19%
- Mutuo prima casa: 19% (max €4.000)
- Ristrutturazione: 50% o 36%
- Assicurazioni: 19%
- Previdenza: varia
- Donazioni: 19% o 26%

## Flusso di Utilizzo

### 1. Registrare una spesa detraibile
```
1. Creare/modificare una transazione
2. Spuntare "Spesa detraibile"
3. Selezionare tipo di detrazione
4. Inserire percentuale (es. 19)
5. Specificare anno fiscale (default: anno corrente)
6. Salvare transazione
```

### 2. Allegare documenti
```
1. Visualizzare la transazione
2. Cliccare su "Aggiungi allegato"
3. Selezionare file (max 5MB)
4. Upload automatico
```

### 3. Generare report annuale
```
1. Accedere a "Detrazioni Fiscali" dal menu
2. Selezionare anno fiscale
3. Visualizzare riepilogo per tipo
4. Esportare PDF o ZIP allegati
```

### 4. Consegnare al CAF
```
1. Scaricare report PDF
2. Scaricare ZIP con tutti gli allegati
3. Consegnare entrambi al commercialista/CAF
```

## Sicurezza e Privacy

- **Autorizzazione**: Solo utenti con accesso alla household possono visualizzare le transazioni
- **Transazioni private**: Le transazioni private sono visibili solo al creatore
- **Upload controllato**: Limite di 5MB, solo formati sicuri (immagini, PDF, documenti)
- **Storage privato**: Gli allegati sono salvati fuori dalla webroot e non direttamente accessibili
- **Download autorizzato**: Verifica autorizzazioni prima del download

## Estensioni Future

### Da implementare nel frontend React/Inertia:
1. **Pagina Index Transazioni**: Badge "Detraibile" sulle transazioni fiscali
2. **Form Create/Edit Transazione**: Sezione detrazioni fiscali con campi condizionali
3. **Dettaglio Transazione**: Visualizzazione allegati con preview e download
4. **Upload Componente**: Drag & drop per allegati multipli
5. **Pagina Tax Deductions**: 
   - Lista transazioni detraibili filtrate per anno
   - Cards riepilogative per tipo
   - Grafici utilizzo detrazioni
   - Bottoni export PDF e ZIP
6. **Navigazione**: Link nel menu sidebar "Detrazioni Fiscali"

### Backend avanzato (opzionale):
1. **Libreria PDF**: Integrare Dompdf o Snappy per vero export PDF (attualmente HTML)
2. **OCR**: Estrazione automatica dati da scontrini/fatture
3. **Notifiche**: Reminder prima della scadenza dichiarazione
4. **Limiti**: Calcolo automatico limiti massimi detraibili per categoria
5. **Multi-anno**: Confronto anno su anno
6. **API**: Integrazione con software CAF/commercialisti

## Note per lo Sviluppo

1. **Migration**: Eseguire `php artisan migrate` prima di testare
2. **Storage**: Assicurarsi che `storage/app/private/attachments` esista e sia scrivibile
3. **Permissions**: Le rotte di upload richiedono middleware `can-modify`
4. **Testing**: Creare test per upload, download e autorizzazioni
5. **Documentazione**: Aggiungere al README principale

## Checklist Implementazione

### Backend ✅
- [x] Migration campi detrazioni transazioni
- [x] Migration relazione polimorfica attachments
- [x] Aggiornamento modelli Transaction e Attachment
- [x] Controller AttachmentController
- [x] Controller TaxDeductionExportController
- [x] Aggiornamento TransactionController
- [x] Request validation (Store/Update)
- [x] Rotte configurate
- [x] View Blade PDF
- [x] Configurazione filesystem

### Frontend ⏳
- [ ] Componente FileUpload con drag & drop
- [ ] Componente AttachmentList con preview
- [ ] Form detrazioni in Create/Edit Transaction
- [ ] Badge detraibile in Index Transactions
- [ ] Pagina Tax Deductions Index
- [ ] Export buttons e chiamate API
- [ ] Aggiunta voce menu sidebar
- [ ] Toast notifications per upload success/error

### Testing ⏳
- [ ] Test unitari modelli
- [ ] Test feature controllers
- [ ] Test upload files
- [ ] Test autorizzazioni
- [ ] Test export PDF/ZIP

## Risorse e Riferimenti

- [Agenzia delle Entrate - Detrazioni](https://www.agenziaentrate.gov.it/)
- [Modello 730 - Guida](https://www.agenziaentrate.gov.it/portale/web/guest/schede/dichiarazioni/mod730)
- [Laravel File Storage](https://laravel.com/docs/10.x/filesystem)
- [Polymorphic Relations](https://laravel.com/docs/10.x/eloquent-relationships#polymorphic-relationships)

---

**Versione**: 1.0  
**Data**: 16/02/2026  
**Autore**: GitHub Copilot  
**Branch**: archivio_detrazioni_730
