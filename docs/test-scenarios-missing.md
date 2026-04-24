# Scenari NON coperti dai test (gap analysis)

## Area: Detrazioni Fiscali (730)
- [ ] Test unitari modelli detrazioni (model logic)
- [ ] Test feature controllers detrazioni (flusso end-to-end)
- [ ] Test upload file detrazioni (allegati, validazione, errori)
- [ ] Test autorizzazioni su detrazioni (accesso, modifica, cancellazione)
- [ ] Test export PDF/ZIP delle detrazioni

## Area: Transazioni Ricorrenti
- [ ] Test automatici per edge case di generazione (es. frequenze miste, salto di date, ricorrenze modificate dopo la creazione)
- [ ] Test di regressione su duplicazione transazioni (solo test manuale descritto)

## Area: Homepage pubblica
- [ ] Test automatici di accessibilità (W3C, contrasto, screen reader) — solo checklist/manuale
- [ ] Test automatici di responsive design (breakpoint) — solo checklist/manuale
- [ ] Test automatici su meta tag SEO/Open Graph (solo checklist/manuale)
- [ ] Test su animazioni scroll/intersection observer (feature opzionale)
- [ ] Test su A/B testing CTA (feature opzionale)

## Area: Landing Pages
- [ ] Test automatici su header, hero, benefit, proof visual, tracking Umami, link CTA, footer (solo checklist/manuale)

## Area: Unsplash/Magazine
- [ ] Test automatici su ricerca immagini Unsplash (solo checklist/manuale)
- [ ] Test su attribution e salvataggio locale immagini

## Area: Analytics/Newsletter
- [ ] Test automatici su tracking eventi Umami/GA/Matomo (solo checklist/manuale)
- [ ] Test su form iscrizione newsletter (solo checklist/manuale)

## Area: FAQ/Testimonianze
- [ ] Test automatici su sezione FAQ e testimonianze (solo checklist/manuale)

## Area: A/B Testing
- [ ] Test automatici su varianti CTA (solo checklist/manuale)

## Area: Edge case multi-currency
- [ ] Test approfonditi su trasferimenti inter-household con valute diverse, tassi di cambio, fee

## Area: Accessibilità avanzata
- [ ] Test automatici su label ARIA, landmark, skip-to-content, sr-only utilities (solo checklist/manuale)

---

> Questi gap sono stati individuati confrontando la mappa degli scenari testati con la documentazione delle feature, le checklist di testing e i TODO nei file markdown. Alcuni scenari sono coperti solo da test manuali/checklist, altri risultano del tutto scoperti.

