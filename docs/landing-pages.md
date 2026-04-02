# Landing Pages — Link di Test

Queste pagine sono destinate esclusivamente al traffico proveniente da annunci pubblicitari. Non sono linkate dalla navigazione principale del sito.

## URL delle Landing Page

| Target | URL locale | URL produzione |
|--------|-----------|----------------|
| Investitori | http://localhost:8080/per-investitori | /per-investitori |
| Famiglie e coppie | http://localhost:8080/per-famiglie | /per-famiglie |
| Freelance e P.IVA | http://localhost:8080/per-freelance | /per-freelance |
| Lavoratori dipendenti | http://localhost:8080/per-lavoratori | /per-lavoratori |
| Pianificatori finanziari | http://localhost:8080/per-pianificatori | /per-pianificatori |
| Tech-savvy | http://localhost:8080/per-tech-savvy | /per-tech-savvy |
| Crescita personale | http://localhost:8080/crescita-personale | /crescita-personale |

## Cosa controllare

- [ ] Header: solo logo + bottone "Abbonati a Pro" (nessun menu)
- [ ] Hero: H1 chiaro, sottotitolo breve, **una sola CTA** → `plan.select?plan=pro&billing_cycle=monthly`
- [ ] 3 benefit in riga
- [ ] Proof visual (mockup specifico per il target)
- [ ] Footer CTA: stesso link Pro, testo "Abbonati a FinanzaMente Pro"
- [ ] Footer: copyright + 3 link legali (nessun menu espanso)
- [ ] Tracking Umami: ogni CTA ha `data-umami-event` con nome del target e posizione (`hero` / `footer`)
- [ ] Link "Hai già un account? Accedi" presente sotto la CTA hero (per utenti esistenti)

## Tracking eventi Umami

| Evento | Posizione | Descrizione |
|--------|-----------|-------------|
| `landing-header-cta` | Header | Click sul bottone "Abbonati a Pro" nell'header |
| `landing-cta-investitori` | `hero` / `footer` | CTA della landing investitori |
| `landing-cta-famiglie` | `hero` / `footer` | CTA della landing famiglie |
| `landing-cta-freelance` | `hero` / `footer` | CTA della landing freelance |
| `landing-cta-lavoratori` | `hero` / `footer` | CTA della landing lavoratori |
| `landing-cta-pianificatori` | `hero` / `footer` | CTA della landing pianificatori |
| `landing-cta-tech-savvy` | `hero` / `footer` | CTA della landing tech-savvy |
| `landing-cta-crescita` | `hero` / `footer` | CTA della landing crescita personale |

> Su Umami filtrare per `data-umami-event-page` per isolare conversioni per singola landing page.
